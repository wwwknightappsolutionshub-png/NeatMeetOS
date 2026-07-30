<?php

namespace App\Domains\AiHairstyle\Http\Controllers\Admin;

use App\Domains\AiHairstyle\Services\AdminAiHairstyleService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Services\TenantEntitlementService;
use App\Shared\Support\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AdminAiHairstyleController extends Controller
{
    public function __construct(
        private readonly AdminAiHairstyleService $admin,
        private readonly TenantEntitlementService $entitlements,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(): JsonResponse
    {
        if ($deny = $this->featureDenied()) {
            return $deny;
        }

        $items = $this->admin->listSubmitted()
            ->loadMissing(['previews', 'client'])
            ->map(fn ($session) => $this->admin->toAdminArray($session))
            ->values()
            ->all();

        return ApiResponse::success(['items' => $items]);
    }

    public function accept(string $id): JsonResponse
    {
        if ($deny = $this->featureDenied()) {
            return $deny;
        }

        try {
            $session = $this->admin->accept($id, auth()->id());
        } catch (ValidationException $e) {
            return ApiResponse::error(
                collect($e->errors())->flatten()->first() ?: 'Validation failed',
                422,
                $e->errors(),
            );
        }

        return ApiResponse::success(
            $this->admin->toAdminArray($session->loadMissing(['previews', 'client'])),
            'Look accepted',
        );
    }

    public function decline(string $id): JsonResponse
    {
        if ($deny = $this->featureDenied()) {
            return $deny;
        }

        try {
            $session = $this->admin->decline($id, auth()->id());
        } catch (ValidationException $e) {
            return ApiResponse::error(
                collect($e->errors())->flatten()->first() ?: 'Validation failed',
                422,
                $e->errors(),
            );
        }

        return ApiResponse::success(
            $this->admin->toAdminArray($session->loadMissing(['previews', 'client'])),
            'Look declined',
        );
    }

    private function featureDenied(): ?JsonResponse
    {
        $tenant = $this->tenantContext->get();
        if ($this->entitlements->isEnabled($tenant, 'ai_hairstyle')) {
            return null;
        }

        return ApiResponse::error(
            'AI Hairstyle Preview is not available on this plan.',
            403,
            null,
            'module_upgrade_required',
            $this->entitlements->upgradeRequiredPayload('ai_hairstyle'),
        );
    }
}
