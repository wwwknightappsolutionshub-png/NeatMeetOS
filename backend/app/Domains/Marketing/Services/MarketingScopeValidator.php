<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Booking\Models\BookableService;
use App\Domains\Booking\Services\BookingScopeValidator;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Workspace;
use App\Domains\Marketing\Models\MarketingAudience;
use App\Domains\Marketing\Models\MarketingAutomationWorkflow;
use App\Domains\Marketing\Models\MarketingCampaign;
use App\Domains\Marketing\Models\MarketingContactSuppression;
use App\Domains\Marketing\Models\MarketingMessage;
use App\Domains\Marketing\Models\MarketingRun;
use App\Domains\Marketing\Models\MarketingTemplate;
use App\Domains\Marketing\Models\MarketingWorkflowExecution;
use App\Domains\Marketing\Models\MarketingWorkflowStep;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Tenant scoping + resource resolution for the Marketing domain.
 *
 * Wraps the shared BookingScopeValidator so Marketing services reuse the same
 * tenant guarantees for cross-domain entities (clients, locations, team members)
 * while adding finders for Marketing aggregates.
 */
class MarketingScopeValidator
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BookingScopeValidator $bookingScope,
    ) {}

    public function tenantId(): string
    {
        return $this->bookingScope->tenantId();
    }

    public function assertTenantModel(object $model): void
    {
        if (isset($model->tenant_id) && $model->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['resource' => ['Resource not found.']]);
        }
    }

    public function findClient(string $id): Client
    {
        return $this->bookingScope->findClient($id);
    }

    public function findTeamMember(string $id): TeamMember
    {
        return $this->bookingScope->findTeamMember($id);
    }

    public function findLocation(string $id): Location
    {
        return $this->bookingScope->findLocation($id);
    }

    public function findWorkspace(?string $id): ?Workspace
    {
        return $this->bookingScope->findWorkspace($id);
    }

    public function findBookableService(string $id): BookableService
    {
        return $this->bookingScope->findBookableService($id);
    }

    public function findTemplate(string $id): MarketingTemplate
    {
        $template = MarketingTemplate::query()->findOrFail($id);
        $this->assertTenantModel($template);

        return $template;
    }

    public function findCampaign(string $id): MarketingCampaign
    {
        $campaign = MarketingCampaign::query()->findOrFail($id);
        $this->assertTenantModel($campaign);

        return $campaign;
    }

    public function findAudience(string $id): MarketingAudience
    {
        $audience = MarketingAudience::query()->findOrFail($id);
        $this->assertTenantModel($audience);

        return $audience;
    }

    public function findRun(string $id): MarketingRun
    {
        $run = MarketingRun::query()->findOrFail($id);
        $this->assertTenantModel($run);

        return $run;
    }

    public function findMessage(string $id): MarketingMessage
    {
        $message = MarketingMessage::query()->findOrFail($id);
        $this->assertTenantModel($message);

        return $message;
    }

    public function findWorkflow(string $id): MarketingAutomationWorkflow
    {
        $workflow = MarketingAutomationWorkflow::query()->findOrFail($id);
        $this->assertTenantModel($workflow);

        return $workflow;
    }

    public function findWorkflowStep(MarketingAutomationWorkflow $workflow, string $stepId): MarketingWorkflowStep
    {
        $step = MarketingWorkflowStep::query()
            ->where('workflow_id', $workflow->id)
            ->findOrFail($stepId);
        $this->assertTenantModel($step);

        return $step;
    }

    public function findExecution(string $id): MarketingWorkflowExecution
    {
        $execution = MarketingWorkflowExecution::query()->findOrFail($id);
        $this->assertTenantModel($execution);

        return $execution;
    }

    public function findSuppression(string $id): MarketingContactSuppression
    {
        $suppression = MarketingContactSuppression::query()->findOrFail($id);
        $this->assertTenantModel($suppression);

        return $suppression;
    }

    /**
     * Ensure every location id in the list belongs to the current tenant.
     *
     * @param  array<int, string>  $locationIds
     * @return array<int, string>
     */
    public function assertLocationIds(array $locationIds): array
    {
        $locationIds = array_values(array_unique(array_filter($locationIds)));

        if ($locationIds === []) {
            return [];
        }

        $valid = Location::query()
            ->where('tenant_id', $this->tenantId())
            ->whereIn('id', $locationIds)
            ->pluck('id')
            ->all();

        if (count($valid) !== count($locationIds)) {
            throw ValidationException::withMessages([
                'location_ids' => ['One or more locations do not belong to this tenant.'],
            ]);
        }

        return $valid;
    }
}
