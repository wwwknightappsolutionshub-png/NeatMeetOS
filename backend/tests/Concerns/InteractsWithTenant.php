<?php

namespace Tests\Concerns;

use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\SubscriptionPlan;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\TenantSubscription;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\Workspace;
use App\Domains\Identity\Support\PlatformModuleCatalogue;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait InteractsWithTenant
{
    protected function seedTenantContext(
        array $permissionIds = [
            'identity.view',
            'identity.manage',
            'identity.access.manage',
            'identity.audit.view',
            'crm.view',
            'crm.manage',
        ],
    ): array {
        $this->seed(PermissionCatalogueSeeder::class);

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter-'.Str::random(6),
            'billing_interval' => 'monthly',
            'limits' => ['max_locations' => 2, 'max_staff' => 10],
            'features' => array_fill_keys(PlatformModuleCatalogue::keys(), true),
            'is_active' => true,
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Test Salon',
            'slug' => 'test-salon',
            'status' => 'active',
            'subscription_plan_id' => $plan->id,
            'timezone' => 'Europe/London',
        ]);

        TenantSubscription::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'subscription_plan_id' => $plan->id,
            'status' => TenantSubscription::STATUS_ACTIVE,
            'billing_interval' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $otherTenant = Tenant::query()->create([
            'name' => 'Other Salon',
            'slug' => 'other-salon',
            'status' => 'active',
            'subscription_plan_id' => $plan->id,
        ]);

        TenantSubscription::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'subscription_plan_id' => $plan->id,
            'status' => TenantSubscription::STATUS_ACTIVE,
            'billing_interval' => 'monthly',
        ]);

        $location = Location::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'HQ',
            'slug' => 'hq',
            'timezone' => 'Europe/London',
            'is_active' => true,
        ]);

        $workspace = Workspace::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'name' => 'Chair A',
            'workspace_type' => Workspace::TYPE_CHAIR,
            'is_active' => true,
        ]);

        foreach ($permissionIds as $perm) {
            Permission::query()->updateOrCreate(
                ['id' => $perm],
                [
                    'name' => $perm,
                    'slug' => $perm,
                    'module' => explode('.', $perm)[0],
                ],
            );
        }

        $role = Role::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Owner',
            'slug' => 'owner',
            'is_system' => true,
            'is_active' => true,
        ]);
        $role->permissions()->sync($permissionIds);

        $viewOnlyRole = Role::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Viewer',
            'slug' => 'viewer',
            'is_system' => false,
            'is_active' => true,
        ]);
        $viewOnlyRole->permissions()->sync(['identity.view']);

        $user = User::factory()->create([
            'email' => 'owner@test.local',
            'password' => Hash::make('password'),
        ]);

        $teamMember = TeamMember::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Owner',
            'employment_type' => TeamMember::EMPLOYMENT_OWNER,
            'display_name' => 'Test Owner',
            'primary_location_id' => $location->id,
            'is_active' => true,
        ]);
        $teamMember->roles()->attach($role->id);
        $teamMember->workspaces()->attach($workspace->id);

        $viewer = User::factory()->create([
            'email' => 'viewer@test.local',
            'password' => Hash::make('password'),
        ]);

        $viewerMember = TeamMember::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $viewer->id,
            'employment_type' => TeamMember::EMPLOYMENT_EMPLOYEE,
            'display_name' => 'Viewer',
            'is_active' => true,
        ]);
        $viewerMember->roles()->attach($viewOnlyRole->id);

        $token = $user->createToken('owner-test')->plainTextToken;
        $viewerToken = $viewer->createToken('viewer-test')->plainTextToken;

        return compact(
            'tenant',
            'otherTenant',
            'location',
            'workspace',
            'user',
            'teamMember',
            'viewer',
            'viewerMember',
            'token',
            'viewerToken',
            'plan',
        );
    }

    protected function withTenantAuth(string $token, string $slug = 'test-salon'): static
    {
        Auth::forgetGuards();

        return $this
            ->flushHeaders()
            ->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Tenant-Slug', $slug);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function membershipJoinPayload(array $overrides = []): array
    {
        return array_merge([
            'preferred_name' => 'Pat',
            'whatsapp_number' => '+447700900111',
            'email' => 'pat@example.test',
            'next_visit_date' => now()->addDays(7)->toDateString(),
            'accept_terms' => true,
        ], $overrides);
    }

    protected function memberLoginViaOtp(string $slug, string $email, string $phone): string
    {
        $request = $this->withHeaders(['X-Tenant-Slug' => $slug])
            ->postJson('/api/v1/member/login/request-otp', [
                'email' => $email,
                'phone' => $phone,
            ]);
        $request->assertOk();
        $otp = $request->json('data.otp');
        $this->assertNotEmpty($otp);

        $login = $this->withHeaders(['X-Tenant-Slug' => $slug])
            ->postJson('/api/v1/member/login', [
                'email' => $email,
                'phone' => $phone,
                'otp' => $otp,
            ]);
        $login->assertOk();

        return (string) $login->json('data.token');
    }
}
