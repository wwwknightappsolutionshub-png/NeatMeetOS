<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\TenantOwnerPushSubscription;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class TenantPresenceAndPwaUsersTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_tenant_heartbeat_marks_online_and_platform_sees_presence(): void
    {
        $ctx = $this->seedTenantContext();

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/presence/heartbeat')
            ->assertOk()
            ->assertJsonPath('data.online', true)
            ->assertJsonPath('data.presence', 'online');

        $admin = User::factory()->create([
            'email' => 'platform-presence@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/platform/tenants')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $ctx['tenant']->id,
                'presence' => 'online',
                'online' => true,
            ]);
    }

    public function test_platform_can_poke_tenant(): void
    {
        $ctx = $this->seedTenantContext();
        $admin = User::factory()->create([
            'email' => 'platform-poke@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/platform/tenants/'.$ctx['tenant']->id.'/poke', [
            'message' => 'Please hop online when you can.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.notices', 1);

        $this->assertDatabaseHas('tenant_owner_notices', [
            'tenant_id' => $ctx['tenant']->id,
            'type' => 'platform.broadcast',
            'title' => 'NeatMeet is looking for you',
        ]);
    }

    public function test_platform_lists_pwa_users_and_accepts_push_request(): void
    {
        $ctx = $this->seedTenantContext();

        TenantOwnerPushSubscription::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'user_id' => $ctx['user']->id,
            'endpoint' => 'https://push.example/ep-'.uniqid(),
            'endpoint_hash' => hash('sha256', 'ep-'.uniqid()),
            'p256dh' => 'dGVzdA==',
            'auth' => 'dGVzdA==',
            'last_seen_at' => now(),
        ]);

        $admin = User::factory()->create([
            'email' => 'platform-pwa@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/platform/pwa-users?type=admin')
            ->assertOk()
            ->assertJsonPath('success', true);

        $list = $this->getJson('/api/v1/platform/pwa-users?type=admin')->json('data');
        $this->assertNotEmpty($list);
        $this->assertSame('admin', $list[0]['type']);

        $this->postJson('/api/v1/platform/pwa-users/push', [
            'title' => 'Hello PWA',
            'body' => 'Platform message',
            'type' => 'admin',
            'subscription_ids' => [$list[0]['id']],
        ])
            ->assertCreated()
            ->assertJsonPath('data.targeted', 1);
    }
}
