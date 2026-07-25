<?php

namespace Database\Seeders;

use App\Domains\Identity\Models\AuditLog;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\SubscriptionPlan;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\TenantSubscription;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\Workspace;
use App\Domains\Identity\Services\SignupFormDefinitionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionCatalogueSeeder::class);

        app(SignupFormDefinitionService::class)->ensureDefaultActive();

        $basicPlan = SubscriptionPlan::query()->firstOrCreate(
            ['slug' => 'basic'],
            [
                'name' => 'Basic',
                'description' => 'Essentials for a single salon — booking, CRM capture, and day-to-day ops.',
                'billing_interval' => SubscriptionPlan::INTERVAL_MONTHLY,
                'features' => [
                    'booking' => true,
                    'crm' => true,
                    'payments' => true,
                    'pos' => false,
                    'inventory' => false,
                    'memberships' => false,
                    'marketing' => false,
                    'notifications' => false,
                    'analytics' => false,
                    'integrations' => false,
                    'ecommerce' => false,
                ],
                'limits' => ['max_locations' => 1, 'max_staff' => 5, 'max_workspaces' => 10],
                'display_price_cents' => 4900,
                'is_active' => true,
            ],
        );

        SubscriptionPlan::query()->firstOrCreate(
            ['slug' => 'pro'],
            [
                'name' => 'Pro',
                'description' => 'Growing teams — POS, inventory, memberships, and multi-chair workflows.',
                'billing_interval' => SubscriptionPlan::INTERVAL_MONTHLY,
                'features' => [
                    'booking' => true,
                    'crm' => true,
                    'payments' => true,
                    'pos' => true,
                    'inventory' => true,
                    'memberships' => true,
                    'marketing' => false,
                    'notifications' => true,
                    'analytics' => true,
                    'integrations' => false,
                    'ecommerce' => false,
                ],
                'limits' => ['max_locations' => 5, 'max_staff' => 25, 'max_workspaces' => 50],
                'display_price_cents' => 12900,
                'is_active' => true,
            ],
        );

        SubscriptionPlan::query()->firstOrCreate(
            ['slug' => 'diamond'],
            [
                'name' => 'Diamond',
                'description' => 'Multi-location brands — full suite, advanced analytics, and priority platform support.',
                'billing_interval' => SubscriptionPlan::INTERVAL_MONTHLY,
                'features' => [
                    'booking' => true,
                    'crm' => true,
                    'payments' => true,
                    'pos' => true,
                    'inventory' => true,
                    'memberships' => true,
                    'marketing' => true,
                    'notifications' => true,
                    'analytics' => true,
                    'integrations' => true,
                    'ecommerce' => true,
                ],
                'limits' => ['max_locations' => 25, 'max_staff' => 200, 'max_workspaces' => 500],
                'display_price_cents' => 29900,
                'is_active' => true,
            ],
        );

        $tenant = Tenant::query()->create([
            'name' => 'Demo Salon',
            'trading_name' => 'Demo Salon Ltd',
            'slug' => 'demo-salon',
            'status' => 'active',
            'activated_at' => now(),
            'business_type' => 'boutique',
            'timezone' => 'Europe/London',
            'contact_email' => 'hello@demo.neatmeet.local',
            'subscription_plan_id' => $basicPlan->id,
            'settings' => [
                'branding' => [
                    'brand_display_name' => 'Demo Salon',
                    'primary_color' => '#18181b',
                    'secondary_color' => '#fafafa',
                    'support_email' => 'hello@demo.neatmeet.local',
                ],
            ],
        ]);

        TenantSubscription::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'subscription_plan_id' => $basicPlan->id,
            'desired_plan_slug' => 'basic',
            'tier_unlocked' => false,
            'status' => TenantSubscription::STATUS_TRIAL,
            'billing_interval' => SubscriptionPlan::INTERVAL_MONTHLY,
            'trial_ends_at' => now()->addDays(30),
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $location = Location::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Street',
            'slug' => 'main-street',
            'timezone' => 'Europe/London',
            'latitude' => 51.5155,
            'longitude' => -0.0722,
            'geofence_radius_meters' => 150,
            'address' => [
                'line1' => '10 Main Street',
                'city' => 'London',
                'postcode' => 'E1 1AA',
                'country' => 'GB',
            ],
            'contact_phone' => '+44000000000',
            'opening_hours' => [
                ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '18:00', 'is_closed' => false],
                ['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '18:00', 'is_closed' => false],
                ['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '18:00', 'is_closed' => false],
                ['day_of_week' => 4, 'start_time' => '09:00', 'end_time' => '18:00', 'is_closed' => false],
                ['day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '18:00', 'is_closed' => false],
                ['day_of_week' => 6, 'start_time' => '10:00', 'end_time' => '16:00', 'is_closed' => false],
                ['day_of_week' => 7, 'start_time' => null, 'end_time' => null, 'is_closed' => true],
            ],
            'is_active' => true,
        ]);

        $workspace = Workspace::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'name' => 'Chair 1',
            'code' => 'C1',
            'workspace_type' => Workspace::TYPE_CHAIR,
            'is_active' => true,
        ]);

        $ownerPermissions = [
            'identity.view',
            'identity.manage',
            'identity.access.manage',
            'booking.view',
            'crm.view',
            'crm.manage',
            'staff.view',
            'staff.manage',
            'booking.view',
            'booking.manage',
            'payments.view',
            'payments.manage',
            'payments.refund',
            'payments.reporting.view',
            'inventory.view',
            'inventory.manage',
            'inventory.adjust',
            'inventory.reporting.view',
            'pos.view',
            'pos.manage',
            'pos.checkout.complete',
            'pos.refund',
            'pos.checkout.reopen',
            'pos.receipt.manage',
            'memberships.view',
            'memberships.manage',
            'memberships.reporting.view',
            'marketing.view',
            'marketing.manage',
            'marketing.dispatch',
            'marketing.reporting.view',
            'notifications.view',
            'notifications.manage',
            'notifications.reporting.view',
            'analytics.view',
            'analytics.reporting.view',
            'analytics.exports.manage',
            'integrations.view',
            'integrations.manage',
            'integrations.reporting.view',
            'ecommerce.view',
            'ecommerce.manage',
            'gallery.view',
            'gallery.manage',
            'lookbook.view',
            'lookbook.manage',
            'next_visit.view',
            'next_visit.manage',
        ];

        $ownerRole = Role::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Owner',
            'slug' => 'owner',
            'is_system' => true,
            'is_active' => true,
        ]);
        $ownerRole->permissions()->sync($ownerPermissions);

        $managerRole = Role::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Manager',
            'slug' => 'manager',
            'is_system' => false,
            'is_active' => true,
        ]);
        $managerRole->permissions()->sync([
            'identity.view',
            'identity.manage',
            'booking.view',
            'booking.manage',
            'crm.view',
            'payments.view',
            'payments.reporting.view',
            'inventory.view',
            'inventory.reporting.view',
            'memberships.view',
            'memberships.reporting.view',
            'marketing.view',
            'marketing.reporting.view',
            'notifications.view',
            'notifications.reporting.view',
            'analytics.view',
            'analytics.reporting.view',
            'analytics.exports.manage',
            'integrations.view',
            'integrations.reporting.view',
        ]);

        User::query()->updateOrCreate(
            ['email' => 'platform@neatmeet.local'],
            [
                'name' => 'Platform Admin',
                'password' => Hash::make('password'),
                'is_platform_admin' => true,
                'platform_role' => 'owner',
            ],
        );

        $user = User::factory()->create([
            'name' => 'Demo Owner',
            'email' => 'owner@demo.neatmeet.local',
            'password' => Hash::make('password'),
        ]);

        $teamMember = TeamMember::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'first_name' => 'Demo',
            'last_name' => 'Owner',
            'employment_type' => TeamMember::EMPLOYMENT_OWNER,
            'display_name' => 'Demo Owner',
            'primary_location_id' => $location->id,
            'is_active' => true,
        ]);

        $teamMember->roles()->attach($ownerRole->id);
        $teamMember->workspaces()->attach($workspace->id);

        AuditLog::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'actor_type' => User::class,
            'actor_id' => (string) $user->id,
            'action' => 'organization.updated',
            'entity_type' => Tenant::class,
            'entity_id' => $tenant->id,
            'new_values' => ['name' => 'Demo Salon'],
            'created_at' => now()->subDay(),
        ]);

        (new CrmDemoSeeder)->run($tenant, $location, $teamMember, $user);
        (new StaffDemoSeeder)->run($tenant, $location, $workspace, $teamMember);
        (new BookingDemoSeeder)->run($tenant, $location, $workspace, $teamMember);
        (new PaymentsDemoSeeder)->run($tenant, $location, $teamMember);
        (new InventoryDemoSeeder)->run($tenant, $location, $teamMember);
        (new PosDemoSeeder)->run($tenant, $location, $teamMember);
        (new MembershipsDemoSeeder)->run($tenant, $location, $teamMember);
        (new MarketingDemoSeeder)->run($tenant, $location, $teamMember);
        (new NotificationsDemoSeeder)->run($tenant, $location, $teamMember);
        (new IntegrationsDemoSeeder)->run($tenant, $teamMember);
        (new EcommerceDemoSeeder)->run($tenant, $location, $teamMember);
        (new SalonReviewsDemoSeeder)->run();
        (new BusinessTypeDemoSeeder)->run();
    }
}
