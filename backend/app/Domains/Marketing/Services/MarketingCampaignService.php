<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Marketing\Enums\MarketingCampaignStatus;
use App\Domains\Marketing\Enums\MarketingCampaignType;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingTriggerType;
use App\Domains\Marketing\Models\MarketingCampaign;
use App\Shared\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketingCampaignService
{
    public function __construct(
        private readonly MarketingScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = MarketingCampaign::query()
            ->with(['template', 'location', 'createdBy'])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['campaign_type'])) {
            $query->where('campaign_type', $filters['campaign_type']);
        }

        if (! empty($filters['trigger_type'])) {
            $query->where('trigger_type', $filters['trigger_type']);
        }

        if (! empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        return $query->paginate($perPage);
    }

    public function find(string $id): MarketingCampaign
    {
        $campaign = $this->scope->findCampaign($id);

        return $campaign->load(['template', 'location', 'createdBy']);
    }

    public function create(array $data): MarketingCampaign
    {
        $tenantId = $this->scope->tenantId();
        $this->validateEnums($data);
        $this->validateReferences($data);

        return DB::transaction(function () use ($tenantId, $data) {
            $campaign = MarketingCampaign::query()->create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'campaign_type' => $data['campaign_type'],
                'trigger_type' => $data['trigger_type'] ?? null,
                'channel' => $data['channel'],
                'status' => $data['status'] ?? MarketingCampaignStatus::DRAFT,
                'template_id' => $data['template_id'] ?? null,
                'audience_name' => $data['audience_name'] ?? null,
                'audience_rules_json' => $data['audience_rules'] ?? $data['audience_rules_json'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                'created_by_team_member_id' => $data['created_by_team_member_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->auditLogger->log('marketing_campaign.created', $campaign, null, $campaign->only([
                'name', 'campaign_type', 'trigger_type', 'channel', 'status',
            ]));

            return $campaign->fresh(['template', 'location', 'createdBy']);
        });
    }

    public function update(MarketingCampaign $campaign, array $data): MarketingCampaign
    {
        $this->scope->assertTenantModel($campaign);
        $this->validateEnums($data, false);
        $this->validateReferences($data);

        if (array_key_exists('audience_rules', $data)) {
            $data['audience_rules_json'] = $data['audience_rules'];
        }

        $fields = array_intersect_key($data, array_flip([
            'name', 'campaign_type', 'trigger_type', 'channel', 'status',
            'template_id', 'audience_name', 'audience_rules_json', 'location_id', 'notes',
        ]));

        return DB::transaction(function () use ($campaign, $fields) {
            $old = $campaign->only(array_keys($fields));
            $campaign->fill($fields);
            $campaign->save();

            $this->auditLogger->log('marketing_campaign.updated', $campaign, $old, $campaign->only(array_keys($fields)));

            return $campaign->fresh(['template', 'location', 'createdBy']);
        });
    }

    public function updateStatus(MarketingCampaign $campaign, string $status): MarketingCampaign
    {
        $this->scope->assertTenantModel($campaign);

        if (! in_array($status, MarketingCampaignStatus::all(), true)) {
            throw ValidationException::withMessages(['status' => ['Invalid campaign status.']]);
        }

        return DB::transaction(function () use ($campaign, $status) {
            $old = ['status' => $campaign->status];
            $campaign->status = $status;
            $campaign->save();

            $this->auditLogger->log('marketing_campaign.status_updated', $campaign, $old, ['status' => $status]);

            return $campaign->fresh(['template', 'location', 'createdBy']);
        });
    }

    private function validateEnums(array $data, bool $requireType = true): void
    {
        if ($requireType || array_key_exists('campaign_type', $data)) {
            if (! in_array($data['campaign_type'] ?? null, MarketingCampaignType::all(), true)) {
                throw ValidationException::withMessages(['campaign_type' => ['Invalid campaign type.']]);
            }
        }

        if ($requireType || array_key_exists('channel', $data)) {
            if (! in_array($data['channel'] ?? null, MarketingChannel::all(), true)) {
                throw ValidationException::withMessages(['channel' => ['Invalid marketing channel.']]);
            }
        }

        if (! empty($data['trigger_type']) && ! in_array($data['trigger_type'], MarketingTriggerType::all(), true)) {
            throw ValidationException::withMessages(['trigger_type' => ['Invalid trigger type.']]);
        }

        if (! empty($data['status']) && ! in_array($data['status'], MarketingCampaignStatus::all(), true)) {
            throw ValidationException::withMessages(['status' => ['Invalid campaign status.']]);
        }
    }

    private function validateReferences(array $data): void
    {
        if (! empty($data['template_id'])) {
            $this->scope->findTemplate($data['template_id']);
        }

        if (! empty($data['location_id'])) {
            $this->scope->findLocation($data['location_id']);
        }
    }
}
