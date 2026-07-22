<?php

namespace App\Domains\Memberships\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Memberships\Enums\MembershipBillingFrequency;
use App\Domains\Memberships\Enums\MembershipPlanStatus;
use App\Domains\Memberships\Http\Resources\MembershipPlanResource;
use App\Domains\Memberships\Services\MembershipPlanService;
use App\Domains\Memberships\Services\MembershipScopeValidator;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MembershipPlanController extends Controller
{
    public function __construct(
        private readonly MembershipPlanService $planService,
        private readonly MembershipScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(MembershipPlanStatus::all())],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return ApiResponse::success(MembershipPlanResource::collection($this->planService->list($filters)));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new MembershipPlanResource($this->planService->find($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(MembershipPlanStatus::all())],
            'billing_frequency' => ['nullable', Rule::in(MembershipBillingFrequency::all())],
            'price_cents' => ['nullable', 'integer', 'min:0'],
            'joining_fee_cents' => ['nullable', 'integer', 'min:0'],
            'included_wallet_credit_cents' => ['nullable', 'integer', 'min:0'],
            'included_loyalty_points' => ['nullable', 'integer', 'min:0'],
            'included_entitlement_quantity' => ['nullable', 'integer', 'min:0'],
            'auto_renew' => ['nullable', 'boolean'],
            'grace_period_days' => ['nullable', 'integer', 'min:0'],
            'is_public' => ['nullable', 'boolean'],
            'applies_to_all_locations' => ['nullable', 'boolean'],
            'location_ids' => ['nullable', 'array'],
            'location_ids.*' => ['uuid'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $plan = $this->planService->create($data);

        return ApiResponse::success(new MembershipPlanResource($plan), 'Plan created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in(MembershipPlanStatus::all())],
            'billing_frequency' => ['nullable', Rule::in(MembershipBillingFrequency::all())],
            'price_cents' => ['sometimes', 'integer', 'min:0'],
            'joining_fee_cents' => ['nullable', 'integer', 'min:0'],
            'included_wallet_credit_cents' => ['nullable', 'integer', 'min:0'],
            'included_loyalty_points' => ['nullable', 'integer', 'min:0'],
            'included_entitlement_quantity' => ['nullable', 'integer', 'min:0'],
            'auto_renew' => ['nullable', 'boolean'],
            'grace_period_days' => ['nullable', 'integer', 'min:0'],
            'is_public' => ['nullable', 'boolean'],
            'applies_to_all_locations' => ['nullable', 'boolean'],
            'location_ids' => ['nullable', 'array'],
            'location_ids.*' => ['uuid'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $plan = $this->planService->update($this->scope->findPlan($id), $data);

        return ApiResponse::success(new MembershipPlanResource($plan), 'Plan updated');
    }

    public function archive(string $id): JsonResponse
    {
        $plan = $this->planService->archive($this->scope->findPlan($id));

        return ApiResponse::success(new MembershipPlanResource($plan), 'Plan archived');
    }
}
