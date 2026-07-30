<?php

namespace Tests\Feature;

use App\Domains\AiHairstyle\Models\PlatformAiHairstyleSetting;
use App\Domains\AiHairstyle\Support\AiHairstyleStatuses;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class AiHairstyleReplicateProviderTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_platform_can_read_and_update_ai_hairstyle_provider(): void
    {
        $admin = User::factory()->create([
            'email' => 'platform-ai-settings@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
            'platform_role' => 'owner',
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/platform/ai-hairstyle-settings')
            ->assertOk()
            ->assertJsonPath('data.provider', 'stub');

        Config::set('ai_hairstyle.replicate.api_token', 'r8_test_token');

        $this->putJson('/api/v1/platform/ai-hairstyle-settings', [
            'provider' => 'replicate',
        ])->assertOk()
            ->assertJsonPath('data.provider', 'replicate')
            ->assertJsonPath('data.replicate_configured', true);

        $this->assertDatabaseHas('platform_ai_hairstyle_settings', [
            'provider' => PlatformAiHairstyleSetting::PROVIDER_REPLICATE,
        ]);
    }

    public function test_replicate_provider_generates_via_http_fake(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Config::set('ai_hairstyle.replicate.api_token', 'r8_test_token');
        Config::set('ai_hairstyle.replicate.model', 'zsxkib/instant-id');
        Config::set('ai_hairstyle.replicate.poll_interval_ms', 1);
        Config::set('ai_hairstyle.replicate.poll_timeout_seconds', 5);

        PlatformAiHairstyleSetting::query()->create([
            'provider' => PlatformAiHairstyleSetting::PROVIDER_REPLICATE,
        ]);

        $predictionCalls = 0;
        Http::fake(function ($request) use (&$predictionCalls) {
            $url = $request->url();
            if (str_contains($url, '/models/') && str_contains($url, '/predictions') && $request->method() === 'POST') {
                $predictionCalls++;

                return Http::response([
                    'id' => 'pred-'.$predictionCalls,
                    'status' => 'starting',
                ], 201);
            }

            if (preg_match('#/predictions/pred-(\d+)$#', $url)) {
                return Http::response([
                    'id' => 'pred-1',
                    'status' => 'succeeded',
                    'output' => 'https://cdn.example.test/out.jpg',
                ], 200);
            }

            if (str_contains($url, 'cdn.example.test/out.jpg')) {
                return Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']);
            }

            return Http::response(['error' => 'unexpected '.$url], 500);
        });

        $ctx = $this->seedTenantContext();
        $tenant = $ctx['tenant'];
        $tenant->forceFill(['business_type' => 'barbershop'])->save();

        $admin = User::factory()->create([
            'email' => 'platform-ai-replicate@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
            'platform_role' => 'owner',
        ]);
        Sanctum::actingAs($admin);
        $this->putJson('/api/v1/platform/tenants/'.$tenant->id.'/modules', [
            'overrides' => ['ai_hairstyle' => true],
        ])->assertOk();

        $create = $this->withHeaders(['X-Tenant-Slug' => $tenant->slug])
            ->postJson('/api/v1/book/ai-hairstyle/sessions')
            ->assertCreated();

        $sessionId = $create->json('data.id');
        $token = $create->json('data.public_token');

        $generated = $this->withHeaders(['X-Tenant-Slug' => $tenant->slug])
            ->post('/api/v1/book/ai-hairstyle/sessions/'.$sessionId.'/generate', [
                'public_token' => $token,
                'selfie' => UploadedFile::fake()->image('face.jpg', 320, 400),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        // Sync queue runs the job before response returns.
        $this->assertSame(AiHairstyleStatuses::SESSION_READY, $generated->json('data.status'));
        $this->assertSame('replicate', $generated->json('data.provider'));
        $this->assertCount(4, $generated->json('data.previews'));
        $this->assertSame(4, $predictionCalls);

        $prefix = config('ai_hairstyle.temp_prefix', 'ai_hairstyle_tmp');
        $this->assertFalse(
            Storage::disk('local')->exists("{$prefix}/{$tenant->id}/{$sessionId}.jpg"),
        );
    }
}
