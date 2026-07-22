<?php

namespace App\Domains\Integrations\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Integrations\Enums\ProviderAttemptStatus;
use App\Domains\Integrations\Enums\ProviderCategory;
use App\Domains\Integrations\Enums\ProviderSourceDomain;
use App\Domains\Integrations\Http\Resources\ProviderDeliveryAttemptResource;
use App\Domains\Integrations\Services\IntegrationsScopeValidator;
use App\Domains\Integrations\Services\ProviderAttemptQueryService;
use App\Domains\Integrations\Services\ProviderDispatchService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProviderDeliveryAttemptController extends Controller
{
    public function __construct(
        private readonly ProviderAttemptQueryService $attempts,
        private readonly ProviderDispatchService $dispatch,
        private readonly IntegrationsScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::in(ProviderCategory::all())],
            'source_domain' => ['nullable', Rule::in(ProviderSourceDomain::all())],
            'status' => ['nullable', Rule::in(ProviderAttemptStatus::all())],
            'provider_account_id' => ['nullable', 'uuid'],
            'client_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return ApiResponse::success(
            ProviderDeliveryAttemptResource::collection($this->attempts->list($filters)),
        );
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new ProviderDeliveryAttemptResource($this->attempts->find($id)));
    }

    public function retry(string $id): JsonResponse
    {
        $attempt = $this->scope->findDeliveryAttempt($id);
        $result = $this->dispatch->retry($attempt);

        return ApiResponse::success([
            'attempt' => new ProviderDeliveryAttemptResource($this->attempts->find($result->providerDeliveryAttemptId)),
            'result' => [
                'provider_reference' => $result->providerReference,
                'status' => $result->status,
                'simulated' => $result->simulated,
            ],
        ], 'Provider attempt retried');
    }
}
