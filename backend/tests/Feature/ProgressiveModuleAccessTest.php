<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\AppointmentServiceLine;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\PlatformUpgradeSend;
use App\Domains\Identity\Models\SubscriptionPlan;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\TenantSubscription;
use App\Domains\Identity\Services\ProgressiveModuleAccessService;
use App\Domains\Identity\Services\TenantEntitlementService;
use App\Domains\Identity\Support\ProgressiveModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class ProgressiveModuleAccessTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        foreach ([
            ['slug' => 'basic', 'name' => 'Basic', 'price' => 4900],
            ['slug' => 'pro', 'name' => 'Pro', 'price' => 12900],
            ['slug' => 'diamond', 'name' => 'Diamond', 'price' => 29900],
        ] as $plan) {
            SubscriptionPlan::query()->firstOrCreate(
                ['slug' => $plan['slug']],
                [
                    'name' => $plan['name'],
                    'billing_interval' => 'monthly',
                    'display_price_cents' => $plan['price'],
                    'features' => [],
                    'limits' => ['max_locations' => 1, 'max_staff' => 5, 'max_workspaces' => 10],
                    'is_active' => true,
                ],
            );
        }
    }

    public function test_trial_soft_unlocks_pos_within_30_days(): void
    {
        $ctx = $this->seedTrialTenant();
        $entitlements = app(TenantEntitlementService::class);

        $this->assertTrue($entitlements->isEnabled($ctx['tenant'], 'pos'));
        $this->assertTrue($entitlements->isEnabled($ctx['tenant'], 'inventory'));
        $this->assertTrue($entitlements->isEnabled($ctx['tenant'], 'memberships'));
        $this->assertTrue($entitlements->isEnabled($ctx['tenant'], 'marketing'));
        $this->assertTrue($entitlements->isEnabled($ctx['tenant'], 'notifications'));
    }

    public function test_trial_gates_pos_after_30_days(): void
    {
        $ctx = $this->seedTrialTenant();
        $ctx['tenant']->forceFill(['activated_at' => now()->subDays(31)])->save();

        $entitlements = app(TenantEntitlementService::class);
        $this->assertFalse($entitlements->isEnabled($ctx['tenant']->fresh(), 'pos'));
    }

    public function test_crm_unlocked_until_30_contacts_then_gated(): void
    {
        $ctx = $this->seedTrialTenant();
        $tenant = $ctx['tenant'];
        $entitlements = app(TenantEntitlementService::class);

        $this->assertTrue($entitlements->isEnabled($tenant, 'crm'));

        for ($i = 0; $i < ProgressiveModuleAccess::CRM_GATE_CONTACTS; $i++) {
            Client::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'first_name' => 'Client',
                'last_name' => (string) $i,
                'display_name' => 'Client '.$i,
                'is_active' => true,
            ]);
        }

        $this->assertFalse($entitlements->isEnabled($tenant->fresh(), 'crm'));
    }

    public function test_day21_email_sent_at_20_contacts(): void
    {
        $ctx = $this->seedTrialTenant();
        $tenant = $ctx['tenant'];
        $progressive = app(ProgressiveModuleAccessService::class);

        for ($i = 0; $i < ProgressiveModuleAccess::CRM_NUDGE_CONTACTS; $i++) {
            Client::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'first_name' => 'Client',
                'last_name' => (string) $i,
                'display_name' => 'Client '.$i,
                'is_active' => true,
            ]);
        }

        $progressive->maybeNudgeAfterClientCreated($tenant->fresh('subscriptionPlan'));

        $this->assertDatabaseHas('platform_upgrade_sends', [
            'tenant_id' => $tenant->id,
            'step' => ProgressiveModuleAccess::TRIGGER_CONTACTS_20,
            'channel' => 'email',
        ]);
    }

    public function test_booking_gates_after_500_pound_appointment_and_sends_email(): void
    {
        $ctx = $this->seedTrialTenant();
        $tenant = $ctx['tenant'];
        $entitlements = app(TenantEntitlementService::class);
        $progressive = app(ProgressiveModuleAccessService::class);

        $this->assertTrue($entitlements->isEnabled($tenant, 'booking'));

        $appointment = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $ctx['location']->id,
            'client_id' => Client::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'first_name' => 'Vip',
                'last_name' => 'Client',
                'display_name' => 'Vip Client',
                'is_active' => true,
            ])->id,
            'team_member_id' => $ctx['teamMember']->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => Appointment::STATUS_CONFIRMED,
            'booking_source' => Appointment::SOURCE_ADMIN,
        ]);

        AppointmentServiceLine::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'appointment_id' => $appointment->id,
            'booking_service_id' => null,
            'service_name' => 'Colour package',
            'duration_minutes' => 120,
            'price_cents' => ProgressiveModuleAccess::BOOKING_GATE_CENTS,
            'sort_order' => 0,
        ]);

        $progressive->maybeNudgeAfterAppointmentCreated($tenant->fresh('subscriptionPlan'), $appointment->fresh());

        $this->assertTrue($entitlements->isEnabled($tenant->fresh(), 'booking'));
        $this->assertFalse(
            (bool) ($entitlements->resolveFeatures($tenant->fresh())['booking_board'] ?? true),
        );
        $this->assertTrue(
            PlatformUpgradeSend::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('step', ProgressiveModuleAccess::TRIGGER_BOOKING_500)
                ->exists(),
        );
    }

    public function test_paid_tenant_keeps_plan_crm_after_30_contacts(): void
    {
        $ctx = $this->seedTenantContext(['crm.view']);
        $basic = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();
        $ctx['tenant']->forceFill([
            'subscription_plan_id' => $basic->id,
            'activated_at' => now()->subDays(5),
        ])->save();
        TenantSubscription::withoutGlobalScopes()
            ->where('tenant_id', $ctx['tenant']->id)
            ->update([
                'subscription_plan_id' => $basic->id,
                'status' => TenantSubscription::STATUS_ACTIVE,
                'trial_ends_at' => null,
            ]);

        for ($i = 0; $i < ProgressiveModuleAccess::CRM_GATE_CONTACTS; $i++) {
            Client::withoutGlobalScopes()->create([
                'tenant_id' => $ctx['tenant']->id,
                'first_name' => 'Client',
                'last_name' => (string) $i,
                'display_name' => 'Client '.$i,
                'is_active' => true,
            ]);
        }

        $this->assertTrue(app(TenantEntitlementService::class)->isEnabled($ctx['tenant']->fresh(), 'crm'));
    }

    public function test_shell_exposes_soft_unlocked_modules_for_trial(): void
    {
        $ctx = $this->seedTrialTenant(['pos.view', 'identity.view']);

        $this->withTenantAuth($ctx['token'], $ctx['tenant']->slug)
            ->getJson('/api/v1/shell')
            ->assertOk()
            ->assertJsonPath('data.features.pos', true)
            ->assertJsonPath('data.features.crm', true)
            ->assertJsonPath('data.features.booking', true);
    }

    /**
     * @param  list<string>  $perms
     * @return array<string, mixed>
     */
    private function seedTrialTenant(array $perms = ['crm.view', 'booking.view', 'pos.view', 'identity.view']): array
    {
        $ctx = $this->seedTenantContext($perms);
        $basic = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();
        $basic->forceFill([
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
        ])->save();

        /** @var Tenant $tenant */
        $tenant = $ctx['tenant'];
        $tenant->forceFill([
            'subscription_plan_id' => $basic->id,
            'activated_at' => now()->subDays(2),
            'status' => 'active',
        ])->save();

        TenantSubscription::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->update([
                'subscription_plan_id' => $basic->id,
                'status' => TenantSubscription::STATUS_TRIAL,
                'trial_ends_at' => now()->addDays(28),
            ]);

        $ctx['tenant'] = $tenant->fresh('subscriptionPlan');

        return $ctx;
    }
}
