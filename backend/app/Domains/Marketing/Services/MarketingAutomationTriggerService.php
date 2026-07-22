<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Crm\Models\Client;
use App\Domains\Marketing\Enums\MarketingWorkflowStatus;
use App\Domains\Marketing\Enums\MarketingWorkflowTrigger;
use App\Domains\Marketing\Models\MarketingAutomationWorkflow;
use App\Domains\Marketing\Models\MarketingWorkflowExecution;
use App\Domains\Memberships\Enums\ClientMembershipStatus;
use App\Domains\Memberships\Models\ClientMembership;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\Log;

/**
 * Internal bridge for firing marketing workflow automations from domain events.
 *
 * Synchronous and admin-safe for Module 10B. Architected so a queue worker
 * can replace the synchronous process path in a later step.
 */
class MarketingAutomationTriggerService
{
    public function __construct(
        private readonly MarketingScopeValidator $scope,
        private readonly MarketingWorkflowExecutionService $executionService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function fireClientCreated(Client $client): array
    {
        return $this->fireForTrigger(
            MarketingWorkflowTrigger::CLIENT_CREATED,
            $client,
            Client::class,
            $client->id,
        );
    }

    public function fireConsentGranted(Client $client): array
    {
        return $this->fireForTrigger(
            MarketingWorkflowTrigger::CONSENT_GRANTED,
            $client,
            Client::class,
            $client->id,
        );
    }

    public function fireConsentWithdrawn(Client $client): array
    {
        return $this->fireForTrigger(
            MarketingWorkflowTrigger::CONSENT_WITHDRAWN,
            $client,
            Client::class,
            $client->id,
        );
    }

    public function fireAppointmentCompleted(Appointment $appointment): array
    {
        if ($appointment->client_id === null) {
            return [];
        }

        $client = $this->scope->findClient($appointment->client_id);

        return $this->fireForTrigger(
            MarketingWorkflowTrigger::APPOINTMENT_COMPLETED,
            $client,
            Appointment::class,
            $appointment->id,
            ['appointment_id' => $appointment->id],
        );
    }

    public function fireAppointmentNoShow(Appointment $appointment): array
    {
        if ($appointment->client_id === null) {
            return [];
        }

        $client = $this->scope->findClient($appointment->client_id);

        return $this->fireForTrigger(
            MarketingWorkflowTrigger::APPOINTMENT_NO_SHOW,
            $client,
            Appointment::class,
            $appointment->id,
            ['appointment_id' => $appointment->id],
        );
    }

    public function fireMembershipStarted(ClientMembership $membership): array
    {
        if (! in_array($membership->status, [ClientMembershipStatus::ACTIVE, ClientMembershipStatus::TRIALING], true)) {
            return [];
        }

        $client = $this->scope->findClient($membership->client_id);

        return $this->fireForTrigger(
            MarketingWorkflowTrigger::MEMBERSHIP_STARTED,
            $client,
            ClientMembership::class,
            $membership->id,
            ['membership_id' => $membership->id],
        );
    }

    public function fireMembershipCancelled(ClientMembership $membership, bool $atPeriodEnd = false): array
    {
        if ($atPeriodEnd || $membership->status !== ClientMembershipStatus::CANCELLED) {
            return [];
        }

        $client = $this->scope->findClient($membership->client_id);

        return $this->fireForTrigger(
            MarketingWorkflowTrigger::MEMBERSHIP_CANCELLED,
            $client,
            ClientMembership::class,
            $membership->id,
            ['membership_id' => $membership->id],
        );
    }

    /**
     * Admin/manual birthday automation run for eligible clients today.
     *
     * @return array{matched: int, executions: array<int, MarketingWorkflowExecution>}
     */
    public function runBirthdayAutomations(?string $teamMemberId = null): array
    {
        $workflows = MarketingAutomationWorkflow::query()
            ->where('tenant_id', $this->scope->tenantId())
            ->where('trigger_type', MarketingWorkflowTrigger::BIRTHDAY)
            ->where('status', MarketingWorkflowStatus::ACTIVE)
            ->with('steps.template', 'template')
            ->get();

        $executions = [];
        $matched = 0;

        $clients = Client::query()
            ->where('tenant_id', $this->scope->tenantId())
            ->where('is_active', true)
            ->whereNotNull('date_of_birth')
            ->whereMonth('date_of_birth', now()->month)
            ->whereDay('date_of_birth', now()->day)
            ->get();

        foreach ($workflows as $workflow) {
            foreach ($clients as $client) {
                $matched++;
                $execution = $this->safeCreateExecution(
                    $workflow,
                    $client,
                    MarketingWorkflowTrigger::BIRTHDAY,
                    null,
                    null,
                    ['birthday_run' => now()->toDateString()],
                    $teamMemberId,
                );
                if ($execution !== null) {
                    $executions[] = $execution;
                }
            }
        }

        return ['matched' => $matched, 'executions' => $executions];
    }

    /**
     * Admin test-run: execute a workflow for a specific client.
     */
    public function testRun(
        MarketingAutomationWorkflow $workflow,
        Client $client,
        ?string $teamMemberId = null,
    ): MarketingWorkflowExecution {
        $this->scope->assertTenantModel($workflow);
        $this->scope->assertTenantModel($client);

        $execution = $this->executionService->createExecution(
            $workflow,
            $client,
            MarketingWorkflowTrigger::MANUAL,
            Client::class,
            $client->id,
            ['test_run' => true],
            $teamMemberId,
            true,
        );

        if ($execution === null) {
            throw new \RuntimeException('Test run could not create an execution for this client.');
        }

        $this->auditLogger->log('marketing_workflow.test_run', $workflow, null, [
            'client_id' => $client->id,
            'execution_id' => $execution->id,
        ]);

        return $execution->fresh(['steps.message', 'messages', 'client']);
    }

    /**
     * @return array<int, MarketingWorkflowExecution>
     */
    private function fireForTrigger(
        string $triggerType,
        Client $client,
        ?string $referenceType = null,
        ?string $referenceId = null,
        array $context = [],
    ): array {
        $workflows = MarketingAutomationWorkflow::query()
            ->where('tenant_id', $this->scope->tenantId())
            ->where('trigger_type', $triggerType)
            ->where('status', MarketingWorkflowStatus::ACTIVE)
            ->with('steps.template', 'template')
            ->get();

        $executions = [];

        foreach ($workflows as $workflow) {
            $execution = $this->safeCreateExecution(
                $workflow,
                $client,
                $triggerType,
                $referenceType,
                $referenceId,
                $context,
            );
            if ($execution !== null) {
                $executions[] = $execution;
            }
        }

        return $executions;
    }

    private function safeCreateExecution(
        MarketingAutomationWorkflow $workflow,
        Client $client,
        string $triggerType,
        ?string $referenceType,
        ?string $referenceId,
        array $context,
        ?string $teamMemberId = null,
    ): ?MarketingWorkflowExecution {
        try {
            return $this->executionService->createExecution(
                $workflow,
                $client,
                $triggerType,
                $referenceType,
                $referenceId,
                $context,
                $teamMemberId,
                true,
            );
        } catch (\Throwable $e) {
            Log::warning('Marketing workflow execution failed', [
                'workflow_id' => $workflow->id,
                'client_id' => $client->id,
                'trigger' => $triggerType,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
