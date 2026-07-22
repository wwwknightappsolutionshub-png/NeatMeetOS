<?php

namespace App\Domains\Memberships\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Memberships\Enums\ClientMembershipStatus;
use App\Domains\Memberships\Http\Resources\ClientMembershipResource;
use App\Domains\Memberships\Services\ClientMembershipService;
use App\Domains\Memberships\Services\MembershipScopeValidator;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientMembershipController extends Controller
{
    public function __construct(
        private readonly ClientMembershipService $membershipService,
        private readonly MembershipScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'client_id' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::in(ClientMembershipStatus::all())],
        ]);

        return ApiResponse::success(ClientMembershipResource::collection($this->membershipService->list($filters)));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new ClientMembershipResource($this->membershipService->find($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'uuid'],
            'membership_plan_id' => ['required', 'uuid'],
            'status' => ['nullable', Rule::in(ClientMembershipStatus::all())],
            'started_at' => ['nullable', 'date'],
            'trial_ends_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $membership = $this->membershipService->assign($data, $teamMember?->id);

        return ApiResponse::success(new ClientMembershipResource($membership), 'Subscription created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(ClientMembershipStatus::all())],
            'notes' => ['nullable', 'string', 'max:5000'],
            'cancel_at_period_end' => ['nullable', 'boolean'],
        ]);

        $membership = $this->membershipService->update($this->scope->findClientMembership($id), $data);

        return ApiResponse::success(new ClientMembershipResource($membership), 'Subscription updated');
    }

    public function pause(string $id): JsonResponse
    {
        $membership = $this->membershipService->pause($this->scope->findClientMembership($id));

        return ApiResponse::success(new ClientMembershipResource($membership), 'Subscription paused');
    }

    public function resume(string $id): JsonResponse
    {
        $membership = $this->membershipService->resume($this->scope->findClientMembership($id));

        return ApiResponse::success(new ClientMembershipResource($membership), 'Subscription resumed');
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'at_period_end' => ['nullable', 'boolean'],
        ]);

        $membership = $this->membershipService->cancel(
            $this->scope->findClientMembership($id),
            (bool) ($data['at_period_end'] ?? false),
        );

        return ApiResponse::success(new ClientMembershipResource($membership), 'Subscription cancelled');
    }
}
