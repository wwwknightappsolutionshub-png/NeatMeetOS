<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class PlatformTenantOwnerPhoneTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function actingAsPlatformManager(): User
    {
        $user = User::query()->create([
            'name' => 'Platform Manager Phone',
            'email' => 'platform.manager.phone@example.test',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
            'platform_role' => 'manager',
        ]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_manager_can_change_tenant_owner_phone(): void
    {
        $this->actingAsPlatformManager();
        $ctx = $this->seedTenantContext(['identity.view']);
        $ctx['tenant']->forceFill([
            'owner_whatsapp' => '+447700900111',
            'contact_phone' => '+447700900111',
        ])->save();

        $this->putJson('/api/v1/platform/tenants/'.$ctx['tenant']->id.'/owner-phone', [
            'phone' => '07700900123',
        ])
            ->assertOk()
            ->assertJsonPath('data.owner_whatsapp', '+447700900123')
            ->assertJsonPath('data.contact_phone', '+447700900123');

        $this->assertDatabaseHas('tenants', [
            'id' => $ctx['tenant']->id,
            'owner_whatsapp' => '+447700900123',
            'contact_phone' => '+447700900123',
        ]);

        $this->postJson('/api/v1/platform/tenants/'.$ctx['tenant']->id.'/owner-phone', [
            'phone' => '+447700900999',
        ])
            ->assertOk()
            ->assertJsonPath('data.owner_whatsapp', '+447700900999');
    }

    public function test_rejects_invalid_phone(): void
    {
        $this->actingAsPlatformManager();
        $ctx = $this->seedTenantContext(['identity.view']);
        $ctx['tenant']->forceFill([
            'owner_whatsapp' => '+447700900111',
            'contact_phone' => '+447700900111',
        ])->save();

        $this->putJson('/api/v1/platform/tenants/'.$ctx['tenant']->id.'/owner-phone', [
            'phone' => 'not-a-phone',
        ])->assertStatus(422);

        $this->assertDatabaseHas('tenants', [
            'id' => $ctx['tenant']->id,
            'owner_whatsapp' => '+447700900111',
        ]);
    }

    public function test_support_role_cannot_change_owner_phone(): void
    {
        $support = User::query()->create([
            'name' => 'Platform Support Phone',
            'email' => 'platform.support.phone@example.test',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
            'platform_role' => 'support',
        ]);
        Sanctum::actingAs($support);

        $ctx = $this->seedTenantContext(['identity.view']);

        $this->putJson('/api/v1/platform/tenants/'.$ctx['tenant']->id.'/owner-phone', [
            'phone' => '+447700900123',
        ])->assertForbidden();
    }
}
