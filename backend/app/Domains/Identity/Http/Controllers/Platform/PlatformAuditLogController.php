<?php

namespace App\Domains\Identity\Http\Controllers\Platform;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Http\Resources\AuditLogResource;
use App\Domains\Identity\Services\AuditLogService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformAuditLogController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'tenant_id' => ['nullable', 'uuid'],
            'entity_type' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'actor_id' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($filters['per_page'] ?? 25);
        unset($filters['per_page']);

        $paginator = $this->auditLogService->listForPlatform($filters, $perPage);

        $items = collect($paginator->items())->map(function ($log) {
            $log->actor_name = $this->auditLogService->resolveActorName($log->actor_id);
            $log->tenant_summary = $this->auditLogService->resolveTenantSummary($log->tenant_id);

            return new AuditLogResource($log);
        });

        return ApiResponse::success([
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $log = $this->auditLogService->findForPlatform($id);
        $log->actor_name = $this->auditLogService->resolveActorName($log->actor_id);
        $log->tenant_summary = $this->auditLogService->resolveTenantSummary($log->tenant_id);

        return ApiResponse::success(new AuditLogResource($log));
    }
}
