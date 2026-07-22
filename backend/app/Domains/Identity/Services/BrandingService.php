<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\Tenant;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

class BrandingService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function get(): array
    {
        return $this->getCurrentTenant()->getBranding();
    }

    public function update(array $data): array
    {
        $tenant = $this->getCurrentTenant();
        $old = $tenant->getBranding();

        $allowed = array_keys(Tenant::BRANDING_DEFAULTS);
        $payload = array_intersect_key($data, array_flip($allowed));

        $tenant->setBranding($payload);
        $tenant->save();

        $this->auditLogger->log(
            'branding.updated',
            $tenant,
            $old,
            $tenant->getBranding(),
        );

        return $tenant->getBranding();
    }

    private function getCurrentTenant(): Tenant
    {
        $tenant = $this->tenantContext->get();

        if ($tenant === null) {
            throw ValidationException::withMessages([
                'tenant' => ['Tenant context is required.'],
            ]);
        }

        return $tenant;
    }
}
