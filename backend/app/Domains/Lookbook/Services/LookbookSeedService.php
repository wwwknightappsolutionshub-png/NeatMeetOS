<?php

namespace App\Domains\Lookbook\Services;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Lookbook\Models\LookbookItem;
use App\Domains\Lookbook\Support\LookbookSeedCatalogue;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class LookbookSeedService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Idempotent: skips when the tenant already has any lookbook items.
     *
     * @return array{seeded: bool, count: int, category: string}
     */
    public function seedForTenant(Tenant $tenant): array
    {
        $previous = $this->tenantContext->get();
        $this->tenantContext->set($tenant);

        try {
            $exists = LookbookItem::query()->where('tenant_id', $tenant->id)->exists();
            if ($exists) {
                return [
                    'seeded' => false,
                    'count' => LookbookItem::query()->where('tenant_id', $tenant->id)->count(),
                    'category' => LookbookSeedCatalogue::mapBusinessType($tenant->business_type),
                ];
            }

            $category = LookbookSeedCatalogue::mapBusinessType($tenant->business_type);
            $entries = LookbookSeedCatalogue::forCategory($category);

            DB::transaction(function () use ($tenant, $category, $entries) {
                foreach ($entries as $index => $entry) {
                    LookbookItem::withoutGlobalScopes()->create([
                        'tenant_id' => $tenant->id,
                        'image_url' => $entry['image_url'],
                        'title' => $entry['title'],
                        'caption' => $entry['caption'],
                        'category_key' => $category,
                        'sort_order' => $index,
                        'is_published' => true,
                        'is_seeded' => true,
                    ]);
                }
            });

            $count = count($entries);
            $this->auditLogger->log('lookbook.seeded', $tenant, null, [
                'category' => $category,
                'count' => $count,
            ]);

            return [
                'seeded' => true,
                'count' => $count,
                'category' => $category,
            ];
        } finally {
            if ($previous !== null) {
                $this->tenantContext->set($previous);
            } else {
                $this->tenantContext->clear();
            }
        }
    }
}
