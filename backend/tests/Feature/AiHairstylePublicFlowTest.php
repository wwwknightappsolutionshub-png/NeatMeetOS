<?php

namespace Tests\Feature;

use App\Domains\AiHairstyle\Models\AiHairstylePreview;
use App\Domains\AiHairstyle\Models\AiHairstyleSession;
use App\Domains\AiHairstyle\Support\AiHairstyleStatuses;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class AiHairstylePublicFlowTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    /**
     * @return array{tenant: \App\Domains\Identity\Models\Tenant, slug: string, owner_user_id: int}
     */
    private function enableAiHairstyleTenant(): array
    {
        $ctx = $this->seedTenantContext();
        $tenant = $ctx['tenant'];
        $tenant->forceFill(['business_type' => 'barbershop'])->save();

        $admin = User::factory()->create([
            'email' => 'platform-ai-flow@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/platform/tenants/'.$tenant->id.'/modules', [
            'overrides' => ['ai_hairstyle' => true],
        ])->assertOk();

        return [
            'tenant' => $tenant->fresh(),
            'slug' => $tenant->slug,
            'owner_user_id' => (int) $ctx['user']->id,
        ];
    }

    public function test_disabled_module_rejects_public_session_create(): void
    {
        $ctx = $this->seedTenantContext();
        $ctx['tenant']->forceFill(['business_type' => 'barbershop'])->save();

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/book/ai-hairstyle/sessions')
            ->assertStatus(403)
            ->assertJsonPath('code', 'feature_disabled');
    }

    public function test_happy_path_generate_select_submit_without_storing_selfie(): void
    {
        Storage::fake('public');
        $ctx = $this->enableAiHairstyleTenant();

        $create = $this->withHeaders(['X-Tenant-Slug' => $ctx['slug']])
            ->postJson('/api/v1/book/ai-hairstyle/sessions')
            ->assertCreated()
            ->assertJsonPath('data.status', AiHairstyleStatuses::SESSION_DRAFT);

        $sessionId = $create->json('data.id');
        $token = $create->json('data.public_token');
        $this->assertNotEmpty($token);

        $selfie = UploadedFile::fake()->image('face.jpg', 400, 500);

        $generated = $this->withHeaders(['X-Tenant-Slug' => $ctx['slug']])
            ->post('/api/v1/book/ai-hairstyle/sessions/'.$sessionId.'/generate', [
                'public_token' => $token,
                'selfie' => $selfie,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', AiHairstyleStatuses::SESSION_READY);

        $previews = $generated->json('data.previews');
        $this->assertIsArray($previews);
        $this->assertGreaterThanOrEqual(2, count($previews));
        $this->assertNotEmpty($previews[0]['composite_image_url']);

        // P1: no selfie/original columns; only composite files on disk.
        $sessionColumns = \Schema::getColumnListing('ai_hairstyle_sessions');
        $this->assertNotContains('selfie_url', $sessionColumns);
        $this->assertNotContains('original_image_url', $sessionColumns);

        $allFiles = Storage::disk('public')->allFiles();
        foreach ($allFiles as $path) {
            $this->assertStringContainsString('ai_hairstyle/', $path);
            $this->assertStringNotContainsString('selfie', $path);
            $this->assertStringNotContainsString('original', $path);
        }

        $previewId = $previews[0]['id'];

        $this->withHeaders(['X-Tenant-Slug' => $ctx['slug']])
            ->postJson('/api/v1/book/ai-hairstyle/sessions/'.$sessionId.'/select', [
                'public_token' => $token,
                'preview_ids' => [$previewId],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', AiHairstyleStatuses::SESSION_SELECTED);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['slug']])
            ->postJson('/api/v1/book/ai-hairstyle/sessions/'.$sessionId.'/submit', [
                'public_token' => $token,
                'first_name' => 'Jordan',
                'last_name' => 'Lee',
                'email' => 'jordan@example.test',
                'phone' => '+447700900111',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', AiHairstyleStatuses::SESSION_SUBMITTED);

        $session = AiHairstyleSession::withoutGlobalScopes()->findOrFail($sessionId);
        $this->assertSame(AiHairstyleStatuses::SESSION_SUBMITTED, $session->status);
        $this->assertSame('Jordan', $session->metadata['contact']['first_name'] ?? null);
        $this->assertCount(4, AiHairstylePreview::withoutGlobalScopes()->where('session_id', $sessionId)->get());

        $this->assertDatabaseHas('tenant_owner_notices', [
            'tenant_id' => $ctx['tenant']->id,
            'user_id' => $ctx['owner_user_id'],
            'type' => 'ai_hairstyle.submitted',
            'href' => '/admin/ai-hairstyle',
        ]);

        $this->assertDatabaseHas('client_timeline_events', [
            'client_id' => $session->client_id,
            'event_type' => 'ai_hairstyle.submitted',
        ]);
    }

    public function test_invalid_token_is_rejected(): void
    {
        Storage::fake('public');
        $ctx = $this->enableAiHairstyleTenant();

        $create = $this->withHeaders(['X-Tenant-Slug' => $ctx['slug']])
            ->postJson('/api/v1/book/ai-hairstyle/sessions')
            ->assertCreated();

        $this->withHeaders(['X-Tenant-Slug' => $ctx['slug']])
            ->getJson('/api/v1/book/ai-hairstyle/sessions/'.$create->json('data.id').'?public_token=wrong-token')
            ->assertStatus(422);
    }
}
