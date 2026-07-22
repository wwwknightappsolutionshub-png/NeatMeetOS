<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\WaitlistEntry;
use App\Domains\Crm\Models\Client;
use App\Domains\Integrations\Enums\ProviderCategory;
use App\Domains\Integrations\Enums\ProviderDriver;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Models\MarketingAutomationWorkflow;
use App\Domains\Marketing\Models\MarketingMessage;
use App\Domains\Marketing\Models\MarketingWorkflowExecution;
use App\Domains\Memberships\Models\ClientMembership;
use App\Domains\Memberships\Models\MembershipPlan;
use App\Domains\Notifications\Models\NotificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Step 25 — cross-module closure regression tests for tenant isolation and permission edges.
 */
class Module25PlatformClosureTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_waitlist_entry_is_tenant_isolated(): void
    {
        $ctx = $this->seedTenantContext(['booking.view', 'booking.manage', 'crm.view']);

        $otherLocation = \App\Domains\Identity\Models\Location::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'name' => 'Other HQ',
            'slug' => 'other-hq',
            'timezone' => 'Europe/London',
            'is_active' => true,
        ]);

        $foreign = WaitlistEntry::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'client_id' => Client::withoutGlobalScopes()->create([
                'tenant_id' => $ctx['otherTenant']->id,
                'first_name' => 'Foreign',
                'last_name' => 'Client',
                'is_active' => true,
            ])->id,
            'location_id' => $otherLocation->id,
            'status' => WaitlistEntry::STATUS_WAITING,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/waitlist/'.$foreign->id)
            ->assertNotFound();

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/waitlist/'.$foreign->id, ['status' => WaitlistEntry::STATUS_CONTACTED])
            ->assertNotFound();
    }

    public function test_notification_template_show_is_tenant_isolated(): void
    {
        $ctx = $this->seedTenantContext(['notifications.view']);

        $foreign = NotificationTemplate::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'name' => 'Foreign template',
            'slug' => 'foreign-template',
            'channel' => 'email',
            'category' => 'operational',
            'body_text' => 'Hello',
            'is_active' => true,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/notifications/templates/'.$foreign->id)
            ->assertNotFound();
    }

    public function test_marketing_message_and_execution_are_tenant_isolated(): void
    {
        $ctx = $this->seedTenantContext(['marketing.view']);

        $foreignMessage = MarketingMessage::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'channel' => MarketingChannel::EMAIL,
            'purpose' => 'campaign',
            'recipient_address' => 'foreign@example.com',
            'status' => 'sent',
        ]);

        $foreignWorkflow = MarketingAutomationWorkflow::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'name' => 'Foreign workflow',
            'slug' => 'foreign-workflow',
            'trigger_type' => 'manual',
            'channel' => MarketingChannel::EMAIL,
            'status' => 'active',
            'delay_minutes' => 0,
            'allow_repeat' => false,
        ]);

        $foreignExecution = MarketingWorkflowExecution::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'workflow_id' => $foreignWorkflow->id,
            'status' => 'running',
            'trigger_type' => 'manual',
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/messages/'.$foreignMessage->id)
            ->assertNotFound();

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/executions/'.$foreignExecution->id)
            ->assertNotFound();
    }

    public function test_foreign_appointment_deposit_mutations_are_tenant_isolated(): void
    {
        $ctx = $this->seedTenantContext(['payments.manage', 'payments.view', 'booking.view']);

        $otherLocation = \App\Domains\Identity\Models\Location::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'name' => 'Other HQ',
            'slug' => 'other-hq-deposit',
            'timezone' => 'Europe/London',
            'is_active' => true,
        ]);

        $foreignAppointment = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'client_id' => Client::withoutGlobalScopes()->create([
                'tenant_id' => $ctx['otherTenant']->id,
                'first_name' => 'Foreign',
                'last_name' => 'Appt',
                'is_active' => true,
            ])->id,
            'location_id' => $otherLocation->id,
            'deposit_status' => Appointment::DEPOSIT_PENDING,
            'deposit_required' => true,
            'deposit_amount_cents' => 1000,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => 'booked',
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/appointments/'.$foreignAppointment->id.'/deposit')
            ->assertNotFound();

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/appointments/'.$foreignAppointment->id.'/deposit/pay', [
                'amount_cents' => 1000,
                'payment_method' => 'cash',
            ])
            ->assertNotFound();
    }

    public function test_client_membership_mutations_are_tenant_isolated(): void
    {
        $ctx = $this->seedTenantContext(['memberships.view', 'memberships.manage', 'crm.view']);

        $foreignPlan = MembershipPlan::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'name' => 'Foreign plan',
            'billing_interval' => 'monthly',
            'price_cents' => 5000,
            'is_active' => true,
        ]);

        $foreignMembership = ClientMembership::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'membership_plan_id' => $foreignPlan->id,
            'client_id' => Client::withoutGlobalScopes()->create([
                'tenant_id' => $ctx['otherTenant']->id,
                'first_name' => 'Foreign',
                'last_name' => 'Member',
                'is_active' => true,
            ])->id,
            'status' => 'active',
            'started_at' => now(),
            'price_cents_snapshot' => 5000,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/memberships/client-memberships/'.$foreignMembership->id)
            ->assertNotFound();

        $this->withTenantAuth($ctx['token'])
            ->patchJson('/api/v1/admin/memberships/client-memberships/'.$foreignMembership->id.'/pause')
            ->assertNotFound();
    }

    public function test_webhook_ingest_rejects_tenant_id_mismatch_with_provider_account(): void
    {
        $ctx = $this->seedTenantContext(['integrations.manage']);

        $accountId = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'Stripe',
                'category' => ProviderCategory::PAYMENT_GATEWAY,
                'driver' => ProviderDriver::STRIPE,
                'credentials' => ['secret_key' => 'sk_test'],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->postJson('/api/v1/integrations/webhooks/stripe', [
            'tenant_id' => $ctx['otherTenant']->id,
            'provider_account_id' => $accountId,
            'type' => 'payment_intent.succeeded',
            'id' => 'evt_mismatch',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('provider_webhook_events', [
            'external_event_id' => 'evt_mismatch',
        ]);
    }

    public function test_webhook_ingest_binds_event_to_provider_account_tenant(): void
    {
        $ctx = $this->seedTenantContext(['integrations.view', 'integrations.manage']);

        $accountId = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'Stripe bind',
                'category' => ProviderCategory::PAYMENT_GATEWAY,
                'driver' => ProviderDriver::STRIPE,
                'credentials' => ['secret_key' => 'sk_test'],
            ])
            ->assertCreated()
            ->json('data.id');

        $eventId = $this->postJson('/api/v1/integrations/webhooks/stripe', [
            'provider_account_id' => $accountId,
            'type' => 'charge.succeeded',
            'id' => 'evt_bound',
        ])->assertCreated()->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/integrations/provider-events/'.$eventId)
            ->assertOk()
            ->assertJsonPath('data.tenant_id', $ctx['tenant']->id);
    }

    public function test_integrations_reporting_view_does_not_grant_manage_actions(): void
    {
        $viewOnly = $this->seedTenantContext(['integrations.view', 'integrations.reporting.view']);

        $this->withTenantAuth($viewOnly['token'])
            ->getJson('/api/v1/admin/integrations/provider-accounts')
            ->assertOk();

        $this->withTenantAuth($viewOnly['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'Blocked',
                'category' => ProviderCategory::EMAIL,
                'driver' => ProviderDriver::SIMULATION,
            ])
            ->assertForbidden();
    }
}
