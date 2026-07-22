<?php

namespace App\Domains\Lookbook\Services;

use App\Domains\Lookbook\Models\LookbookItem;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LookbookItemService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(array $filters = []): Collection
    {
        $query = LookbookItem::query()
            ->orderBy('sort_order')
            ->orderBy('title');

        if (array_key_exists('is_published', $filters) && $filters['is_published'] !== null) {
            $query->where('is_published', (bool) $filters['is_published']);
        }

        return $query->limit(200)->get();
    }

    public function listPublished(): Collection
    {
        return LookbookItem::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->limit(100)
            ->get();
    }

    public function find(string $id): LookbookItem
    {
        $item = LookbookItem::query()->findOrFail($id);
        $this->assertTenant($item);

        return $item;
    }

    public function update(LookbookItem $item, array $data): LookbookItem
    {
        $this->assertTenant($item);
        $old = $item->only(['image_url', 'title', 'caption', 'sort_order', 'is_published']);

        $item->fill(array_intersect_key($data, array_flip([
            'image_url', 'title', 'caption', 'sort_order', 'is_published',
        ])));
        $item->save();

        $this->auditLogger->log('lookbook.item.updated', $item, $old, $item->only([
            'image_url', 'title', 'caption', 'sort_order', 'is_published',
        ]));

        return $item->fresh();
    }

    public function replaceImage(LookbookItem $item, string $imageUrl): LookbookItem
    {
        return $this->update($item, ['image_url' => $imageUrl]);
    }

    public function hide(LookbookItem $item): LookbookItem
    {
        return $this->update($item, ['is_published' => false]);
    }

    public function publish(LookbookItem $item): LookbookItem
    {
        return $this->update($item, ['is_published' => true]);
    }

    /**
     * @param  list<array{id: string, sort_order: int}>  $items
     */
    public function reorder(array $items): Collection
    {
        return DB::transaction(function () use ($items) {
            foreach ($items as $row) {
                $item = $this->find($row['id']);
                $item->sort_order = (int) $row['sort_order'];
                $item->save();
            }

            $this->auditLogger->log('lookbook.item.reordered', null, null, [
                'count' => count($items),
            ]);

            return $this->list();
        });
    }

    private function assertTenant(LookbookItem $item): void
    {
        if ($item->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['resource' => ['Resource not found.']]);
        }
    }
}
