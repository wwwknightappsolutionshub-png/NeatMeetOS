<?php

namespace App\Domains\Marketing\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Marketing\Enums\MarketingCampaignStatus;
use App\Domains\Marketing\Enums\MarketingCampaignType;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingTriggerType;
use App\Domains\Marketing\Http\Resources\MarketingCampaignResource;
use App\Domains\Marketing\Services\MarketingCampaignService;
use App\Domains\Marketing\Services\MarketingScopeValidator;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketingCampaignController extends Controller
{
    public function __construct(
        private readonly MarketingCampaignService $campaignService,
        private readonly MarketingScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(MarketingCampaignStatus::all())],
            'campaign_type' => ['nullable', Rule::in(MarketingCampaignType::all())],
            'channel' => ['nullable', Rule::in(MarketingChannel::all())],
            'trigger_type' => ['nullable', Rule::in(MarketingTriggerType::all())],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return ApiResponse::success(MarketingCampaignResource::collection($this->campaignService->list($filters)));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new MarketingCampaignResource($this->campaignService->find($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'campaign_type' => ['required', Rule::in(MarketingCampaignType::all())],
            'trigger_type' => ['nullable', Rule::in(MarketingTriggerType::all())],
            'channel' => ['required', Rule::in(MarketingChannel::all())],
            'status' => ['nullable', Rule::in(MarketingCampaignStatus::all())],
            'template_id' => ['nullable', 'uuid'],
            'audience_name' => ['nullable', 'string', 'max:255'],
            'audience_rules' => ['nullable', 'array'],
            'location_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $campaign = $this->campaignService->create($data);

        return ApiResponse::success(new MarketingCampaignResource($campaign), 'Campaign created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'campaign_type' => ['sometimes', Rule::in(MarketingCampaignType::all())],
            'trigger_type' => ['nullable', Rule::in(MarketingTriggerType::all())],
            'channel' => ['sometimes', Rule::in(MarketingChannel::all())],
            'template_id' => ['nullable', 'uuid'],
            'audience_name' => ['nullable', 'string', 'max:255'],
            'audience_rules' => ['nullable', 'array'],
            'location_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $campaign = $this->campaignService->update($this->scope->findCampaign($id), $data);

        return ApiResponse::success(new MarketingCampaignResource($campaign), 'Campaign updated');
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(MarketingCampaignStatus::all())],
        ]);

        $campaign = $this->campaignService->updateStatus($this->scope->findCampaign($id), $data['status']);

        return ApiResponse::success(new MarketingCampaignResource($campaign), 'Campaign status updated');
    }
}
