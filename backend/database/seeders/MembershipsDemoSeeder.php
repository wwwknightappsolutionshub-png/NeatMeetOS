<?php

namespace Database\Seeders;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\AppointmentServiceLine;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Memberships\Enums\ClientMembershipStatus;
use App\Domains\Memberships\Enums\ClientPackageSource;
use App\Domains\Memberships\Enums\MembershipBillingFrequency;
use App\Domains\Memberships\Enums\MembershipPlanStatus;
use App\Domains\Memberships\Services\ClientMembershipService;
use App\Domains\Memberships\Services\ClientPackageService;
use App\Domains\Memberships\Services\LoyaltyLedgerService;
use App\Domains\Memberships\Services\LoyaltyRedemptionSettingsService;
use App\Domains\Memberships\Services\MembershipPlanService;
use App\Domains\Memberships\Services\PackageEntitlementService;
use App\Domains\Memberships\Services\PackageProductService;
use App\Domains\Memberships\Services\WalletLedgerService;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class MembershipsDemoSeeder extends Seeder
{
    public function run(Tenant $tenant, Location $location, TeamMember $ownerMember): void
    {
        app(TenantContext::class)->set($tenant);

        $alex = Client::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('first_name', 'Alex')
            ->where('last_name', 'Taylor')
            ->first();

        $jordan = Client::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('first_name', 'Jordan')
            ->first();

        $blowDryService = BookableService::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Cut & Blow Dry')
            ->first();

        $colourService = BookableService::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Full Colour')
            ->first();

        if ($alex === null || $blowDryService === null) {
            return;
        }

        $planService = app(MembershipPlanService::class);
        $packageService = app(PackageProductService::class);
        $membershipService = app(ClientMembershipService::class);
        $clientPackageService = app(ClientPackageService::class);
        $walletService = app(WalletLedgerService::class);

        $blowDryClub = $planService->create([
            'name' => 'Blow Dry Club',
            'description' => 'Monthly membership with salon wallet credit — members pay member rates on listed services.',
            'billing_frequency' => MembershipBillingFrequency::MONTHLY,
            'price_cents' => 6500,
            'included_wallet_credit_cents' => 1000,
            'is_public' => true,
        ]);

        $planService->create([
            'name' => 'Colour Care Membership',
            'description' => 'Monthly colour care with loyalty bonus and member pricing.',
            'billing_frequency' => MembershipBillingFrequency::MONTHLY,
            'price_cents' => 12000,
            'included_loyalty_points' => 100,
            'is_public' => true,
        ]);

        $blowDryPack = $packageService->create([
            'name' => '6 Blow Dries Pack',
            'description' => 'Prepaid blow dry sessions — great value if you visit often.',
            'price_cents' => 18000,
            'included_quantity' => 6,
            'is_public' => true,
            'service_restrictions' => [
                ['booking_service_id' => $blowDryService->id, 'quantity_per_redemption' => 1],
            ],
        ]);

        if ($colourService !== null) {
            $packageService->create([
                'name' => 'Colour Refresh Bundle',
                'description' => 'Three full colour sessions at a package rate.',
                'price_cents' => 25000,
                'included_quantity' => 3,
                'is_public' => true,
                'service_restrictions' => [
                    ['booking_service_id' => $colourService->id, 'quantity_per_redemption' => 1],
                ],
            ]);
        }

        $membershipService->assign([
            'client_id' => $alex->id,
            'membership_plan_id' => $blowDryClub->id,
            'status' => ClientMembershipStatus::ACTIVE,
        ], $ownerMember->id);

        $walletService->postManualCredit([
            'client_id' => $alex->id,
            'amount_cents' => 500,
            'notes' => 'Goodwill credit — demo adjustment',
        ], $ownerMember->id);

        if ($jordan !== null) {
            $colourPlan = \App\Domains\Memberships\Models\MembershipPlan::query()
                ->where('name', 'Colour Care Membership')
                ->first();

            if ($colourPlan !== null) {
                $membershipService->assign([
                    'client_id' => $jordan->id,
                    'membership_plan_id' => $colourPlan->id,
                    'status' => ClientMembershipStatus::ACTIVE,
                ], $ownerMember->id);
            }
        }

        $clientPackage = $clientPackageService->assign([
            'client_id' => $alex->id,
            'package_product_id' => $blowDryPack->id,
            'source' => ClientPackageSource::MANUAL,
        ], $ownerMember->id);

        $clientPackageService->redeem($clientPackage, [
            'quantity' => 1,
            'booking_service_id' => $blowDryService->id,
            'notes' => 'Demo redemption — used 1 blow dry',
        ], $ownerMember->id);

        app(LoyaltyRedemptionSettingsService::class)->update([
            'is_loyalty_redemption_enabled' => true,
            'points_per_redemption_block' => 100,
            'value_cents_per_block' => 1000,
        ]);

        app(LoyaltyLedgerService::class)->postManualAward([
            'client_id' => $alex->id,
            'points' => 250,
            'notes' => 'Demo loyalty balance for POS redemption',
        ], $ownerMember->id);

        $walletService->postManualCredit([
            'client_id' => $alex->id,
            'amount_cents' => 2000,
            'notes' => 'Extra wallet credit for POS demo checkout',
        ], $ownerMember->id);

        $clientPackage->refresh();

        $appointment = Appointment::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('client_id', $alex->id)
            ->where('status', Appointment::STATUS_CHECKED_IN)
            ->first();

        if ($appointment !== null) {
            $serviceLine = AppointmentServiceLine::withoutGlobalScopes()
                ->where('appointment_id', $appointment->id)
                ->where('booking_service_id', $blowDryService->id)
                ->first();

            if ($serviceLine !== null && (float) $clientPackage->quantity_remaining > 0) {
                app(PackageEntitlementService::class)->reserveForServiceLine(
                    $appointment,
                    $serviceLine,
                    $clientPackage->id,
                    1.0,
                    $ownerMember->id,
                );
            }
        }
    }
}
