<?php

namespace Tests\Feature;

use App\Domains\AiHairstyle\Models\AiHairstylePreview;
use App\Domains\AiHairstyle\Models\AiHairstyleSession;
use App\Domains\AiHairstyle\Services\AiHairstyleSessionService;
use App\Domains\AiHairstyle\Support\AiHairstyleStatuses;
use App\Domains\Crm\Models\Client;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class AiHairstyleSessionDomainTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_happy_path_status_machine_persists_composites_only(): void
    {
        $ctx = $this->seedTenantContext();
        app(TenantContext::class)->set($ctx['tenant']);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Alex',
            'last_name' => 'Client',
            'display_name' => 'Alex Client',
            'email' => 'alex@example.test',
            'is_active' => true,
        ]);

        /** @var AiHairstyleSessionService $service */
        $service = app(AiHairstyleSessionService::class);

        $session = $service->createDraft(['client_id' => $client->id]);
        $this->assertSame(AiHairstyleStatuses::SESSION_DRAFT, $session->status);

        $session = $service->markGenerating($session, 'replicate', 'job-1');
        $this->assertSame(AiHairstyleStatuses::SESSION_GENERATING, $session->status);
        $this->assertSame('replicate', $session->provider);

        $session = $service->markReady($session, [
            [
                'composite_image_url' => 'https://cdn.example.test/look-a.jpg',
                'style_label' => 'Fade',
                'style_key' => 'fade',
                'sort_order' => 0,
            ],
            [
                'composite_image_url' => 'https://cdn.example.test/look-b.jpg',
                'style_label' => 'Crop',
                'style_key' => 'crop',
                'sort_order' => 1,
            ],
        ]);
        $this->assertSame(AiHairstyleStatuses::SESSION_READY, $session->status);
        $this->assertCount(2, $session->previews);
        $this->assertSame(
            AiHairstyleStatuses::PREVIEW_READY,
            $session->previews->first()->status,
        );

        $previewId = $session->previews->first()->id;
        $session = $service->selectPreviews($session, [$previewId]);
        $this->assertSame(AiHairstyleStatuses::SESSION_SELECTED, $session->status);
        $this->assertSame([$previewId], $session->selected_preview_ids);

        $session = $service->submit($session);
        $this->assertSame(AiHairstyleStatuses::SESSION_SUBMITTED, $session->status);
        $this->assertNotNull($session->submitted_at);

        $session = $service->accept($session, $ctx['user']->id);
        $this->assertSame(AiHairstyleStatuses::SESSION_ACCEPTED, $session->status);
        $this->assertNotNull($session->accepted_at);
        $this->assertSame($ctx['user']->id, $session->accepted_by_user_id);

        $columns = \Schema::getColumnListing('ai_hairstyle_sessions');
        $this->assertNotContains('selfie_url', $columns);
        $this->assertNotContains('original_image_url', $columns);
        $this->assertNotContains('face_image_url', $columns);

        $previewColumns = \Schema::getColumnListing('ai_hairstyle_previews');
        $this->assertContains('composite_image_url', $previewColumns);
        $this->assertNotContains('selfie_url', $previewColumns);
        $this->assertNotContains('original_image_url', $previewColumns);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $ctx = $this->seedTenantContext();
        app(TenantContext::class)->set($ctx['tenant']);

        $service = app(AiHairstyleSessionService::class);
        $session = $service->createDraft();

        $this->expectException(ValidationException::class);
        $service->submit($session);
    }

    public function test_failed_generation_can_retry(): void
    {
        $ctx = $this->seedTenantContext();
        app(TenantContext::class)->set($ctx['tenant']);

        $service = app(AiHairstyleSessionService::class);
        $session = $service->createDraft();
        $session = $service->markGenerating($session);
        $session = $service->markFailed($session, 'provider timeout');

        $this->assertSame(AiHairstyleStatuses::SESSION_FAILED, $session->status);
        $this->assertSame('provider timeout', $session->error_message);

        $session = $service->markGenerating($session);
        $this->assertSame(AiHairstyleStatuses::SESSION_GENERATING, $session->status);
        $this->assertNull($session->error_message);
    }

    public function test_tenant_isolation_hides_foreign_sessions(): void
    {
        $ctx = $this->seedTenantContext();
        app(TenantContext::class)->set($ctx['tenant']);

        $service = app(AiHairstyleSessionService::class);
        $local = $service->createDraft();

        $foreign = AiHairstyleSession::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'status' => AiHairstyleStatuses::SESSION_DRAFT,
            'expires_at' => now()->addDay(),
        ]);

        AiHairstylePreview::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'session_id' => $foreign->id,
            'status' => AiHairstyleStatuses::PREVIEW_READY,
            'composite_image_url' => 'https://cdn.example.test/foreign.jpg',
            'sort_order' => 0,
        ]);

        $this->assertNull(AiHairstyleSession::query()->find($foreign->id));
        $this->assertNotNull(AiHairstyleSession::query()->find($local->id));

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $service->find($foreign->id);
    }

    public function test_select_requires_owned_ready_previews(): void
    {
        $ctx = $this->seedTenantContext();
        app(TenantContext::class)->set($ctx['tenant']);

        $service = app(AiHairstyleSessionService::class);
        $session = $service->createDraft();
        $session = $service->markGenerating($session);
        $session = $service->markReady($session, [
            ['composite_image_url' => 'https://cdn.example.test/a.jpg', 'style_key' => 'a'],
        ]);

        $this->expectException(ValidationException::class);
        $service->selectPreviews($session, ['00000000-0000-0000-0000-000000000099']);
    }
}
