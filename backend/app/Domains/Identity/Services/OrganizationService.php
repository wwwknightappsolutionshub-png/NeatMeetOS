<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\Tenant;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

class OrganizationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function getCurrent(): Tenant
    {
        $tenant = $this->tenantContext->get();

        if ($tenant === null) {
            throw ValidationException::withMessages([
                'tenant' => ['Tenant context is required.'],
            ]);
        }

        return $tenant;
    }

    public function update(array $data): Tenant
    {
        $tenant = $this->getCurrent();
        $old = $tenant->only(array_keys($data));

        $tenant->fill($data);
        $tenant->save();

        $this->auditLogger->log(
            'organization.updated',
            $tenant,
            $old,
            $tenant->only(array_keys($data)),
        );

        return $tenant->fresh();
    }
}
