<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationMessageStatus;
use App\Domains\Notifications\Enums\NotificationPurpose;
use App\Domains\Notifications\Enums\NotificationSourceType;
use App\Domains\Notifications\Models\NotificationMessage;
use App\Domains\Notifications\Models\PlatformWhatsAppSettings;
use App\Domains\Notifications\Models\TenantWhatsAppSettings;
use App\Domains\Notifications\Services\WhatsApp\WhatsAppCredentialResolver;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class TenantHostedWhatsAppAndPlatformPurgeTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function actingAsPlatformOwner(): User
    {
        $user = User::query()->create([
            'name' => 'Platform Owner WA2',
            'email' => 'platform.wa2@example.test',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
            'platform_role' => 'owner',
        ]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_platform_can_purge_stale_whatsapp_messages(): void
    {
        $this->actingAsPlatformOwner();

        PlatformWhatsAppSettings::query()->create([
            'enabled' => true,
            'provider' => 'genius',
            'api_key' => 'api-test',
            'session_id' => 'session_platform',
            'base_url' => 'https://restapi.geniusdevel.com',
        ]);

        $ctx = $this->seedTenantContext(['integrations.view', 'integrations.manage']);

        $stale = NotificationMessage::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'source_type' => NotificationSourceType::BOOKING,
            'purpose' => NotificationPurpose::BOOKING_CONFIRMATION,
            'channel' => NotificationChannel::WHATSAPP,
            'status' => NotificationMessageStatus::FAILED,
            'recipient_address' => '+447700900001',
            'body_text' => 'stale',
            'queued_at' => now()->subHours(5),
        ]);
        $stale->forceFill([
            'created_at' => now()->subHours(5),
            'updated_at' => now()->subHours(5),
        ])->save();

        $this->postJson('/api/v1/platform/whatsapp-settings/purge', [
            'include_failed_jobs' => true,
            'include_stale_messages' => true,
            'older_than_hours' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('data.cancelled_messages', 1);

        $this->assertDatabaseHas('notifications_messages', [
            'tenant_id' => $ctx['tenant']->id,
            'channel' => NotificationChannel::WHATSAPP,
            'status' => NotificationMessageStatus::CANCELLED,
        ]);
    }

    public function test_tenant_hosted_session_uses_platform_api_key(): void
    {
        $ctx = $this->seedTenantContext(['integrations.view', 'integrations.manage']);
        app(TenantContext::class)->set($ctx['tenant']);

        PlatformWhatsAppSettings::query()->create([
            'enabled' => true,
            'provider' => 'genius',
            'api_key' => 'api-platform-key',
            'session_id' => 'session_platform_default',
            'base_url' => 'https://restapi.geniusdevel.com',
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/whatsapp/session/init')
            ->assertOk()
            ->assertJsonPath('data.whatsapp.hosted_session.status', 'pending_scan');

        $sessionId = TenantWhatsAppSettings::withoutGlobalScopes()
            ->where('tenant_id', $ctx['tenant']->id)
            ->value('hosted_session_id');
        $this->assertNotEmpty($sessionId);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/whatsapp/session/activate', [
                'phone_number' => '+447700900555',
            ])
            ->assertOk()
            ->assertJsonPath('data.whatsapp.hosted_session.status', 'active')
            ->assertJsonPath('data.whatsapp.active_source', 'tenant')
            ->assertJsonPath('data.whatsapp.using_platform_fallback', false);

        $resolved = app(WhatsAppCredentialResolver::class)->resolve($ctx['tenant']->id);
        $this->assertTrue($resolved['ready']);
        $this->assertSame('tenant', $resolved['source']);
        $this->assertSame('api-platform-key', $resolved['genius']['api_key']);
        $this->assertSame($sessionId, $resolved['genius']['session_id']);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/whatsapp/session/disconnect')
            ->assertOk()
            ->assertJsonPath('data.whatsapp.using_platform_fallback', true);

        $fallback = app(WhatsAppCredentialResolver::class)->resolve($ctx['tenant']->id);
        $this->assertSame('platform', $fallback['source']);
        $this->assertSame('session_platform_default', $fallback['genius']['session_id']);
    }

    public function test_platform_whatsapp_test_endpoint(): void
    {
        $this->actingAsPlatformOwner();
        PlatformWhatsAppSettings::query()->create([
            'enabled' => true,
            'provider' => 'genius',
            'api_key' => 'api-test',
            'session_id' => 'session_platform',
            'base_url' => 'https://restapi.geniusdevel.com',
        ]);

        Http::fake([
            'restapi.geniusdevel.com/*' => Http::response(['ok' => true], 200),
        ]);

        $this->postJson('/api/v1/platform/whatsapp-settings/test', [
            'phone' => '+447700900888',
            'message' => 'Custom platform test',
        ])
            ->assertOk()
            ->assertJsonPath('data.sent', true);
    }
}
