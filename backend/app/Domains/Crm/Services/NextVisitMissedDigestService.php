<?php

namespace App\Domains\Crm\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientVisit;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\TenantOwnerNotice;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\TenantEntitlementService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class NextVisitMissedDigestService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantEntitlementService $entitlements,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * For each tenant in the last hour of their local day, digest clients who checked in
     * today without booking a next visit.
     *
     * @return array{tenants: int, digests: int, clients: int}
     */
    public function dispatchForAllTenants(?Carbon $now = null): array
    {
        $now = $now?->copy() ?? now();
        $tenantsProcessed = 0;
        $digests = 0;
        $clientsTotal = 0;

        $tenants = Tenant::query()->where('status', 'active')->get();

        foreach ($tenants as $tenant) {
            $timezone = $tenant->timezone ?: config('app.timezone');
            $local = $now->copy()->timezone($timezone);

            // Hourly job: only fire during the last hour of the local day (23:00–23:59).
            if ((int) $local->format('H') !== 23) {
                continue;
            }

            $this->tenantContext->set($tenant);

            try {
                if (! $this->entitlements->isEnabled($tenant, 'next_visit')) {
                    continue;
                }

                $tenantsProcessed++;
                $count = $this->dispatchForCurrentTenant($local);
                if ($count > 0) {
                    $digests++;
                    $clientsTotal += $count;
                }
            } finally {
                $this->tenantContext->clear();
            }
        }

        return [
            'tenants' => $tenantsProcessed,
            'digests' => $digests,
            'clients' => $clientsTotal,
        ];
    }

    public function dispatchForCurrentTenant(Carbon $localNow): int
    {
        $tenant = $this->tenantContext->get();
        if ($tenant === null) {
            return 0;
        }

        $dayStart = $localNow->copy()->startOfDay()->utc();
        $dayEnd = $localNow->copy()->endOfDay()->utc();

        $visits = ClientVisit::query()
            ->with('client')
            ->whereBetween('checked_in_at', [$dayStart, $dayEnd])
            ->whereNull('next_visit_appointment_id')
            ->orderBy('checked_in_at')
            ->get();

        if ($visits->isEmpty()) {
            return 0;
        }

        $lines = [];
        foreach ($visits as $visit) {
            $client = $visit->client;
            if (! $client instanceof Client) {
                continue;
            }
            $name = trim(($client->first_name ?? '').' '.($client->last_name ?? '')) ?: 'Client';
            $lines[] = $name.' (visit '.$visit->id.')';
        }

        if ($lines === []) {
            return 0;
        }

        $title = 'Missed next-visit prompts ('.count($lines).')';
        $body = "These clients checked in today without booking a next visit:\n\n"
            .implode("\n", array_map(fn (string $line) => '• '.$line, $lines));

        $owners = TeamMember::query()
            ->where('employment_type', TeamMember::EMPLOYMENT_OWNER)
            ->where('is_active', true)
            ->whereNotNull('user_id')
            ->get();

        foreach ($owners as $owner) {
            TenantOwnerNotice::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $owner->user_id,
                'type' => 'next_visit.missed_digest',
                'title' => $title,
                'body' => $body,
                'href' => '/admin/clients',
                'data' => [
                    'visit_ids' => $visits->pluck('id')->values()->all(),
                    'date' => $localNow->toDateString(),
                ],
            ]);

            $user = User::query()->find($owner->user_id);
            if ($user && filled($user->email)) {
                try {
                    Mail::raw($body, function ($message) use ($user, $title) {
                        $message->to($user->email)->subject($title);
                    });
                } catch (\Throwable) {
                    // ignore
                }
            }
        }

        $this->auditLogger->log('next_visit.missed_digest', $tenant, null, [
            'count' => count($lines),
            'date' => $localNow->toDateString(),
        ]);

        return count($lines);
    }
}
