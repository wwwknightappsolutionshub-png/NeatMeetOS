<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Crm\Models\Client;
use App\Domains\Payments\Enums\PaymentDirection;
use App\Domains\Payments\Enums\PaymentTransactionStatus;
use App\Domains\Payments\Enums\PaymentTransactionType;
use App\Domains\Payments\Models\PaymentTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module12CBusinessPerformanceIntelligenceTest extends TestCase
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

    public function test_intelligence_returns_five_sections_with_visibility_metrics(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $this->seedIntelligenceFixtures($ctx);

        $response = $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/intelligence')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'generated_at',
                    'windows' => ['today', 'week', 'month'],
                    'business_performance' => [
                        'customers_served_today',
                        'customers_served_week',
                        'customers_served_month',
                        'total_revenue_cents',
                        'average_spend_cents',
                        'new_customers_month',
                        'returning_customers_month',
                        'walk_ins_month',
                        'online_bookings_month',
                    ],
                    'customer_intelligence' => [
                        'identified_served_month',
                        'anonymous_served_month',
                        'visibility_rate',
                        'returning_rate',
                        'first_time_rate',
                        'unidentified_gap_count',
                    ],
                    'repeat_revenue_opportunity' => [
                        'clients_due_soon',
                        'clients_overdue',
                        'estimated_opportunity_cents',
                        'crm_joiners_without_visit',
                    ],
                    'action_center',
                    'business_insights',
                    'metric_definitions_doc',
                ],
            ]);

        $data = $response->json('data');

        $this->assertSame(1, $data['customer_intelligence']['identified_served_month']);
        $this->assertSame(1, $data['customer_intelligence']['anonymous_served_month']);
        $this->assertSame(0.5, $data['customer_intelligence']['visibility_rate']);
        $this->assertSame(1, $data['customer_intelligence']['unidentified_gap_count']);
        $this->assertSame(2, $data['business_performance']['customers_served_month']);
        $this->assertSame(1, $data['business_performance']['walk_ins_month']);
        $this->assertSame(1, $data['business_performance']['online_bookings_month']);
        $this->assertSame(4500, $data['business_performance']['total_revenue_cents']);
        $this->assertSame(1, $data['repeat_revenue_opportunity']['crm_joiners_without_visit']);
        $this->assertNotEmpty($data['action_center']);
        $this->assertSame(
            'docs/BUSINESS_PERFORMANCE_INTELLIGENCE_METRICS.md',
            $data['metric_definitions_doc'],
        );

        $captureTask = collect($data['action_center'])->firstWhere('key', 'capture_anonymous_contacts');
        $this->assertNotNull($captureTask);
        $this->assertSame(1, $captureTask['count']);
        $this->assertSame('/admin/settings/crm-join-qr', $captureTask['href']);
    }

    public function test_intelligence_requires_analytics_view(): void
    {
        $ctx = $this->seedTenantContext(['identity.view']);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/intelligence')
            ->assertForbidden();
    }

    public function test_intelligence_is_tenant_isolated(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $this->seedIntelligenceFixtures($ctx);

        Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'first_name' => 'Other',
            'last_name' => 'Tenant',
            'email' => 'other.'.Str::random(6).'@example.com',
            'is_active' => true,
            'membership_joined_at' => now()->subDay(),
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/intelligence')
            ->assertOk()
            ->assertJsonPath('data.repeat_revenue_opportunity.crm_joiners_without_visit', 1);
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function seedIntelligenceFixtures(array $ctx): void
    {
        $identified = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Visible',
            'last_name' => 'Guest',
            'email' => 'visible.'.Str::random(6).'@example.com',
            'phone' => '+447700900111',
            'phone_normalized' => '+447700900111',
            'primary_location_id' => $ctx['location']->id,
            'is_active' => true,
        ]);

        $anonymousClient = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'No',
            'last_name' => 'Contact',
            'email' => null,
            'phone' => null,
            'phone_normalized' => null,
            'primary_location_id' => $ctx['location']->id,
            'is_active' => true,
        ]);

        Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Joined',
            'last_name' => 'Only',
            'email' => 'joined.'.Str::random(6).'@example.com',
            'phone' => '+447700900222',
            'phone_normalized' => '+447700900222',
            'membership_joined_at' => now()->subDays(2),
            'is_active' => true,
        ]);

        Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $identified->id,
            'team_member_id' => $ctx['teamMember']->id,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
            'status' => Appointment::STATUS_COMPLETED,
            'booking_source' => Appointment::SOURCE_ONLINE,
        ]);

        Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $anonymousClient->id,
            'team_member_id' => $ctx['teamMember']->id,
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHours(2),
            'status' => Appointment::STATUS_COMPLETED,
            'booking_source' => Appointment::SOURCE_WALK_IN,
            'walk_in_stage' => Appointment::WALK_IN_SEATED,
        ]);

        PaymentTransaction::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $identified->id,
            'transaction_type' => PaymentTransactionType::SALE,
            'direction' => PaymentDirection::INBOUND,
            'status' => PaymentTransactionStatus::SUCCEEDED,
            'amount_cents' => 4500,
            'currency' => 'GBP',
            'provider' => 'manual',
            'created_at' => now()->subHour(),
        ]);
    }
}
