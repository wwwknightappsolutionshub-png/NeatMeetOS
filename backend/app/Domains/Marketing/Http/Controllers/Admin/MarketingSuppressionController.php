<?php

namespace App\Domains\Marketing\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingSuppressionReason;
use App\Domains\Marketing\Http\Resources\MarketingContactSuppressionResource;
use App\Domains\Marketing\Services\MarketingScopeValidator;
use App\Domains\Marketing\Services\MarketingSuppressionService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketingSuppressionController extends Controller
{
    public function __construct(
        private readonly MarketingSuppressionService $suppressionService,
        private readonly MarketingScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'channel' => ['nullable', Rule::in(MarketingChannel::all())],
            'reason' => ['nullable', Rule::in(MarketingSuppressionReason::all())],
            'client_id' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return ApiResponse::success(MarketingContactSuppressionResource::collection($this->suppressionService->list($filters)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'uuid'],
            'channel' => ['required', Rule::in(MarketingChannel::all())],
            'contact_value' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', Rule::in(MarketingSuppressionReason::all())],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $data['created_by_team_member_id'] = $teamMember?->id;
        $data['source'] = 'staff_action';

        $suppression = $this->suppressionService->create($data);

        return ApiResponse::success(new MarketingContactSuppressionResource($suppression), 'Suppression created', 201);
    }

    public function lift(string $id): JsonResponse
    {
        $suppression = $this->suppressionService->lift($this->scope->findSuppression($id));

        return ApiResponse::success(new MarketingContactSuppressionResource($suppression), 'Suppression lifted');
    }

    public function deactivate(string $id): JsonResponse
    {
        $suppression = $this->suppressionService->deactivate($this->scope->findSuppression($id));

        return ApiResponse::success(new MarketingContactSuppressionResource($suppression), 'Suppression deactivated');
    }

    public function reactivate(string $id): JsonResponse
    {
        $suppression = $this->suppressionService->reactivate($this->scope->findSuppression($id));

        return ApiResponse::success(new MarketingContactSuppressionResource($suppression), 'Suppression reactivated');
    }
}
