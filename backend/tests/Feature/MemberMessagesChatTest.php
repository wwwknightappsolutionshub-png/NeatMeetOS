<?php

namespace Tests\Feature;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientNotice;
use App\Domains\Crm\Models\ClientThreadMessage;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class MemberMessagesChatTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_member_inbound_appears_in_admin_open_inbox(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);
        Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Chatty',
            'display_name' => 'Chatty',
            'email' => 'chat@example.test',
            'phone' => '+447700901001',
            'is_active' => true,
        ]);

        $token = $this->memberLoginViaOtp($ctx['tenant']->slug, 'chat@example.test', '+447700901001');
        $headers = [
            'X-Tenant-Slug' => $ctx['tenant']->slug,
            'Authorization' => 'Bearer '.$token,
        ];

        $this->withHeaders($headers)
            ->postJson('/api/v1/member/messages/threads', ['body' => 'Hello salon'])
            ->assertCreated()
            ->assertJsonPath('data.direction', ClientThreadMessage::DIRECTION_INBOUND)
            ->assertJsonPath('data.body', 'Hello salon');

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/messages/conversations?filter=open')
            ->assertOk()
            ->assertJsonPath('data.0.needs_reply', true)
            ->assertJsonPath('data.0.last_message.body', 'Hello salon')
            ->assertJsonPath('data.0.unread_inbound_count', 1);
    }

    public function test_admin_outbound_creates_notice_and_member_sees_thread(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);
        app(TenantContext::class)->set($ctx['tenant']);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Listen',
            'email' => 'listen@example.test',
            'phone' => '+447700901002',
            'is_active' => true,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/clients/'.$client->id.'/threads', [
                'body' => 'We have a slot tomorrow',
                'channel' => ClientThreadMessage::CHANNEL_IN_APP,
            ])
            ->assertCreated()
            ->assertJsonPath('data.direction', ClientThreadMessage::DIRECTION_OUTBOUND);

        $this->assertDatabaseHas('client_notices', [
            'client_id' => $client->id,
            'type' => ClientNotice::TYPE_OPERATIONAL_IN_APP,
        ]);

        $token = $this->memberLoginViaOtp($ctx['tenant']->slug, 'listen@example.test', '+447700901002');
        $headers = [
            'X-Tenant-Slug' => $ctx['tenant']->slug,
            'Authorization' => 'Bearer '.$token,
        ];

        $this->withHeaders($headers)
            ->getJson('/api/v1/member/messages')
            ->assertOk()
            ->assertJsonPath('data.unread_thread', 1)
            ->assertJsonPath('data.thread.0.body', 'We have a slot tomorrow');

        $this->withHeaders($headers)
            ->postJson('/api/v1/member/messages/threads/read')
            ->assertOk();

        $this->withHeaders($headers)
            ->getJson('/api/v1/member/messages')
            ->assertOk()
            ->assertJsonPath('data.unread_thread', 0);
    }

    public function test_staff_mark_read_clears_needs_reply(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);
        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Need',
            'email' => 'need@example.test',
            'phone' => '+447700901003',
            'is_active' => true,
        ]);

        $token = $this->memberLoginViaOtp($ctx['tenant']->slug, 'need@example.test', '+447700901003');
        $this->withHeaders([
            'X-Tenant-Slug' => $ctx['tenant']->slug,
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/member/messages/threads', ['body' => 'Quick question'])->assertCreated();

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/clients/'.$client->id.'/threads/read')
            ->assertOk()
            ->assertJsonPath('data.updated', 1);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/messages/conversations?filter=open')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/messages/conversations?filter=all')
            ->assertOk()
            ->assertJsonPath('data.0.needs_reply', false);
    }

    public function test_thread_is_tenant_isolated(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);
        $other = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'first_name' => 'Other',
            'email' => 'other-chat@example.test',
            'phone' => '+447700901099',
            'is_active' => true,
        ]);

        ClientThreadMessage::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'client_id' => $other->id,
            'direction' => ClientThreadMessage::DIRECTION_INBOUND,
            'channel' => ClientThreadMessage::CHANNEL_IN_APP,
            'body' => 'Secret',
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/messages/conversations?filter=all')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/clients/'.$other->id.'/threads')
            ->assertNotFound();
    }
}
