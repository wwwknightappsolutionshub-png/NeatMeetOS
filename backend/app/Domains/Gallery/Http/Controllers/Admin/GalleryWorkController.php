<?php

namespace App\Domains\Gallery\Http\Controllers\Admin;

use App\Domains\Gallery\Http\Resources\GalleryWorkResource;
use App\Domains\Gallery\Services\GalleryWorkService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use App\Shared\Support\PublicStorageUrl;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GalleryWorkController extends Controller
{
    public function __construct(
        private readonly GalleryWorkService $works,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'is_published' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success(
            GalleryWorkResource::collection($this->works->list($filters))->resolve(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image_url' => ['required', 'string', 'max:2048'],
            'caption' => ['nullable', 'string', 'max:255'],
            'service_tag' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $work = $this->works->create($data);

        return ApiResponse::success(new GalleryWorkResource($work), 'Gallery work created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'image_url' => ['sometimes', 'string', 'max:2048'],
            'caption' => ['nullable', 'string', 'max:255'],
            'service_tag' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $work = $this->works->update($this->works->find($id), $data);

        return ApiResponse::success(new GalleryWorkResource($work), 'Gallery work updated');
    }

    public function destroy(string $id): JsonResponse
    {
        $this->works->delete($this->works->find($id));

        return ApiResponse::success(null, 'Gallery work deleted');
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'uuid'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $works = $this->works->reorder($data['items']);

        return ApiResponse::success(
            GalleryWorkResource::collection($works)->resolve(),
            'Gallery works reordered',
        );
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'max:8192'],
            'caption' => ['nullable', 'string', 'max:255'],
            'service_tag' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $tenantId = $this->tenantContext->id() ?? 'shared';
        $path = $data['image']->store('gallery/'.$tenantId, 'public');
        $url = PublicStorageUrl::fromDiskPath($path);

        $work = $this->works->create([
            'image_url' => $url,
            'caption' => $data['caption'] ?? null,
            'service_tag' => $data['service_tag'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_published' => $data['is_published'] ?? true,
        ]);

        return ApiResponse::success([
            'url' => $url,
            'path' => $path,
            'work' => (new GalleryWorkResource($work))->resolve(),
        ], 'Gallery image uploaded', 201);
    }
}
