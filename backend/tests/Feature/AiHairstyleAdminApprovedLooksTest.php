<?php

namespace Tests\Feature;

use App\Domains\AiHairstyle\Mail\AiHairstyleLookAcceptedMail;
use App\Domains\AiHairstyle\Mail\AiHairstyleLookDeclinedMail;
use App\Domains\AiHairstyle\Models\AiHairstyleSession;
use App\Domains\AiHairstyle\Support\AiHairstyleStatuses;
use App\Domains\Crm\Models\ClientNotice;
use App\Domains\Crm\Models\ClientTimelineEvent;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class AiHairstyleAdminApprovedLooksTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function enableAndSubmitLook(): array
    {
        Mail::fake();
        Storage::fake('public');
        Storage::fake('local');

        $ctx = $this->seedTenantContext([
            'identity.view',
            'ai_hairstyle.view',
            'ai_hairstyle.manage',
            'crm.view',
            'crm.manage',
        ]);
        $tenant = $ctx['tenant'];
        $tenant->forceFill(['business_type' => 'boutique'])->save();

        $admin = User::factory()->create([
            'email' => 'platform-ai-admin-looks@neatmeet.local',
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
                'selfie' => UploadedFile::fake()->image('face.jpg', 300, 400),
            ], ['Accept' => 'application/json'])
            ->assertOk();
        $previewId = $generated->json('data.previews.0.id');

        $this->withHeaders(['X-Tenant-Slug' => $tenant->slug])
            ->postJson('/api/v1/book/ai-hairstyle/sessions/'.$sessionId.'/select', [
                'public_token' => $token,
                'preview_ids' => [$previewId],
            ])->assertOk();

        $this->withHeaders(['X-Tenant-Slug' => $tenant->slug])
            ->postJson('/api/v1/book/ai-hairstyle/sessions/'.$sessionId.'/submit', [
                'public_token' => $token,
                'first_name' => 'Sam',
                'last_name' => 'Rivera',
                'email' => 'sam.rivera@example.test',
                'notes' => 'Prefer shorter sides',
            ])->assertOk()
            ->assertJsonPath('data.status', AiHairstyleStatuses::SESSION_SUBMITTED);

        return array_merge($ctx, [
            'sessionId' => $sessionId,
            'previewId' => $previewId,
        ]);
    }

    public function test_admin_lists_only_submitted_and_accept_notifies_client(): void
    {
        $ctx = $this->enableAndSubmitLook();

        Sanctum::actingAs($ctx['user']);
        $list = $this->withTenantAuth($ctx['token'], $ctx['tenant']->slug)
            ->getJson('/api/v1/admin/ai-hairstyle/sessions')
            ->assertOk();

        $items = $list->json('data.items');
        $this->assertCount(1, $items);
        $this->assertSame($ctx['sessionId'], $items[0]['id']);
        $this->assertSame('Sam Rivera', $items[0]['client']['display_name']);
        $this->assertNotEmpty($items[0]['selected_previews']);
        $this->assertArrayNotHasKey('public_token', $items[0]);
        $encoded = json_encode($items[0]);
        $this->assertStringNotContainsString('selfie', strtolower((string) $encoded));
        $this->assertStringNotContainsString('original_image', strtolower((string) $encoded));

        $this->withTenantAuth($ctx['token'], $ctx['tenant']->slug)
            ->postJson('/api/v1/admin/ai-hairstyle/sessions/'.$ctx['sessionId'].'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', AiHairstyleStatuses::SESSION_ACCEPTED);

        $session = AiHairstyleSession::withoutGlobalScopes()->findOrFail($ctx['sessionId']);
        $this->assertSame(AiHairstyleStatuses::SESSION_ACCEPTED, $session->status);
        $this->assertNotNull($session->client_id);

        $this->assertDatabaseHas('client_notices', [
            'client_id' => $session->client_id,
            'type' => ClientNotice::TYPE_OPERATIONAL_IN_APP,
            'title' => 'Your look was approved',
        ]);

        Mail::assertQueued(AiHairstyleLookAcceptedMail::class, function (AiHairstyleLookAcceptedMail $mail) {
            return $mail->hasTo('sam.rivera@example.test')
                && $mail->lookLabel !== '';
        });

        $this->assertDatabaseHas('client_timeline_events', [
            'client_id' => $session->client_id,
            'event_type' => ClientTimelineEvent::EVENT_AI_HAIRSTYLE_ACCEPTED,
        ]);

        $this->withTenantAuth($ctx['token'], $ctx['tenant']->slug)
            ->getJson('/api/v1/admin/ai-hairstyle/sessions')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    public function test_admin_can_decline_submitted_look_and_notify_client(): void
    {
        $ctx = $this->enableAndSubmitLook();

        Sanctum::actingAs($ctx['user']);
        $this->withTenantAuth($ctx['token'], $ctx['tenant']->slug)
            ->postJson('/api/v1/admin/ai-hairstyle/sessions/'.$ctx['sessionId'].'/decline')
            ->assertOk()
            ->assertJsonPath('data.status', AiHairstyleStatuses::SESSION_CANCELLED);

        $session = AiHairstyleSession::withoutGlobalScopes()->findOrFail($ctx['sessionId']);
        $this->assertSame(AiHairstyleStatuses::SESSION_CANCELLED, $session->status);

        $this->assertDatabaseHas('client_notices', [
            'client_id' => $session->client_id,
            'type' => ClientNotice::TYPE_OPERATIONAL_IN_APP,
            'title' => 'Your look was not approved',
        ]);

        Mail::assertQueued(AiHairstyleLookDeclinedMail::class, function (AiHairstyleLookDeclinedMail $mail) {
            return $mail->hasTo('sam.rivera@example.test');
        });

        $this->assertDatabaseHas('client_timeline_events', [
            'client_id' => $session->client_id,
            'event_type' => ClientTimelineEvent::EVENT_AI_HAIRSTYLE_DECLINED,
        ]);

        $this->withTenantAuth($ctx['token'], $ctx['tenant']->slug)
            ->getJson('/api/v1/admin/ai-hairstyle/sessions')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    public function test_draft_sessions_are_hidden_from_admin_queue(): void
    {
        Storage::fake('public');
        $ctx = $this->seedTenantContext(['ai_hairstyle.view', 'ai_hairstyle.manage']);
        $ctx['tenant']->forceFill(['business_type' => 'barbershop'])->save();

        $platform = User::factory()->create([
            'email' => 'platform-ai-draft-hide@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
            'platform_role' => 'owner',
        ]);
        Sanctum::actingAs($platform);
        $this->putJson('/api/v1/platform/tenants/'.$ctx['tenant']->id.'/modules', [
            'overrides' => ['ai_hairstyle' => true],
        ])->assertOk();

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/book/ai-hairstyle/sessions')
            ->assertCreated();

        Sanctum::actingAs($ctx['user']);
        $this->withTenantAuth($ctx['token'], $ctx['tenant']->slug)
            ->getJson('/api/v1/admin/ai-hairstyle/sessions')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }
}
