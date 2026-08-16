<?php

namespace App\Domains\Payments\Services;

use App\Domains\Identity\Models\Tenant;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

class TenantPaymentsSettingsService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->tenant()->getPaymentsSettings();
    }

    /**
     * Public-safe bank details for online booking transfer instructions.
     *
     * @return array<string, string|null>
     */
    public function publicBankDetails(): array
    {
        $settings = $this->get();

        return [
            'account_name' => $settings['bank_account_name'],
            'bank_name' => $settings['bank_name'],
            'sort_code' => $settings['bank_sort_code'],
            'account_number' => $settings['bank_account_number'],
            'iban' => $settings['bank_iban'],
            'reference_hint' => $settings['bank_reference_hint'],
        ];
    }

    public function hasCompleteBankDetails(): bool
    {
        $bank = $this->publicBankDetails();

        return filled($bank['account_name'])
            && (filled($bank['sort_code']) && filled($bank['account_number']) || filled($bank['iban']));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(array $data): array
    {
        $tenant = $this->tenant();
        $old = $tenant->getPaymentsSettings();
        $allowed = array_keys(Tenant::PAYMENTS_DEFAULTS);
        $payload = array_intersect_key($data, array_flip($allowed));

        foreach ($payload as $key => $value) {
            if (is_string($value)) {
                $payload[$key] = trim($value) !== '' ? trim($value) : null;
            }
        }

        $tenant->setPaymentsSettings($payload);
        $tenant->save();

        $this->auditLogger->log('tenant.payments_settings.updated', $tenant, $old, $tenant->getPaymentsSettings());

        return $tenant->getPaymentsSettings();
    }

    private function tenant(): Tenant
    {
        $tenant = $this->tenantContext->get();
        if ($tenant === null) {
            throw ValidationException::withMessages(['tenant' => ['Tenant context is required.']]);
        }

        return $tenant;
    }
}
