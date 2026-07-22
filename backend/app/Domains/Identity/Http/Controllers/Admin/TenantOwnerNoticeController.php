<?php

namespace App\Domains\Identity\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Models\TenantOwnerNotice;
use App\Shared\Support\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantOwnerNoticeController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantContext->id();
        $userId = $request->user()?->id;

        $items = TenantOwnerNotice::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($userId) {
                $q->whereNull('user_id');
                if ($userId) {
                    $q->orWhere('user_id', $userId);
                }
            })
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn (TenantOwnerNotice $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'image_url' => $n->image_url,
                'href' => $n->href,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])
            ->all();

        return ApiResponse::success([
            'items' => $items,
            'unread_count' => collect($items)->whereNull('read_at')->count(),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notice = TenantOwnerNotice::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->findOrFail($id);

        $notice->forceFill(['read_at' => now()])->save();

        return ApiResponse::success(['id' => $notice->id, 'read_at' => $notice->read_at?->toIso8601String()]);
    }
}
