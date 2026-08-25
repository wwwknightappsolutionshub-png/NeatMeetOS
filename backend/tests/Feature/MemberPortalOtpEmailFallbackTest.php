<?php

namespace Tests\Feature;

use App\Domains\Crm\Models\Client;
use App\Domains\Notifications\Services\NotificationMailTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class MemberPortalOtpEmailFallbackTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_member_can_request_otp_by_email_channel(): void
    {
        $this->mock(NotificationMailTransport::class, function ($mock) {
            $mock->shouldReceive('send')
                ->once()
                ->andReturn(['ok' => true]);
        });

        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);

        Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Email',
            'display_name' => 'Email User',
            'email' => 'email-otp@example.test',
            'phone' => '+447700900901',
            'is_active' => true,
        ]);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/member/login/request-otp', [
                'email' => 'email-otp@example.test',
                'phone' => '+447700900901',
                'channel' => 'email',
            ])
            ->assertOk()
            ->assertJsonPath('data.sent', true)
            ->assertJsonPath('data.channel', 'email')
            ->assertJsonPath('data.masked_email', 'e*******p@example.test');

        $this->assertDatabaseHas('client_portal_otps', [
            'email' => 'email-otp@example.test',
        ]);
    }

    public function test_email_otp_can_complete_login(): void
    {
        $this->mock(NotificationMailTransport::class, function ($mock) {
            $mock->shouldReceive('send')->once()->andReturn(['ok' => true]);
        });

        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);

        Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Login',
            'email' => 'login-email-otp@example.test',
            'phone' => '+447700900902',
            'is_active' => true,
        ]);

        $request = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/member/login/request-otp', [
                'email' => 'login-email-otp@example.test',
                'phone' => '+447700900902',
                'channel' => 'email',
            ])
            ->assertOk();

        $otp = $request->json('data.otp');
        $this->assertNotEmpty($otp);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/member/login', [
                'email' => 'login-email-otp@example.test',
                'phone' => '+447700900902',
                'otp' => $otp,
            ])
            ->assertOk()
            ->assertJsonPath('data.client.email', 'login-email-otp@example.test');
    }
}
