<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Models\AnalyticsExportJob;
use App\Domains\Analytics\Models\AnalyticsSavedReport;
use App\Shared\Tenancy\TenantContext;

/**
 * Resolves the active tenant for analytics queries.
 *
 * Optional location/provider filters are applied directly as WHERE clauses by
 * the individual services. Because every analytics query is explicitly scoped
 * to the resolved tenant id, a location/provider id belonging to another tenant
 * simply yields zero rows rather than leaking data, so no extra ownership
 * lookups are required here.
 *
 * For the 12B saved-reports/export-jobs surface, model lookups rely on the
 * BelongsToTenant global scope so cross-tenant ids resolve to a 404.
 */
class AnalyticsScopeValidator
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantContext->id();
    }

    public function findSavedReport(string $id): AnalyticsSavedReport
    {
        return AnalyticsSavedReport::query()->findOrFail($id);
    }

    public function findExportJob(string $id): AnalyticsExportJob
    {
        return AnalyticsExportJob::query()->findOrFail($id);
    }
}
