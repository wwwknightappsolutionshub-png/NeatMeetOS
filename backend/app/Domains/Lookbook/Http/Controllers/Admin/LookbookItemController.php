<?php

namespace App\Domains\Lookbook\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Lookbook\Http\Resources\LookbookItemResource;
use App\Domains\Lookbook\Services\LookbookItemService;
use App\Shared\Support\ApiResponse;
use App\Shared\Support\PublicStorageUrl;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookbookItemController extends Controller
{
    public function __construct(
        private readonly LookbookItemService $items,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'is_published' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success(
            LookbookItemResource::collection($this->items->list($filters))->resolve(),
        );
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'image_url' => ['sometimes', 'string', 'max:2048'],
            'title' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $item = $this->items->update($this->items->find($id), $data);

        return ApiResponse::success(new LookbookItemResource($item), 'Lookbook item updated');
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'uuid'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $items = $this->items->reorder($data['items']);

        return ApiResponse::success(
            LookbookItemResource::collection($items)->resolve(),
            'Lookbook items reordered',
        );
    }

    public function replaceImage(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'max:8192'],
        ]);

        $tenantId = $this->tenantContext->id() ?? 'shared';
        $path = $data['image']->store('lookbook/'.$tenantId, 'public');
        $url = PublicStorageUrl::fromDiskPath($path);

        $item = $this->items->replaceImage($this->items->find($id), $url);

        return ApiResponse::success([
            'url' => $url,
            'path' => $path,
            'item' => (new LookbookItemResource($item))->resolve(),
        ], 'Lookbook image replaced');
    }

    public function hide(string $id): JsonResponse
    {
        $item = $this->items->hide($this->items->find($id));

        return ApiResponse::success(new LookbookItemResource($item), 'Lookbook item hidden');
    }

    public function publish(string $id): JsonResponse
    {
        $item = $this->items->publish($this->items->find($id));

        return ApiResponse::success(new LookbookItemResource($item), 'Lookbook item published');
    }
}
