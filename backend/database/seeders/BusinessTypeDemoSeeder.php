<?php

namespace Database\Seeders;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\AppointmentServiceLine;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\SubscriptionPlan;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\TenantSubscription;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\Workspace;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Domains\Staff\Models\StaffProfile;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds four separate demo tenants for business-type testing.
 * Logins: owner@{slug}.neatmeet.local / password
 */
class BusinessTypeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $starter = SubscriptionPlan::query()->where('slug', 'starter')->first()
            ?? SubscriptionPlan::query()->first();
        $growth = SubscriptionPlan::query()->where('slug', 'growth')->first() ?? $starter;

        if ($starter === null) {
            return;
        }

        $this->seedSoloStylist($starter);
        $this->seedChairRenterHub($growth);
        $this->seedBoutiqueSalon($growth);
        $this->seedBarberShop($starter);
    }

    private function ownerPermissionIds(): array
    {
        return collect(PermissionCatalogueSeeder::catalogue())->pluck('id')->all();
    }

    /**
     * @param  array{
     *     name: string,
     *     slug: string,
     *     business_type: string,
     *     brand: string,
     *     plan: SubscriptionPlan,
     *     location_name: string,
     *     location_slug: string,
     *     workspace_name: string,
     *     workspace_type: string,
     *     owner_first: string,
     *     owner_last: string,
     *     services: list<array{name: string, category: string, duration: int, price: int, description?: string}>,
     *     walk_ins?: bool,
     *     extra_staff?: list<array{email: string, first: string, last: string, employment: string, display: string, walk_ins?: bool}>,
     *     second_location?: bool
     * }  $cfg
     */
    private function seedTenant(array $cfg): void
    {
        if (Tenant::query()->where('slug', $cfg['slug'])->exists()) {
            return;
        }

        $tenant = Tenant::query()->create([
            'name' => $cfg['name'],
            'trading_name' => $cfg['name'],
            'slug' => $cfg['slug'],
            'status' => 'active',
            'business_type' => $cfg['business_type'],
            'timezone' => 'Europe/London',
            'contact_email' => "hello@{$cfg['slug']}.neatmeet.local",
            'subscription_plan_id' => $cfg['plan']->id,
            'settings' => [
                'branding' => [
                    'brand_display_name' => $cfg['brand'],
                    'primary_color' => '#18181b',
                    'secondary_color' => '#fafafa',
                    'support_email' => "hello@{$cfg['slug']}.neatmeet.local",
                ],
            ],
        ]);

        TenantSubscription::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'subscription_plan_id' => $cfg['plan']->id,
            'status' => TenantSubscription::STATUS_TRIAL,
            'billing_interval' => SubscriptionPlan::INTERVAL_MONTHLY,
            'trial_ends_at' => now()->addDays(14),
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $hours = [
            ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '18:00', 'is_closed' => false],
            ['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '18:00', 'is_closed' => false],
            ['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '18:00', 'is_closed' => false],
            ['day_of_week' => 4, 'start_time' => '09:00', 'end_time' => '18:00', 'is_closed' => false],
            ['day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '18:00', 'is_closed' => false],
            ['day_of_week' => 6, 'start_time' => '09:00', 'end_time' => '16:00', 'is_closed' => false],
            ['day_of_week' => 7, 'start_time' => null, 'end_time' => null, 'is_closed' => true],
        ];

        $location = Location::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => $cfg['location_name'],
            'slug' => $cfg['location_slug'],
            'timezone' => 'Europe/London',
            'address' => [
                'line1' => '1 Demo Road',
                'city' => 'London',
                'postcode' => 'E1 1AA',
                'country' => 'GB',
            ],
            'opening_hours' => $hours,
            'is_active' => true,
        ]);

        if (! empty($cfg['second_location'])) {
            Location::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'name' => 'Second Street',
                'slug' => 'second-street',
                'timezone' => 'Europe/London',
                'address' => [
                    'line1' => '22 Second Street',
                    'city' => 'London',
                    'postcode' => 'N1 2BB',
                    'country' => 'GB',
                ],
                'opening_hours' => $hours,
                'is_active' => true,
            ]);
        }

        $workspace = Workspace::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'name' => $cfg['workspace_name'],
            'code' => strtoupper(substr($cfg['slug'], 0, 3)).'1',
            'workspace_type' => $cfg['workspace_type'],
            'is_active' => true,
        ]);

        $role = Role::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Owner',
            'slug' => 'owner',
            'is_system' => true,
            'is_active' => true,
        ]);
        $role->permissions()->sync($this->ownerPermissionIds());

        $user = User::factory()->create([
            'name' => $cfg['owner_first'].' '.$cfg['owner_last'],
            'email' => "owner@{$cfg['slug']}.neatmeet.local",
            'password' => Hash::make('password'),
        ]);

        $owner = TeamMember::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'first_name' => $cfg['owner_first'],
            'last_name' => $cfg['owner_last'],
            'employment_type' => TeamMember::EMPLOYMENT_OWNER,
            'display_name' => $cfg['owner_first'].' '.$cfg['owner_last'],
            'primary_location_id' => $location->id,
            'is_active' => true,
        ]);
        $owner->roles()->attach($role->id);
        $owner->workspaces()->attach($workspace->id);

        StaffProfile::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'team_member_id' => $owner->id,
            'is_bookable' => true,
            'show_in_online_booking' => true,
            'accepts_walk_ins' => (bool) ($cfg['walk_ins'] ?? false),
            'booking_display_name' => $cfg['owner_first'],
            'default_workspace_id' => $workspace->id,
        ]);
        $owner->operatingLocations()->attach($location->id);

        foreach ([1, 2, 3, 4, 5, 6] as $day) {
            StaffAvailabilityRule::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'team_member_id' => $owner->id,
                'location_id' => $location->id,
                'workspace_id' => $workspace->id,
                'day_of_week' => $day,
                'start_time' => '09:00',
                'end_time' => $day === 6 ? '16:00' : '18:00',
                'is_active' => true,
            ]);
        }

        $primaryService = null;
        foreach ($cfg['services'] as $index => $serviceRow) {
            $base = (int) $serviceRow['price'];
            $service = BookableService::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'name' => $serviceRow['name'],
                'category' => $serviceRow['category'],
                'description' => $serviceRow['description'] ?? null,
                'duration_minutes' => $serviceRow['duration'],
                'base_price_cents' => $base,
                'membership_price_cents' => $serviceRow['membership_price'] ?? (int) round($base * 0.85),
                'loyalty_price_cents' => $serviceRow['loyalty_price'] ?? (int) round($base * 0.9),
                'is_active' => true,
                'is_bookable_online' => true,
                'display_order' => $index + 1,
            ]);
            $primaryService ??= $service;
        }

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Casey',
            'last_name' => 'Client',
            'email' => "casey@{$cfg['slug']}.example.com",
            'phone' => '+44000000099',
            'primary_location_id' => $location->id,
            'is_active' => true,
        ]);

        if ($primaryService !== null) {
            $starts = Carbon::now()->next(Carbon::TUESDAY)->setTime(11, 0);
            $appointment = Appointment::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $location->id,
                'client_id' => $client->id,
                'team_member_id' => $owner->id,
                'workspace_id' => $workspace->id,
                'starts_at' => $starts,
                'ends_at' => $starts->copy()->addMinutes($primaryService->duration_minutes),
                'status' => Appointment::STATUS_CONFIRMED,
                'booking_source' => Appointment::SOURCE_ADMIN,
                'created_by_team_member_id' => $owner->id,
                'booking_reference' => 'NM-'.strtoupper(substr($cfg['slug'], 0, 4)).'0001',
                'deposit_status' => Appointment::DEPOSIT_NOT_REQUIRED,
            ]);

            AppointmentServiceLine::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'appointment_id' => $appointment->id,
                'booking_service_id' => $primaryService->id,
                'service_name' => $primaryService->name,
                'duration_minutes' => $primaryService->duration_minutes,
                'price_cents' => $primaryService->base_price_cents,
                'sort_order' => 0,
            ]);
        }

        foreach ($cfg['extra_staff'] ?? [] as $staffRow) {
            $this->seedExtraStaff($tenant, $location, $workspace, $role, $staffRow);
        }

        (new EcommerceDemoSeeder)->run($tenant, $location, $owner);
    }

    /**
     * @param  array{email: string, first: string, last: string, employment: string, display: string, walk_ins?: bool}  $staffRow
     */
    private function seedExtraStaff(
        Tenant $tenant,
        Location $location,
        Workspace $workspace,
        Role $ownerRole,
        array $staffRow,
    ): void {
        $chair = $workspace;
        if ($staffRow['employment'] === TeamMember::EMPLOYMENT_CHAIR_RENTER) {
            $chair = Workspace::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $location->id,
                'name' => $staffRow['display'].' Chair',
                'code' => 'CR1',
                'workspace_type' => Workspace::TYPE_CHAIR,
                'is_active' => true,
            ]);
        }

        $user = User::factory()->create([
            'name' => $staffRow['first'].' '.$staffRow['last'],
            'email' => $staffRow['email'],
            'password' => Hash::make('password'),
        ]);

        $member = TeamMember::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'first_name' => $staffRow['first'],
            'last_name' => $staffRow['last'],
            'employment_type' => $staffRow['employment'],
            'display_name' => $staffRow['display'],
            'primary_location_id' => $location->id,
            'is_active' => true,
        ]);
        $member->workspaces()->attach($chair->id);
        // Chair renters get a scoped role copy of view+manage booking/crm only via owner role for demo simplicity.
        $member->roles()->attach($ownerRole->id);

        StaffProfile::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'team_member_id' => $member->id,
            'is_bookable' => true,
            'show_in_online_booking' => true,
            'accepts_walk_ins' => (bool) ($staffRow['walk_ins'] ?? false),
            'booking_display_name' => $staffRow['display'],
            'default_workspace_id' => $chair->id,
        ]);
        $member->operatingLocations()->attach($location->id);

        foreach ([1, 2, 3, 4, 5] as $day) {
            StaffAvailabilityRule::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'team_member_id' => $member->id,
                'location_id' => $location->id,
                'workspace_id' => $chair->id,
                'day_of_week' => $day,
                'start_time' => '10:00',
                'end_time' => '17:00',
                'is_active' => true,
            ]);
        }
    }

    private function seedSoloStylist(SubscriptionPlan $plan): void
    {
        $this->seedTenant([
            'name' => 'Solo Stylist Studio',
            'slug' => 'solo-stylist',
            'business_type' => 'solo',
            'brand' => 'Solo Stylist',
            'plan' => $plan,
            'location_name' => 'Home Studio',
            'location_slug' => 'home-studio',
            'workspace_name' => 'My Chair',
            'workspace_type' => Workspace::TYPE_CHAIR,
            'owner_first' => 'Ava',
            'owner_last' => 'Solo',
            'walk_ins' => false,
            'services' => [
                [
                    'name' => 'Signature Cut',
                    'category' => 'hair',
                    'duration' => 45,
                    'price' => 5500,
                    'description' => 'Personal cut for one client at a time.',
                ],
                [
                    'name' => 'Blow Dry',
                    'category' => 'hair',
                    'duration' => 30,
                    'price' => 3500,
                ],
            ],
        ]);
    }

    private function seedChairRenterHub(SubscriptionPlan $plan): void
    {
        $this->seedTenant([
            'name' => 'Chair Renter Hub',
            'slug' => 'chair-renter-hub',
            'business_type' => 'chair_renter',
            'brand' => 'Chair Hub',
            'plan' => $plan,
            'location_name' => 'Shared Floor',
            'location_slug' => 'shared-floor',
            'workspace_name' => 'Hub Desk',
            'workspace_type' => Workspace::TYPE_STATION,
            'owner_first' => 'Morgan',
            'owner_last' => 'Hub',
            'walk_ins' => false,
            'services' => [
                [
                    'name' => 'Renter Cut',
                    'category' => 'hair',
                    'duration' => 40,
                    'price' => 4000,
                ],
                [
                    'name' => 'Renter Colour',
                    'category' => 'colour',
                    'duration' => 90,
                    'price' => 8500,
                ],
            ],
            'extra_staff' => [
                [
                    'email' => 'renter@chair-renter-hub.neatmeet.local',
                    'first' => 'Riley',
                    'last' => 'Renter',
                    'employment' => TeamMember::EMPLOYMENT_CHAIR_RENTER,
                    'display' => 'Riley Renter',
                    'walk_ins' => false,
                ],
            ],
        ]);
    }

    private function seedBoutiqueSalon(SubscriptionPlan $plan): void
    {
        $this->seedTenant([
            'name' => 'Boutique Salon Collective',
            'slug' => 'boutique-salon',
            'business_type' => 'boutique',
            'brand' => 'Boutique Salon',
            'plan' => $plan,
            'location_name' => 'High Street',
            'location_slug' => 'high-street',
            'workspace_name' => 'Chair A',
            'workspace_type' => Workspace::TYPE_CHAIR,
            'owner_first' => 'Blake',
            'owner_last' => 'Boutique',
            'walk_ins' => false,
            'second_location' => true,
            'services' => [
                [
                    'name' => 'Cut & Style',
                    'category' => 'hair',
                    'duration' => 60,
                    'price' => 6500,
                ],
                [
                    'name' => 'Balayage',
                    'category' => 'colour',
                    'duration' => 150,
                    'price' => 14000,
                ],
                [
                    'name' => 'Express Manicure',
                    'category' => 'nails',
                    'duration' => 30,
                    'price' => 2800,
                ],
            ],
            'extra_staff' => [
                [
                    'email' => 'stylist@boutique-salon.neatmeet.local',
                    'first' => 'Sam',
                    'last' => 'Stylist',
                    'employment' => TeamMember::EMPLOYMENT_EMPLOYEE,
                    'display' => 'Sam Stylist',
                    'walk_ins' => false,
                ],
            ],
        ]);
    }

    private function seedBarberShop(SubscriptionPlan $plan): void
    {
        $this->seedTenant([
            'name' => 'Neighbourhood Barber',
            'slug' => 'barber-shop',
            'business_type' => 'barber',
            'brand' => 'Barber Shop',
            'plan' => $plan,
            'location_name' => 'Corner Chair',
            'location_slug' => 'corner-chair',
            'workspace_name' => 'Barber Chair 1',
            'workspace_type' => Workspace::TYPE_CHAIR,
            'owner_first' => 'Jordan',
            'owner_last' => 'Barber',
            'walk_ins' => true,
            'services' => [
                [
                    'name' => 'Skin Fade',
                    'category' => 'barber',
                    'duration' => 30,
                    'price' => 2500,
                    'description' => 'Fast turnover fade — walk-ins welcome.',
                ],
                [
                    'name' => 'Beard Trim',
                    'category' => 'barber',
                    'duration' => 15,
                    'price' => 1200,
                    'description' => 'Shape and tidy — scissors and clipper finish.',
                ],
                [
                    'name' => 'Cut & Beard',
                    'category' => 'barber',
                    'duration' => 40,
                    'price' => 3200,
                    'description' => 'Full cut plus beard tidy in one visit.',
                ],
            ],
            'extra_staff' => [
                [
                    'email' => 'barber@barber-shop.neatmeet.local',
                    'first' => 'Chris',
                    'last' => 'Clipper',
                    'employment' => TeamMember::EMPLOYMENT_EMPLOYEE,
                    'display' => 'Chris',
                    'walk_ins' => true,
                ],
            ],
        ]);
    }
}
