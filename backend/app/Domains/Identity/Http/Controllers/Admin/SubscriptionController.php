<?php

namespace App\Domains\Identity\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Http\Resources\SubscriptionPlanResource;
use App\Domains\Identity\Http\Resources\TenantSubscriptionResource;
use App\Domains\Identity\Services\SubscriptionService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptionService) {}

    public function show(): JsonResponse
    {
        $subscription = $this->subscriptionService->getCurrent();

        return ApiResponse::success(new TenantSubscriptionResource($subscription->load('plan')));
    }

    public function plans(): JsonResponse
    {
        return ApiResponse::success(
            SubscriptionPlanResource::collection($this->subscriptionService->listAvailablePlans()),
        );
    }

    public function changePlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_slug' => ['required', 'string', 'in:basic,pro,diamond'],
        ]);

        $subscription = $this->subscriptionService->changePlan($data['plan_slug']);

        return ApiResponse::success(
            new TenantSubscriptionResource($subscription->load('plan')),
            'Plan updated',
        );
    }
}
