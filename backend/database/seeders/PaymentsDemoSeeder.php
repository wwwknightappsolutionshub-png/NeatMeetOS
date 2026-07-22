<?php

namespace Database\Seeders;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Payments\Enums\PaymentDirection;
use App\Domains\Payments\Enums\PaymentMethodType;
use App\Domains\Payments\Enums\PaymentProvider;
use App\Domains\Payments\Enums\PaymentTransactionStatus;
use App\Domains\Payments\Enums\PaymentTransactionType;
use App\Domains\Payments\Models\PaymentAllocation;
use App\Domains\Payments\Models\PaymentRefund;
use App\Domains\Payments\Models\PaymentTransaction;
use App\Shared\Commerce\Enums\DepositLifecycleState;
use App\Shared\Commerce\Enums\PaymentAllocationType;
use App\Shared\Commerce\Models\CommerceDepositRecord;
use Illuminate\Database\Seeder;

class PaymentsDemoSeeder extends Seeder
{
    public function run(Tenant $tenant, Location $location, TeamMember $ownerMember): void
    {
        $pendingDepositAppt = Appointment::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('booking_reference', 'NM-DEMO0002')
            ->first();

        if ($pendingDepositAppt === null) {
            return;
        }

        $depositRecord = CommerceDepositRecord::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'appointment_id' => $pendingDepositAppt->id,
            'booking_deposit_status' => Appointment::DEPOSIT_SATISFIED,
            'required_cents' => $pendingDepositAppt->deposit_required_cents ?? 3000,
            'collected_cents' => 3000,
            'lifecycle_state' => DepositLifecycleState::COLLECTED,
            'rule_snapshot' => $pendingDepositAppt->deposit_rule_snapshot,
        ]);

        $collectedTxn = PaymentTransaction::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'client_id' => $pendingDepositAppt->client_id,
            'appointment_id' => $pendingDepositAppt->id,
            'team_member_id' => $ownerMember->id,
            'transaction_type' => PaymentTransactionType::DEPOSIT,
            'direction' => PaymentDirection::INBOUND,
            'status' => PaymentTransactionStatus::SUCCEEDED,
            'amount_cents' => 3000,
            'currency' => 'GBP',
            'provider' => PaymentProvider::MANUAL,
            'payment_method_type' => PaymentMethodType::CARD,
            'payment_method_label' => 'Demo card (manual)',
            'processed_at' => now()->subHours(3),
            'created_by_team_member_id' => $ownerMember->id,
        ]);

        $depositRecord->update([
            'payment_transaction_id' => $collectedTxn->id,
            'collected_at' => now()->subHours(3),
        ]);

        PaymentAllocation::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'payment_transaction_id' => $collectedTxn->id,
            'allocation_type' => PaymentAllocationType::DEPOSIT,
            'amount_cents' => 3000,
            'appointment_id' => $pendingDepositAppt->id,
            'commerce_deposit_record_id' => $depositRecord->id,
        ]);

        $pendingDepositAppt->update(['deposit_status' => Appointment::DEPOSIT_SATISFIED]);

        $linkAppt = Appointment::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('booking_reference', 'NM-DEMO0001')
            ->first();

        if ($linkAppt !== null) {
            PaymentTransaction::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $location->id,
                'client_id' => $linkAppt->client_id,
                'appointment_id' => $linkAppt->id,
                'transaction_type' => PaymentTransactionType::DEPOSIT,
                'direction' => PaymentDirection::INBOUND,
                'status' => PaymentTransactionStatus::PENDING,
                'amount_cents' => 1500,
                'currency' => 'GBP',
                'provider' => PaymentProvider::PAYMENT_LINK,
                'provider_reference' => 'plink_demo_pending01',
                'payment_method_type' => PaymentMethodType::PAYMENT_LINK,
                'payment_method_label' => 'Demo payment link',
                'metadata' => ['demo' => true, 'note' => 'Awaiting client payment'],
                'created_by_team_member_id' => $ownerMember->id,
            ]);
        }

        PaymentTransaction::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'client_id' => $pendingDepositAppt->client_id,
            'transaction_type' => PaymentTransactionType::DEPOSIT,
            'direction' => PaymentDirection::INBOUND,
            'status' => PaymentTransactionStatus::FAILED,
            'amount_cents' => 3000,
            'currency' => 'GBP',
            'provider' => PaymentProvider::PAYMENT_LINK,
            'payment_method_type' => PaymentMethodType::PAYMENT_LINK,
            'failed_at' => now()->subDay(),
            'failure_code' => 'card_declined',
            'failure_message' => 'Demo failed deposit attempt',
            'created_by_team_member_id' => $ownerMember->id,
        ]);

        $refundAppt = Appointment::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('booking_reference', 'NM-REC0000')
            ->first();

        if ($refundAppt !== null) {
            $refundDeposit = CommerceDepositRecord::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'appointment_id' => $refundAppt->id,
                'booking_deposit_status' => Appointment::DEPOSIT_PENDING,
                'required_cents' => 2000,
                'collected_cents' => 2000,
                'lifecycle_state' => DepositLifecycleState::REFUNDED,
                'rule_snapshot' => ['demo' => true],
            ]);

            $sourceTxn = PaymentTransaction::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $location->id,
                'client_id' => $refundAppt->client_id,
                'appointment_id' => $refundAppt->id,
                'transaction_type' => PaymentTransactionType::DEPOSIT,
                'direction' => PaymentDirection::INBOUND,
                'status' => PaymentTransactionStatus::REFUNDED,
                'amount_cents' => 2000,
                'currency' => 'GBP',
                'provider' => PaymentProvider::MANUAL,
                'payment_method_type' => PaymentMethodType::CASH,
                'processed_at' => now()->subDays(2),
                'created_by_team_member_id' => $ownerMember->id,
            ]);

            $refundTxn = PaymentTransaction::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $location->id,
                'client_id' => $refundAppt->client_id,
                'appointment_id' => $refundAppt->id,
                'transaction_type' => PaymentTransactionType::REFUND,
                'direction' => PaymentDirection::OUTBOUND,
                'status' => PaymentTransactionStatus::SUCCEEDED,
                'amount_cents' => 2000,
                'currency' => 'GBP',
                'provider' => PaymentProvider::MANUAL,
                'processed_at' => now()->subDay(),
                'metadata' => ['source_transaction_id' => $sourceTxn->id],
                'created_by_team_member_id' => $ownerMember->id,
            ]);

            $refundDeposit->update([
                'payment_transaction_id' => $sourceTxn->id,
                'refunded_payment_transaction_id' => $refundTxn->id,
                'collected_at' => now()->subDays(2),
                'refunded_at' => now()->subDay(),
            ]);

            PaymentRefund::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'payment_transaction_id' => $sourceTxn->id,
                'refund_transaction_id' => $refundTxn->id,
                'amount_cents' => 2000,
                'reason' => 'Demo deposit refund',
                'status' => PaymentRefund::STATUS_SUCCEEDED,
                'processed_at' => now()->subDay(),
                'created_by_team_member_id' => $ownerMember->id,
            ]);
        }
    }
}
