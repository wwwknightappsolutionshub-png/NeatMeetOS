<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientConsentRecord;
use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Models\InventoryItem;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Marketing\Enums\MarketingMessageStatus;
use App\Domains\Marketing\Models\MarketingCampaign;
use App\Domains\Marketing\Models\MarketingMessage;
use App\Domains\Memberships\Enums\ClientMembershipStatus;
use App\Domains\Memberships\Enums\WalletEntryDirection;
use App\Domains\Memberships\Enums\WalletEntryType;
use App\Domains\Memberships\Models\ClientMembership;
use App\Domains\Memberships\Models\ClientWalletEntry;
use App\Domains\Memberships\Models\MembershipPlan;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationMessageStatus;
use App\Domains\Notifications\Enums\NotificationPurpose;
use App\Domains\Notifications\Enums\NotificationSourceType;
use App\Domains\Notifications\Models\NotificationMessage;
use App\Domains\Payments\Enums\PaymentDirection;
use App\Domains\Payments\Enums\PaymentTransactionStatus;
use App\Domains\Payments\Enums\PaymentTransactionType;
use App\Domains\Payments\Models\PaymentRefund;
use App\Domains\Payments\Models\PaymentTransaction;
use App\Shared\Commerce\Enums\CheckoutStatus;
use App\Shared\Commerce\Enums\DepositLifecycleState;
use App\Shared\Commerce\Models\CommerceCheckout;
use App\Shared\Commerce\Models\CommerceDepositRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module12AAnalyticsAdminTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    protected function modulePermissions(): array
    {
        return [
            'analytics.view',
            'analytics.reporting.view',
            'crm.view',
            'booking.view',
        ];
    }

    public function test_overview_returns_structured_payload_with_expected_sections(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $this->seedAnalyticsFixtures($ctx);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/overview')
            ->assertOk()
            ->assertJsonPath('data.range.from', fn ($value) => $value !== null)
            ->assertJsonStructure([
                'data' => [
                    'range' => ['from', 'to', 'days'],
                    'bookings' => [
                        'total_appointments',
                        'completed_appointments',
                        'cancelled_appointments',
                        'no_show_appointments',
                        'checked_in_appointments',
                        'walk_in_appointments',
                        'average_booking_value_cents',
                    ],
                    'payments',
                    'pos',
                    'clients',
                    'memberships',
                    'inventory',
                    'marketing',
                    'notifications',
                ],
            ])
            ->assertJsonPath('data.bookings.total_appointments', 3)
            ->assertJsonPath('data.payments.total_payment_collected_cents', 5000)
            ->assertJsonPath('data.pos.completed_checkouts_count', 1);
    }

    public function test_booking_analytics_status_counts_and_provider_filter(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $fixtures = $this->seedAnalyticsFixtures($ctx);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/bookings')
            ->assertOk()
            ->assertJsonPath('data.summary.total_appointments', 3)
            ->assertJsonPath('data.summary.completed_appointments', 1)
            ->assertJsonPath('data.summary.cancelled_appointments', 1)
            ->assertJsonPath('data.summary.no_show_appointments', 1)
            ->assertJsonStructure([
                'data' => [
                    'summary',
                    'daily',
                    'providers',
                    'services',
                ],
            ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/bookings?provider_id='.$fixtures['providerId'])
            ->assertOk()
            ->assertJsonPath('data.summary.total_appointments', 3);
    }

    public function test_booking_analytics_excludes_cross_tenant_data(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $this->seedAnalyticsFixtures($ctx);

        $foreignClient = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'first_name' => 'Foreign',
            'last_name' => 'Client',
            'is_active' => true,
        ]);

        Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $foreignClient->id,
            'team_member_id' => $ctx['teamMember']->id,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDays(2)->addHour(),
            'status' => Appointment::STATUS_COMPLETED,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/bookings')
            ->assertOk()
            ->assertJsonPath('data.summary.total_appointments', 3);
    }

    public function test_revenue_analytics_includes_payments_deposits_and_pos(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $this->seedAnalyticsFixtures($ctx);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/revenue')
            ->assertOk()
            ->assertJsonPath('data.summary.payments.total_payment_collected_cents', 5000)
            ->assertJsonPath('data.summary.payments.deposit_collected_cents', 1500)
            ->assertJsonPath('data.summary.payments.refund_total_cents', 1000)
            ->assertJsonPath('data.summary.pos.gross_sales_cents', 8000)
            ->assertJsonStructure([
                'data' => [
                    'summary' => ['payments', 'pos'],
                    'daily',
                    'payment_status_breakdown',
                    'payment_type_breakdown',
                    'provider_breakdown',
                ],
            ]);
    }

    public function test_client_analytics_returns_growth_and_consent_counts(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $this->seedAnalyticsFixtures($ctx);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/clients')
            ->assertOk()
            ->assertJsonPath('data.summary.total_clients', 1)
            ->assertJsonPath('data.summary.new_clients_in_period', 1)
            ->assertJsonPath('data.summary.active_clients', 1)
            ->assertJsonPath('data.summary.marketing_email_opt_in_count', 1)
            ->assertJsonStructure([
                'data' => [
                    'summary',
                    'growth',
                    'tags',
                    'consents',
                    'membership_attachment',
                ],
            ]);
    }

    public function test_inventory_analytics_returns_low_stock_and_movements(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $this->seedAnalyticsFixtures($ctx);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/inventory')
            ->assertOk()
            ->assertJsonPath('data.summary.low_stock_items_count', 1)
            ->assertJsonPath('data.summary.stock_adjustments_count', 1)
            ->assertJsonPath('data.summary.stock_consumption_events_count', 1)
            ->assertJsonStructure([
                'data' => [
                    'summary',
                    'movement_breakdown',
                    'low_stock',
                    'top_consumed_items',
                ],
            ]);
    }

    public function test_communications_analytics_returns_marketing_and_notifications(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $this->seedAnalyticsFixtures($ctx);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/communications')
            ->assertOk()
            ->assertJsonPath('data.marketing.messages_sent_count', 1)
            ->assertJsonPath('data.marketing.messages_failed_count', 1)
            ->assertJsonPath('data.notifications.messages_sent_count', 1)
            ->assertJsonPath('data.notifications.messages_failed_count', 1)
            ->assertJsonStructure([
                'data' => [
                    'marketing' => ['campaigns_count', 'by_channel'],
                    'notifications' => ['by_channel'],
                ],
            ]);
    }

    public function test_permission_gate_blocks_without_analytics_view(): void
    {
        $ctx = $this->seedTenantContext(['identity.view']);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/overview')
            ->assertForbidden();
    }

    public function test_tenant_isolation_excludes_other_tenant_metrics(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $this->seedAnalyticsFixtures($ctx);

        PaymentTransaction::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'transaction_type' => PaymentTransactionType::SALE,
            'direction' => PaymentDirection::INBOUND,
            'status' => PaymentTransactionStatus::SUCCEEDED,
            'amount_cents' => 999_999,
            'currency' => 'GBP',
            'created_at' => now()->subDay(),
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/overview')
            ->assertOk()
            ->assertJsonPath('data.payments.total_payment_collected_cents', 5000);
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function seedAnalyticsFixtures(array $ctx): array
    {
        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Analytics',
            'last_name' => 'Client',
            'email' => 'analytics.'.Str::random(6).'@example.com',
            'primary_location_id' => $ctx['location']->id,
            'is_active' => true,
            'created_at' => now()->subDays(4),
        ]);

        ClientConsentRecord::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'consent_type' => ClientConsentRecord::TYPE_MARKETING_EMAIL,
            'granted' => true,
            'source' => ClientConsentRecord::SOURCE_STAFF_ENTRY,
            'recorded_at' => now()->subDays(3),
        ]);

        $statuses = [
            Appointment::STATUS_COMPLETED,
            Appointment::STATUS_CANCELLED,
            Appointment::STATUS_NO_SHOW,
        ];

        foreach ($statuses as $index => $status) {
            Appointment::withoutGlobalScopes()->create([
                'tenant_id' => $ctx['tenant']->id,
                'location_id' => $ctx['location']->id,
                'client_id' => $client->id,
                'team_member_id' => $ctx['teamMember']->id,
                'starts_at' => now()->subDays(5 - $index),
                'ends_at' => now()->subDays(5 - $index)->addHour(),
                'status' => $status,
            ]);
        }

        $payment = PaymentTransaction::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $client->id,
            'transaction_type' => PaymentTransactionType::SALE,
            'direction' => PaymentDirection::INBOUND,
            'status' => PaymentTransactionStatus::SUCCEEDED,
            'amount_cents' => 5000,
            'currency' => 'GBP',
            'provider' => 'manual',
            'created_at' => now()->subDays(3),
        ]);

        PaymentRefund::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'payment_transaction_id' => $payment->id,
            'amount_cents' => 1000,
            'status' => PaymentTransactionStatus::SUCCEEDED,
            'created_at' => now()->subDays(2),
        ]);

        CommerceDepositRecord::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'appointment_id' => Appointment::withoutGlobalScopes()->where('tenant_id', $ctx['tenant']->id)->first()->id,
            'booking_deposit_status' => 'collected',
            'required_cents' => 1500,
            'collected_cents' => 1500,
            'lifecycle_state' => DepositLifecycleState::COLLECTED,
            'collected_at' => now()->subDays(3),
        ]);

        CommerceCheckout::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'location_id' => $ctx['location']->id,
            'team_member_id' => $ctx['teamMember']->id,
            'status' => CheckoutStatus::COMPLETED,
            'total_cents' => 8000,
            'refunded_total_cents' => 500,
            'completed_at' => now()->subDays(2),
        ]);

        $plan = MembershipPlan::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Gold',
            'price_cents' => 5000,
            'status' => 'active',
        ]);

        ClientMembership::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'membership_plan_id' => $plan->id,
            'status' => ClientMembershipStatus::ACTIVE,
            'started_at' => now()->subMonth(),
            'price_cents_snapshot' => 5000,
        ]);

        ClientWalletEntry::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'entry_type' => WalletEntryType::MANUAL_CREDIT,
            'direction' => WalletEntryDirection::CREDIT,
            'amount_cents' => 2500,
            'balance_effective_at' => now()->subDays(10),
        ]);

        $item = InventoryItem::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Shampoo',
            'item_type' => 'professional',
            'status' => 'active',
        ]);

        InventoryLevel::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'inventory_item_id' => $item->id,
            'location_id' => $ctx['location']->id,
            'on_hand_quantity' => 2,
            'reorder_point' => 5,
        ]);

        InventoryMovement::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'inventory_item_id' => $item->id,
            'location_id' => $ctx['location']->id,
            'movement_type' => InventoryMovementType::ADJUSTMENT,
            'quantity_delta' => -1,
            'created_at' => now()->subDays(2),
        ]);

        InventoryMovement::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'inventory_item_id' => $item->id,
            'location_id' => $ctx['location']->id,
            'movement_type' => InventoryMovementType::SERVICE_CONSUMPTION,
            'quantity_delta' => -0.5,
            'created_at' => now()->subDay(),
        ]);

        MarketingCampaign::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Spring promo',
            'campaign_type' => 'broadcast',
            'channel' => 'email',
            'status' => 'active',
            'created_at' => now()->subDays(5),
        ]);

        MarketingMessage::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'channel' => 'email',
            'purpose' => 'broadcast',
            'status' => MarketingMessageStatus::SENT,
            'recipient_address' => $client->email,
            'rendered_body_text' => 'Hello',
            'created_at' => now()->subDays(2),
        ]);

        MarketingMessage::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'channel' => 'email',
            'purpose' => 'broadcast',
            'status' => MarketingMessageStatus::FAILED,
            'recipient_address' => $client->email,
            'rendered_body_text' => 'Failed',
            'created_at' => now()->subDay(),
        ]);

        NotificationMessage::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'source_type' => NotificationSourceType::MANUAL,
            'channel' => NotificationChannel::EMAIL,
            'purpose' => NotificationPurpose::MANUAL_CLIENT_MESSAGE,
            'status' => NotificationMessageStatus::SENT,
            'recipient_address' => $client->email,
            'body_text' => 'Sent',
            'created_at' => now()->subDays(2),
        ]);

        NotificationMessage::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'source_type' => NotificationSourceType::MANUAL,
            'channel' => NotificationChannel::EMAIL,
            'purpose' => NotificationPurpose::MANUAL_CLIENT_MESSAGE,
            'status' => NotificationMessageStatus::FAILED,
            'recipient_address' => $client->email,
            'body_text' => 'Failed',
            'failure_reason' => 'Simulated',
            'created_at' => now()->subDay(),
        ]);

        return [
            'client' => $client,
            'providerId' => $ctx['teamMember']->id,
        ];
    }
}
