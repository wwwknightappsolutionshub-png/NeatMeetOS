<?php

namespace App\Domains\Gallery\Services;

use App\Domains\Gallery\Models\GalleryWork;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GalleryWorkService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(array $filters = []): Collection
    {
        $query = GalleryWork::query()
            ->orderBy('sort_order')
            ->orderByDesc('created_at');

        if (array_key_exists('is_published', $filters) && $filters['is_published'] !== null) {
            $query->where('is_published', (bool) $filters['is_published']);
        }

        return $query->limit(200)->get();
    }

    public function listPublished(): Collection
    {
        return GalleryWork::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    public function find(string $id): GalleryWork
    {
        $work = GalleryWork::query()->findOrFail($id);
        $this->assertTenant($work);

        return $work;
    }

    public function create(array $data): GalleryWork
    {
        $work = GalleryWork::query()->create([
            'tenant_id' => $this->requireTenantId(),
            'image_url' => $data['image_url'],
            'caption' => $data['caption'] ?? null,
            'service_tag' => $data['service_tag'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_published' => $data['is_published'] ?? true,
        ]);

        $this->auditLogger->log('gallery.work.created', $work, null, $work->only([
            'image_url', 'caption', 'service_tag', 'sort_order', 'is_published',
        ]));

        return $work->fresh();
    }

    public function update(GalleryWork $work, array $data): GalleryWork
    {
        $this->assertTenant($work);
        $old = $work->only(['image_url', 'caption', 'service_tag', 'sort_order', 'is_published']);

        $work->fill(array_intersect_key($data, array_flip([
            'image_url', 'caption', 'service_tag', 'sort_order', 'is_published',
        ])));
        $work->save();

        $this->auditLogger->log('gallery.work.updated', $work, $old, $work->only([
            'image_url', 'caption', 'service_tag', 'sort_order', 'is_published',
        ]));

        return $work->fresh();
    }

    public function delete(GalleryWork $work): void
    {
        $this->assertTenant($work);
        $snapshot = $work->only(['image_url', 'caption', 'service_tag', 'sort_order']);
        $work->delete();

        $this->auditLogger->log('gallery.work.deleted', $work, $snapshot, null);
    }

    /**
     * @param  list<array{id: string, sort_order: int}>  $items
     */
    public function reorder(array $items): Collection
    {
        return DB::transaction(function () use ($items) {
            foreach ($items as $row) {
                $work = $this->find($row['id']);
                $work->sort_order = (int) $row['sort_order'];
                $work->save();
            }

            $this->auditLogger->log('gallery.work.reordered', null, null, [
                'count' => count($items),
            ]);

            return $this->list();
        });
    }

    private function assertTenant(GalleryWork $work): void
    {
        if ($work->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['resource' => ['Resource not found.']]);
        }
    }

    private function requireTenantId(): string
    {
        $id = $this->tenantContext->id();
        if ($id === null) {
            throw ValidationException::withMessages(['tenant' => ['Tenant context is required.']]);
        }

        return $id;
    }
}
