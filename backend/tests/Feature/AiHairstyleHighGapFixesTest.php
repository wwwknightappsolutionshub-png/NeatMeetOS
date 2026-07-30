<?php

namespace Tests\Feature;

use App\Domains\AiHairstyle\Models\PlatformAiHairstyleSetting;
use App\Domains\AiHairstyle\Services\AiHairstyleTempCleanupService;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class AiHairstyleHighGapFixesTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_stub_generation_blocked_when_allow_stub_false(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Config::set('ai_hairstyle.allow_stub', false);
        Config::set('ai_hairstyle.default_provider', 'stub');

        PlatformAiHairstyleSetting::query()->create([
            'provider' => PlatformAiHairstyleSetting::PROVIDER_STUB,
        ]);

        $ctx = $this->seedTenantContext();
        $tenant = $ctx['tenant'];
        $tenant->forceFill(['business_type' => 'barbershop'])->save();

        $admin = User::factory()->create([
            'email' => 'platform-ai-stub-block@neatmeet.local',
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

        $this->withHeaders(['X-Tenant-Slug' => $tenant->slug])
            ->post('/api/v1/book/ai-hairstyle/sessions/'.$create->json('data.id').'/generate', [
                'public_token' => $create->json('data.public_token'),
                'selfie' => UploadedFile::fake()->image('face.jpg', 200, 200),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->putJson('/api/v1/platform/ai-hairstyle-settings', [
            'provider' => 'stub',
        ])->assertStatus(422);

        $response = $this->getJson('/api/v1/platform/ai-hairstyle-settings')
            ->assertOk()
            ->assertJsonPath('data.allow_stub', false);

        $keys = collect($response->json('data.providers'))->pluck('key')->all();
        $this->assertNotContains('stub', $keys);
    }

    public function test_temp_purge_deletes_stale_selfies(): void
    {
        Storage::fake('local');
        $prefix = 'ai_hairstyle_tmp';
        $stale = $prefix.'/tenant-a/old.jpg';
        $fresh = $prefix.'/tenant-a/new.jpg';
        Storage::disk('local')->put($stale, 'old-bytes');
        Storage::disk('local')->put($fresh, 'new-bytes');

        // Force stale mtime by rewriting with Carbon travel via cleanup age of 0 minutes
        // after freezing "now" past file write — use max age 0 so both would delete;
        // instead delete only files older than 60 by mocking lastModified via real ages.
        Config::set('ai_hairstyle.temp_max_age_minutes', 60);

        $service = app(AiHairstyleTempCleanupService::class);
        // With age 60 and files just written, nothing deleted.
        $noop = $service->purgeStale(60);
        $this->assertSame(0, $noop['deleted']);
        $this->assertTrue(Storage::disk('local')->exists($stale));

        // Age 0 minutes ⇒ anything with mtime <= now is stale.
        $purged = $service->purgeStale(0);
        $this->assertSame(2, $purged['deleted']);
        $this->assertFalse(Storage::disk('local')->exists($stale));
        $this->assertFalse(Storage::disk('local')->exists($fresh));

        Storage::disk('local')->put($fresh, 'again');
        Artisan::call('ai-hairstyle:purge-temp', ['--minutes' => 0]);
        $this->assertFalse(Storage::disk('local')->exists($fresh));
    }
}
