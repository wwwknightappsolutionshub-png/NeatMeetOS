<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientConsentRecord;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingExecutionStatus;
use App\Domains\Marketing\Enums\MarketingMessageStatus;
use App\Domains\Marketing\Enums\MarketingSuppressionReason;
use App\Domains\Marketing\Enums\MarketingWorkflowStatus;
use App\Domains\Marketing\Enums\MarketingWorkflowTrigger;
use App\Domains\Marketing\Models\MarketingAutomationWorkflow;
use App\Domains\Marketing\Models\MarketingContactSuppression;
use App\Domains\Marketing\Models\MarketingMessage;
use App\Domains\Marketing\Models\MarketingTemplate;
use App\Domains\Marketing\Models\MarketingWorkflowExecution;
use App\Domains\Marketing\Services\MarketingAutomationTriggerService;
use App\Domains\Marketing\Services\MarketingEligibilityService;
use App\Domains\Marketing\Services\MarketingSuppressionService;
use App\Domains\Marketing\Services\MarketingWorkflowExecutionService;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module10BMarketingAutomationAdminTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    protected function modulePermissions(): array
    {
        return [
            'marketing.view',
            'marketing.manage',
            'marketing.dispatch',
            'marketing.reporting.view',
            'crm.view',
            'crm.manage',
            'booking.view',
            'booking.manage',
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $attributes
     * @param  array<string, bool>  $consents
     */
    protected function makeClient(array $ctx, array $attributes = [], array $consents = []): Client
    {
        $client = Client::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Client',
            'last_name' => Str::random(6),
            'email' => 'client.'.Str::lower(Str::random(8)).'@example.com',
            'phone' => '+4477'.mt_rand(10000000, 99999999),
            'primary_location_id' => $ctx['location']->id,
            'is_active' => true,
        ], $attributes));

        foreach ($consents as $type => $granted) {
            ClientConsentRecord::withoutGlobalScopes()->create([
                'tenant_id' => $ctx['tenant']->id,
                'client_id' => $client->id,
                'consent_type' => $type,
                'granted' => $granted,
                'source' => ClientConsentRecord::SOURCE_STAFF_ENTRY,
                'recorded_at' => now(),
            ]);
        }

        return $client;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    protected function makeTemplate(array $ctx, string $category, string $channel): MarketingTemplate
    {
        return MarketingTemplate::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => Str::title($category).' '.Str::upper($channel),
            'category' => $category,
            'channel' => $channel,
            'subject' => $channel === MarketingChannel::EMAIL ? 'Hello {{client.first_name}}' : null,
            'body_text' => 'Hi {{client.first_name}}, from {{business.name}}.',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    protected function makeWorkflow(array $ctx, string $trigger, string $channel, ?string $templateId = null): MarketingAutomationWorkflow
    {
        return MarketingAutomationWorkflow::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => Str::title(str_replace('_', ' ', $trigger)).' Workflow',
            'slug' => Str::slug($trigger.'-'.Str::random(4)),
            'trigger_type' => $trigger,
            'channel' => $channel,
            'status' => MarketingWorkflowStatus::ACTIVE,
            'template_id' => $templateId,
            'delay_minutes' => 0,
            'allow_repeat' => false,
        ]);
    }

    public function test_workflow_crud_and_status_lifecycle(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $template = $this->makeTemplate($ctx, MarketingWorkflowTrigger::CLIENT_CREATED, MarketingChannel::EMAIL);

        $created = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/workflows', [
                'name' => 'Welcome Journey',
                'trigger_type' => MarketingWorkflowTrigger::CLIENT_CREATED,
                'channel' => MarketingChannel::EMAIL,
                'status' => MarketingWorkflowStatus::DRAFT,
                'template_id' => $template->id,
                'delay_minutes' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Welcome Journey')
            ->assertJsonPath('data.status', MarketingWorkflowStatus::DRAFT);

        $id = $created->json('data.id');

        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing_workflow.created']);

        $this->withTenantAuth($ctx['token'])
            ->putJson("/api/v1/admin/marketing/workflows/{$id}", ['name' => 'Welcome Journey v2'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Welcome Journey v2');

        $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/marketing/workflows/{$id}/status", ['status' => MarketingWorkflowStatus::ACTIVE])
            ->assertOk()
            ->assertJsonPath('data.status', MarketingWorkflowStatus::ACTIVE);

        $this->withTenantAuth($ctx['token'])
            ->putJson("/api/v1/admin/marketing/workflows/{$id}/steps", [
                'steps' => [
                    ['step_type' => 'send_message', 'template_id' => $template->id, 'delay_minutes' => 0],
                    ['step_type' => 'wait', 'delay_minutes' => 60],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('marketing_workflow_steps', ['workflow_id' => $id, 'position' => 0]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing_workflow.steps_updated']);
    }

    public function test_workflow_test_run_creates_execution_and_message(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $template = $this->makeTemplate($ctx, MarketingWorkflowTrigger::MANUAL, MarketingChannel::EMAIL);
        $workflow = $this->makeWorkflow($ctx, MarketingWorkflowTrigger::MANUAL, MarketingChannel::EMAIL, $template->id);
        $client = $this->makeClient($ctx, [], [ClientConsentRecord::TYPE_MARKETING_EMAIL => true]);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/marketing/workflows/{$workflow->id}/run-test", [
                'client_id' => $client->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', MarketingExecutionStatus::COMPLETED);

        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing_workflow.test_run']);
        $this->assertDatabaseHas('marketing_messages', [
            'client_id' => $client->id,
            'status' => MarketingMessageStatus::SENT,
        ]);
    }

    public function test_suppression_prevents_workflow_delivery(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $template = $this->makeTemplate($ctx, MarketingWorkflowTrigger::MANUAL, MarketingChannel::EMAIL);
        $workflow = $this->makeWorkflow($ctx, MarketingWorkflowTrigger::MANUAL, MarketingChannel::EMAIL, $template->id);
        $client = $this->makeClient($ctx, [], [ClientConsentRecord::TYPE_MARKETING_EMAIL => true]);

        app(MarketingSuppressionService::class)->create([
            'client_id' => $client->id,
            'channel' => MarketingChannel::EMAIL,
            'contact_value' => $client->email,
            'reason' => MarketingSuppressionReason::MANUAL,
        ]);

        $execution = app(MarketingWorkflowExecutionService::class)->createExecution(
            $workflow,
            $client,
            MarketingWorkflowTrigger::MANUAL,
            null,
            null,
            [],
            null,
            true,
        );

        $this->assertNotNull($execution);
        $this->assertSame(MarketingExecutionStatus::SKIPPED, $execution->status);
        $this->assertSame(MarketingEligibilityService::REASON_SUPPRESSED, $execution->failure_reason);
    }

    public function test_unsubscribe_creates_suppression_and_blocks_eligibility(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $client = $this->makeClient($ctx, [], [ClientConsentRecord::TYPE_MARKETING_EMAIL => true]);
        $message = MarketingMessage::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'channel' => MarketingChannel::EMAIL,
            'purpose' => 'workflow',
            'status' => MarketingMessageStatus::SENT,
            'recipient_address' => $client->email,
            'sent_at' => now(),
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/marketing/messages/{$message->id}/unsubscribe")
            ->assertOk()
            ->assertJsonPath('data.status', MarketingMessageStatus::UNSUBSCRIBED);

        $this->assertDatabaseHas('marketing_contact_suppressions', [
            'client_id' => $client->id,
            'channel' => MarketingChannel::EMAIL,
            'reason' => MarketingSuppressionReason::UNSUBSCRIBE,
            'is_active' => true,
        ]);

        $check = app(MarketingEligibilityService::class)->evaluate($client, MarketingChannel::EMAIL);
        $this->assertFalse($check['eligible']);
        $this->assertSame(MarketingEligibilityService::REASON_SUPPRESSED, $check['skipped_reason']);
    }

    public function test_appointment_no_show_trigger_creates_execution(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $template = $this->makeTemplate($ctx, MarketingWorkflowTrigger::APPOINTMENT_NO_SHOW, MarketingChannel::SMS);
        $this->makeWorkflow($ctx, MarketingWorkflowTrigger::APPOINTMENT_NO_SHOW, MarketingChannel::SMS, $template->id);

        $client = $this->makeClient($ctx, [], [ClientConsentRecord::TYPE_MARKETING_SMS => true]);
        $appointment = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $client->id,
            'team_member_id' => $ctx['teamMember']->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now(),
            'status' => Appointment::STATUS_CONFIRMED,
            'booking_source' => Appointment::SOURCE_ADMIN,
            'booking_reference' => 'NM-'.Str::upper(Str::random(6)),
            'deposit_status' => Appointment::DEPOSIT_NOT_REQUIRED,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/appointments/{$appointment->id}/status", [
                'status' => Appointment::STATUS_NO_SHOW,
                'no_show_reason' => 'Did not arrive',
            ])
            ->assertOk();

        $this->assertDatabaseHas('marketing_workflow_executions', [
            'client_id' => $client->id,
            'trigger_type' => MarketingWorkflowTrigger::APPOINTMENT_NO_SHOW,
        ]);
    }

    public function test_birthday_manual_run_creates_executions(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $template = $this->makeTemplate($ctx, MarketingWorkflowTrigger::BIRTHDAY, MarketingChannel::EMAIL);
        $this->makeWorkflow($ctx, MarketingWorkflowTrigger::BIRTHDAY, MarketingChannel::EMAIL, $template->id);

        $this->makeClient($ctx, [
            'date_of_birth' => now()->format('Y-m-d'),
        ], [ClientConsentRecord::TYPE_MARKETING_EMAIL => true]);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/automations/run-birthday')
            ->assertCreated()
            ->assertJsonPath('data.matched', 1);

        $this->assertDatabaseHas('marketing_workflow_executions', [
            'trigger_type' => MarketingWorkflowTrigger::BIRTHDAY,
        ]);
    }

    public function test_execution_cancel_and_process_endpoints(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $workflow = $this->makeWorkflow($ctx, MarketingWorkflowTrigger::MANUAL, MarketingChannel::EMAIL, null);
        $workflow->delay_minutes = 120;
        $workflow->save();

        $client = $this->makeClient($ctx, [], [ClientConsentRecord::TYPE_MARKETING_EMAIL => true]);

        $execution = MarketingWorkflowExecution::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'workflow_id' => $workflow->id,
            'client_id' => $client->id,
            'trigger_type' => MarketingWorkflowTrigger::MANUAL,
            'status' => MarketingExecutionStatus::QUEUED,
            'scheduled_for' => now()->addHours(2),
        ]);

        $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/marketing/executions/{$execution->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', MarketingExecutionStatus::CANCELLED);

        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing_workflow_execution.cancelled']);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/executions/process')
            ->assertOk()
            ->assertJsonStructure(['data' => ['processed', 'completed', 'failed']]);
    }

    public function test_message_operational_actions(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $client = $this->makeClient($ctx, [], [ClientConsentRecord::TYPE_MARKETING_EMAIL => true]);
        $message = MarketingMessage::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'channel' => MarketingChannel::EMAIL,
            'purpose' => 'workflow',
            'status' => MarketingMessageStatus::SENT,
            'recipient_address' => $client->email,
            'sent_at' => now(),
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/marketing/messages/{$message->id}/mark-delivered")
            ->assertOk()
            ->assertJsonPath('data.status', MarketingMessageStatus::DELIVERED);

        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing_message.delivered']);
    }

    public function test_automation_reporting_endpoints(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $this->makeWorkflow($ctx, MarketingWorkflowTrigger::CLIENT_CREATED, MarketingChannel::EMAIL, null);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/reporting/automations/summary')
            ->assertOk()
            ->assertJsonStructure(['data' => ['workflows', 'executions', 'messages', 'suppressions']]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/reporting/automations/executions')
            ->assertOk();

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/reporting/automations/messages')
            ->assertOk();

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/reporting/automations/suppressions')
            ->assertOk()
            ->assertJsonStructure(['data' => ['total', 'active', 'by_reason', 'by_channel']]);
    }

    public function test_tenant_isolation_on_workflows_and_suppressions(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $foreignWorkflow = MarketingAutomationWorkflow::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'name' => 'Foreign Workflow',
            'slug' => 'foreign',
            'trigger_type' => MarketingWorkflowTrigger::MANUAL,
            'channel' => MarketingChannel::EMAIL,
            'status' => MarketingWorkflowStatus::ACTIVE,
        ]);

        $foreignSuppression = MarketingContactSuppression::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'channel' => MarketingChannel::EMAIL,
            'contact_value' => 'foreign@example.com',
            'reason' => MarketingSuppressionReason::MANUAL,
            'source' => 'system',
            'is_active' => true,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson("/api/v1/admin/marketing/workflows/{$foreignWorkflow->id}")
            ->assertNotFound();

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/workflows')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_dispatch_permission_gate_for_workflow_test_run(): void
    {
        $ctx = $this->seedTenantContext(['marketing.view', 'marketing.manage']);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/workflows/'.Str::uuid().'/run-test', ['client_id' => Str::uuid()])
            ->assertForbidden();
    }

    public function test_granular_workflow_step_crud_reorder_and_archive(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $template = $this->makeTemplate($ctx, MarketingWorkflowTrigger::CLIENT_CREATED, MarketingChannel::EMAIL);

        $workflow = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/workflows', [
                'name' => 'Step CRUD Workflow',
                'trigger_type' => MarketingWorkflowTrigger::CLIENT_CREATED,
                'channel' => MarketingChannel::EMAIL,
                'status' => MarketingWorkflowStatus::DRAFT,
            ])
            ->assertCreated()
            ->json('data');

        $workflowId = $workflow['id'];

        $stepA = $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/marketing/workflows/{$workflowId}/steps", [
                'step_type' => 'send_message',
                'template_id' => $template->id,
                'delay_minutes' => 0,
            ])
            ->assertCreated()
            ->json('data.steps.0');

        $stepB = $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/marketing/workflows/{$workflowId}/steps", [
                'step_type' => 'wait',
                'delay_minutes' => 30,
            ])
            ->assertCreated()
            ->json('data.steps.1');

        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing_workflow_step.created']);

        $this->withTenantAuth($ctx['token'])
            ->putJson("/api/v1/admin/marketing/workflows/{$workflowId}/steps/{$stepB['id']}", [
                'delay_minutes' => 45,
            ])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing_workflow_step.updated']);

        $this->withTenantAuth($ctx['token'])
            ->putJson("/api/v1/admin/marketing/workflows/{$workflowId}/steps/reorder", [
                'step_ids' => [$stepB['id'], $stepA['id']],
            ])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing_workflow_step.reordered']);
        $this->assertDatabaseHas('marketing_workflow_steps', [
            'id' => $stepB['id'],
            'position' => 0,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/marketing/workflows/{$workflowId}/steps/{$stepA['id']}/archive")
            ->assertOk();

        $this->assertDatabaseMissing('marketing_workflow_steps', ['id' => $stepA['id']]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing_workflow_step.archived']);
    }

    public function test_workflow_executions_nested_list_endpoint(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $workflow = $this->makeWorkflow($ctx, MarketingWorkflowTrigger::MANUAL, MarketingChannel::EMAIL, null);
        $client = $this->makeClient($ctx, [], [ClientConsentRecord::TYPE_MARKETING_EMAIL => true]);

        MarketingWorkflowExecution::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'workflow_id' => $workflow->id,
            'client_id' => $client->id,
            'trigger_type' => MarketingWorkflowTrigger::MANUAL,
            'status' => MarketingExecutionStatus::COMPLETED,
            'completed_at' => now(),
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson("/api/v1/admin/marketing/workflows/{$workflow->id}/executions")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_suppression_lift_endpoint_and_audit(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $suppression = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/suppressions', [
                'channel' => MarketingChannel::EMAIL,
                'contact_value' => 'lift-me@example.com',
                'reason' => MarketingSuppressionReason::MANUAL,
            ])
            ->assertCreated()
            ->json('data');

        $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/marketing/suppressions/{$suppression['id']}/lift")
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.lifted_at', fn ($v) => $v !== null);

        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing_suppression.lifted']);
    }

    public function test_consent_granted_trigger_creates_execution(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $template = $this->makeTemplate($ctx, MarketingWorkflowTrigger::CONSENT_GRANTED, MarketingChannel::EMAIL);
        $this->makeWorkflow($ctx, MarketingWorkflowTrigger::CONSENT_GRANTED, MarketingChannel::EMAIL, $template->id);
        $client = $this->makeClient($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/clients/{$client->id}/consents", [
                'consent_type' => ClientConsentRecord::TYPE_MARKETING_EMAIL,
                'granted' => true,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('marketing_workflow_executions', [
            'client_id' => $client->id,
            'trigger_type' => MarketingWorkflowTrigger::CONSENT_GRANTED,
        ]);
    }

    public function test_spec_aligned_automation_reporting_routes(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $this->makeWorkflow($ctx, MarketingWorkflowTrigger::CLIENT_CREATED, MarketingChannel::EMAIL, null);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/reports/automation/summary')
            ->assertOk()
            ->assertJsonStructure(['data' => ['workflows', 'executions', 'messages', 'suppressions']]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/reports/automation/workflows')
            ->assertOk()
            ->assertJsonStructure(['data' => [['workflow_id', 'name', 'executions', 'messages']]]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/reports/automation/messages')
            ->assertOk();

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/reports/automation/suppressions')
            ->assertOk();
    }
}
