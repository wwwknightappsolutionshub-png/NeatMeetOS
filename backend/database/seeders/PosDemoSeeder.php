<?php

namespace Database\Seeders;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\AppointmentServiceLine;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Inventory\Enums\InventoryItemType;
use App\Domains\Inventory\Models\InventoryItem;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Payments\Enums\PaymentDirection;
use App\Domains\Payments\Enums\PaymentProvider;
use App\Domains\Payments\Enums\PaymentTransactionStatus;
use App\Domains\Payments\Enums\PaymentTransactionType;
use App\Domains\Payments\Models\PaymentAllocation;
use App\Domains\Payments\Models\PaymentTransaction;
use App\Domains\Pos\Enums\CheckoutSource;
use App\Domains\Pos\Services\CheckoutCompletionService;
use App\Domains\Pos\Services\CheckoutDepositService;
use App\Domains\Pos\Services\CheckoutImportService;
use App\Domains\Pos\Services\CheckoutLineService;
use App\Domains\Pos\Services\CheckoutPaymentService;
use App\Domains\Pos\Services\CheckoutService;
use App\Shared\Commerce\Enums\BillingSettlementStatus;
use App\Shared\Commerce\Enums\CheckoutStatus;
use App\Shared\Commerce\Enums\DepositLifecycleState;
use App\Shared\Commerce\Enums\PaymentAllocationType;
use App\Shared\Commerce\Models\CommerceCheckout;
use App\Shared\Commerce\Models\CommerceDepositRecord;
use App\Shared\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PosDemoSeeder extends Seeder
{
    public function run(Tenant $tenant, Location $location, TeamMember $ownerMember): void
    {
        $client = Client::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', 'alex.taylor@example.com')
            ->first();

        if ($client === null) {
            return;
        }

        $colour = BookableService::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Full Colour')
            ->first();

        $retailItem = InventoryItem::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('item_type', InventoryItemType::RETAIL)
            ->first();

        if ($colour === null || $retailItem === null) {
            return;
        }

        app(TenantContext::class)->set($tenant);

        $depositAppt = $this->seedDepositAppointment($tenant, $location, $ownerMember, $client, $colour);

        $checkoutService = app(CheckoutService::class);
        $importService = app(CheckoutImportService::class);
        $depositService = app(CheckoutDepositService::class);
        $paymentService = app(CheckoutPaymentService::class);
        $completionService = app(CheckoutCompletionService::class);
        $lineService = app(CheckoutLineService::class);

        // Scenario A — draft checkout ready for appointment + deposit import
        $draft = $checkoutService->createDraft([
            'location_id' => $location->id,
            'client_id' => $client->id,
            'team_member_id' => $ownerMember->id,
            'notes' => 'Demo POS draft — import NM-POS0001 appointment',
        ], $ownerMember->id);

        // Scenario B — completed mixed sale (import + retail, deposit applied)
        $mixed = $checkoutService->createDraft([
            'location_id' => $location->id,
            'team_member_id' => $ownerMember->id,
        ], $ownerMember->id);

        $mixedAppt = $this->seedCheckedInAppointment(
            $tenant,
            $location,
            $ownerMember,
            $client,
            $colour,
            'NM-POS0002',
            DepositLifecycleState::COLLECTED,
            3000,
        );

        $mixed = $importService->importAppointment($mixed->id, $mixedAppt->id);
        $lineService->addRetailLine($mixed->id, [
            'inventory_item_id' => $retailItem->id,
            'quantity' => 1,
        ]);
        $mixed = $depositService->applyDepositCredit($mixed->id);
        $due = $mixed->amount_due_cents;
        $paymentService->recordPayments($mixed->id, [[
            'amount_cents' => $due,
            'payment_method_type' => 'cash',
        ]], $ownerMember->id);
        $completionService->complete($mixed->id, $ownerMember->id);

        // Scenario C — retail-only completed sale
        $retailOnly = $checkoutService->createDraft([
            'location_id' => $location->id,
        ], $ownerMember->id);

        $retailOnly = $lineService->addRetailLine($retailOnly->id, [
            'inventory_item_id' => $retailItem->id,
            'quantity' => 2,
        ]);
        $retailDue = $retailOnly->amount_due_cents;
        $paymentService->recordPayments($retailOnly->id, [[
            'amount_cents' => $retailDue,
            'payment_method_type' => 'card_manual',
            'payment_method_label' => 'Demo manual card',
        ]], $ownerMember->id);
        $completionService->complete($retailOnly->id, $ownerMember->id);

        // Scenario D — split tender completed checkout
        $split = $checkoutService->createDraft([
            'location_id' => $location->id,
            'client_id' => $client->id,
        ], $ownerMember->id);

        $split = $lineService->addServiceLine($split->id, [
            'description' => 'Walk-in blow dry',
            'unit_price_cents' => 5000,
        ]);
        $paymentService->recordPayments($split->id, [
            ['amount_cents' => 3000, 'payment_method_type' => 'cash'],
            ['amount_cents' => 2000, 'payment_method_type' => 'card_manual'],
        ], $ownerMember->id);
        $completionService->complete($split->id, $ownerMember->id);

        // Keep draft reference on deposit appointment for UI import demo
        CommerceCheckout::withoutGlobalScopes()
            ->where('id', $draft->id)
            ->update(['metadata' => ['demo_appointment_id' => $depositAppt->id]]);
    }

    private function seedDepositAppointment(
        Tenant $tenant,
        Location $location,
        TeamMember $ownerMember,
        Client $client,
        BookableService $colour,
    ): Appointment {
        return $this->seedCheckedInAppointment(
            $tenant,
            $location,
            $ownerMember,
            $client,
            $colour,
            'NM-POS0001',
            DepositLifecycleState::COLLECTED,
            3000,
        );
    }

    private function seedCheckedInAppointment(
        Tenant $tenant,
        Location $location,
        TeamMember $ownerMember,
        Client $client,
        BookableService $service,
        string $reference,
        string $depositLifecycle,
        int $depositCents,
    ): Appointment {
        $startsAt = Carbon::now()->subHours(2);

        $appointment = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'client_id' => $client->id,
            'team_member_id' => $ownerMember->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes($service->duration_minutes),
            'status' => Appointment::STATUS_CHECKED_IN,
            'booking_source' => Appointment::SOURCE_ADMIN,
            'booking_reference' => $reference,
            'deposit_status' => Appointment::DEPOSIT_SATISFIED,
            'deposit_required_cents' => $depositCents,
            'billing_settlement_status' => BillingSettlementStatus::UNSETTLED,
            'created_by_team_member_id' => $ownerMember->id,
        ]);

        AppointmentServiceLine::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'appointment_id' => $appointment->id,
            'booking_service_id' => $service->id,
            'service_name' => $service->name,
            'duration_minutes' => $service->duration_minutes,
            'price_cents' => $service->base_price_cents,
            'sort_order' => 0,
        ]);

        if ($depositCents > 0 && $depositLifecycle === DepositLifecycleState::COLLECTED) {
            $record = CommerceDepositRecord::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'appointment_id' => $appointment->id,
                'booking_deposit_status' => Appointment::DEPOSIT_SATISFIED,
                'required_cents' => $depositCents,
                'collected_cents' => $depositCents,
                'lifecycle_state' => DepositLifecycleState::COLLECTED,
                'collected_at' => now()->subDay(),
            ]);

            $txn = PaymentTransaction::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $location->id,
                'client_id' => $client->id,
                'appointment_id' => $appointment->id,
                'team_member_id' => $ownerMember->id,
                'transaction_type' => PaymentTransactionType::DEPOSIT,
                'direction' => PaymentDirection::INBOUND,
                'status' => PaymentTransactionStatus::SUCCEEDED,
                'amount_cents' => $depositCents,
                'currency' => 'GBP',
                'provider' => PaymentProvider::MANUAL,
                'payment_method_type' => 'card',
                'processed_at' => now()->subDay(),
            ]);

            $record->update(['payment_transaction_id' => $txn->id]);

            PaymentAllocation::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'payment_transaction_id' => $txn->id,
                'allocation_type' => PaymentAllocationType::DEPOSIT,
                'amount_cents' => $depositCents,
                'appointment_id' => $appointment->id,
                'commerce_deposit_record_id' => $record->id,
            ]);
        }

        return $appointment;
    }
}
