<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\SalonReview;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SalonReviewService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listPublished(): array
    {
        return SalonReview::query()
            ->where('is_published', true)
            ->orderBy('display_order')
            ->orderByDesc('created_at')
            ->limit(40)
            ->get()
            ->map(fn (SalonReview $r) => $this->serialize($r))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAdmin(?bool $published = null): array
    {
        $query = SalonReview::query()->orderByDesc('created_at');
        if ($published !== null) {
            $query->where('is_published', $published);
        }

        return $query->get()->map(fn (SalonReview $r) => $this->serialize($r))->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPublic(array $data): array
    {
        $tenantId = $this->tenantContext->id();
        if ($tenantId === null) {
            throw ValidationException::withMessages(['tenant' => ['Tenant is required.']]);
        }

        $review = SalonReview::query()->create([
            'tenant_id' => $tenantId,
            'author_name' => trim((string) $data['author_name']),
            'rating' => (int) ($data['rating'] ?? 5),
            'body' => trim((string) $data['body']),
            'is_published' => true,
            'display_order' => (int) SalonReview::query()->max('display_order') + 1,
        ]);

        $this->audit->log('salon_review.created_public', $review, null, $review->toArray());

        return $this->serialize($review);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(SalonReview $review, array $data): array
    {
        $old = $review->toArray();
        $review->fill([
            'author_name' => $data['author_name'] ?? $review->author_name,
            'rating' => array_key_exists('rating', $data) ? (int) $data['rating'] : $review->rating,
            'body' => $data['body'] ?? $review->body,
            'is_published' => array_key_exists('is_published', $data)
                ? (bool) $data['is_published']
                : $review->is_published,
            'display_order' => array_key_exists('display_order', $data)
                ? (int) $data['display_order']
                : $review->display_order,
        ])->save();

        $this->audit->log('salon_review.updated', $review, $old, $review->toArray());

        return $this->serialize($review->fresh());
    }

    public function delete(SalonReview $review): void
    {
        $snapshot = $review->toArray();
        $review->delete();
        $this->audit->log('salon_review.deleted', null, $snapshot, null);
    }

    public function find(string $id): SalonReview
    {
        return SalonReview::query()->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(SalonReview $review): array
    {
        return [
            'id' => $review->id,
            'author_name' => $review->author_name,
            'rating' => (int) $review->rating,
            'body' => $review->body,
            'is_published' => (bool) $review->is_published,
            'display_order' => (int) $review->display_order,
            'created_at' => $review->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<array{author_name: string, rating: int, body: string}>  $rows
     */
    public function seedForTenant(string $tenantId, array $rows): Collection
    {
        $created = collect();
        foreach ($rows as $i => $row) {
            $created->push(SalonReview::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'author_name' => $row['author_name'],
                'rating' => $row['rating'],
                'body' => $row['body'],
                'is_published' => true,
                'display_order' => $i + 1,
            ]));
        }

        return $created;
    }
}
