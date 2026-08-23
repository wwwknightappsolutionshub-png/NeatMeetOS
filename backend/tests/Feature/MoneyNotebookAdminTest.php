<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\AppointmentServiceLine;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\TenantModuleOverride;
use App\Domains\Money\Models\MoneyEntry;
use App\Domains\Payments\Enums\PaymentDirection;
use App\Domains\Payments\Enums\PaymentTransactionStatus;
use App\Domains\Payments\Enums\PaymentTransactionType;
use App\Domains\Payments\Models\PaymentRefund;
use App\Domains\Payments\Models\PaymentTransaction;
use App\Shared\Commerce\Enums\CheckoutStatus;
use App\Shared\Commerce\Models\CommerceCheckout;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class MoneyNotebookAdminTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    protected function moneyPermissions(): array
    {
        return [
            'identity.view',
            'money.view',
            'money.manage',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-19 12:00:00', 'Europe/London'));
    }

    public function test_basic_plan_includes_money_by_default(): void
    {
        $this->assertTrue(\App\Domains\Identity\Support\PlatformModuleCatalogue::defaultsForPlanSlug('basic')['money']);
        $this->assertTrue(\App\Domains\Identity\Support\PlatformModuleCatalogue::isValid('money'));
    }

    public function test_summary_combines_taken_spend_and_coming_up(): void
    {
        $ctx = $this->seedTenantContext($this->moneyPermissions());
        $this->seedMoneyFixtures($ctx);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/money/summary?month=2026-08')
            ->assertOk()
            ->assertJsonPath('data.month', '2026-08')
            ->assertJsonPath('data.taken_breakdown.from_cards_and_app_cents', 4000)
            ->assertJsonPath('data.taken_breakdown.from_till_cents', 7500)
            ->assertJsonPath('data.taken_breakdown.cash_you_added_cents', 1000)
            ->assertJsonPath('data.taken_cents', 12500)
            ->assertJsonPath('data.spent_cents', 3000)
            ->assertJsonPath('data.left_cents', 9500)
            ->assertJsonPath('data.coming_up.booked_cents', 12000)
            ->assertJsonPath('data.coming_up.booked_visits', 1);
    }

    public function test_owner_can_add_cash_and_spend_in_pounds(): void
    {
        $ctx = $this->seedTenantContext($this->moneyPermissions());

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/money/entries', [
                'kind' => MoneyEntry::KIND_CASH_IN,
                'amount_pounds' => 10.5,
                'occurred_on' => '2026-08-10',
                'note' => 'Walk-in cash',
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount_cents', 1050)
            ->assertJsonPath('data.kind', 'cash_in');

        $spend = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/money/entries', [
                'kind' => MoneyEntry::KIND_SPEND,
                'category' => MoneyEntry::CATEGORY_RENT,
                'amount_pounds' => 900,
                'occurred_on' => '2026-08-01',
                'note' => 'August chair',
            ])
            ->assertCreated()
            ->assertJsonPath('data.category_label', 'Rent / chair');

        $this->assertDatabaseHas('audit_logs', ['action' => 'money.entry.created']);

        $id = $spend->json('data.id');
        $this->withTenantAuth($ctx['token'])
            ->deleteJson('/api/v1/admin/money/entries/'.$id)
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'money.entry.deleted']);
        $this->assertDatabaseMissing('money_entries', ['id' => $id]);
    }

    public function test_viewer_cannot_manage_money(): void
    {
        $ctx = $this->seedTenantContext($this->moneyPermissions());

        $this->withTenantAuth($ctx['viewerToken'])
            ->getJson('/api/v1/admin/money/summary')
            ->assertForbidden();

        $this->withTenantAuth($ctx['viewerToken'])
            ->postJson('/api/v1/admin/money/entries', [
                'kind' => MoneyEntry::KIND_SPEND,
                'category' => MoneyEntry::CATEGORY_ADS,
                'amount_cents' => 500,
                'occurred_on' => '2026-08-19',
            ])
            ->assertForbidden();
    }

    public function test_feature_flag_can_lock_money_module(): void
    {
        $ctx = $this->seedTenantContext($this->moneyPermissions());

        TenantModuleOverride::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'module_key' => 'money',
            'enabled' => false,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/money/summary')
            ->assertForbidden()
            ->assertJsonPath('code', 'module_upgrade_required');
    }

    public function test_summary_excludes_other_tenant_money(): void
    {
        $ctx = $this->seedTenantContext($this->moneyPermissions());

        MoneyEntry::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'kind' => MoneyEntry::KIND_SPEND,
            'category' => MoneyEntry::CATEGORY_RENT,
            'amount_cents' => 99999,
            'occurred_on' => '2026-08-05',
        ]);

        PaymentTransaction::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'transaction_type' => PaymentTransactionType::SALE,
            'direction' => PaymentDirection::INBOUND,
            'status' => PaymentTransactionStatus::SUCCEEDED,
            'amount_cents' => 88888,
            'currency' => 'GBP',
            'created_at' => now(),
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/money/summary?month=2026-08')
            ->assertOk()
            ->assertJsonPath('data.taken_cents', 0)
            ->assertJsonPath('data.spent_cents', 0);
    }

    public function test_ledger_lists_inflows_and_outflows_for_date_range(): void
    {
        $ctx = $this->seedTenantContext($this->moneyPermissions());
        $this->seedMoneyFixtures($ctx);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/money/ledger?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertJsonPath('data.from', '2026-08-01')
            ->assertJsonPath('data.to', '2026-08-31')
            ->assertJsonPath('data.inflow_cents', 13500)
            ->assertJsonPath('data.outflow_cents', 4000)
            ->assertJsonPath('data.net_cents', 9500)
            ->assertJsonCount(5, 'data.rows');

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/money/ledger?from=2026-08-01&to=2026-08-31&direction=outflow')
            ->assertOk()
            ->assertJsonCount(2, 'data.rows')
            ->assertJsonPath('data.rows.0.direction', 'outflow');
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function seedMoneyFixtures(array $ctx): void
    {
        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Money',
            'last_name' => 'Client',
            'is_active' => true,
        ]);

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
            'created_at' => Carbon::parse('2026-08-05 10:00:00', 'Europe/London'),
        ]);

        PaymentRefund::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'payment_transaction_id' => $payment->id,
            'amount_cents' => 1000,
            'status' => PaymentTransactionStatus::SUCCEEDED,
            'created_at' => Carbon::parse('2026-08-06 10:00:00', 'Europe/London'),
        ]);

        CommerceCheckout::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'location_id' => $ctx['location']->id,
            'team_member_id' => $ctx['teamMember']->id,
            'status' => CheckoutStatus::COMPLETED,
            'total_cents' => 8000,
            'refunded_total_cents' => 500,
            'completed_at' => Carbon::parse('2026-08-08 14:00:00', 'Europe/London'),
        ]);

        MoneyEntry::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'kind' => MoneyEntry::KIND_CASH_IN,
            'category' => MoneyEntry::CATEGORY_CASH,
            'amount_cents' => 1000,
            'occurred_on' => '2026-08-09',
        ]);

        MoneyEntry::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'kind' => MoneyEntry::KIND_SPEND,
            'category' => MoneyEntry::CATEGORY_PRODUCTS,
            'amount_cents' => 3000,
            'occurred_on' => '2026-08-11',
            'note' => 'Colour stock',
        ]);

        $appointment = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $client->id,
            'team_member_id' => $ctx['teamMember']->id,
            'starts_at' => Carbon::parse('2026-09-03 11:00:00', 'Europe/London'),
            'ends_at' => Carbon::parse('2026-09-03 12:00:00', 'Europe/London'),
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        AppointmentServiceLine::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'appointment_id' => $appointment->id,
            'service_name' => 'Cut and finish',
            'duration_minutes' => 60,
            'price_cents' => 12000,
        ]);
    }
}
