<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\DTOs\DateRange;
use App\Domains\Analytics\Services\Concerns\BuildsDailySeries;
use App\Domains\Crm\Models\ClientConsentRecord;
use Illuminate\Support\Facades\DB;

/**
 * Client / CRM analytics. New-client metrics are anchored on
 * clients.created_at; consent metrics are derived from the latest consent
 * record per (client, consent_type), mirroring ClientConsentService::currentState.
 */
class ClientAnalyticsService
{
    use BuildsDailySeries;

    /**
     * @return array<string, mixed>
     */
    public function report(string $tenantId, DateRange $range, ?string $locationId = null): array
    {
        return [
            'range' => $range->toArray(),
            'summary' => $this->summary($tenantId, $range, $locationId),
            'growth' => $this->growth($tenantId, $range, $locationId),
            'tags' => $this->tags($tenantId, $locationId),
            'consents' => $this->consents($tenantId, $locationId),
            'membership_attachment' => $this->membershipAttachment($tenantId),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function summary(string $tenantId, DateRange $range, ?string $locationId = null): array
    {
        $total = (int) $this->clientQuery($tenantId, $locationId)->count();

        $new = (int) $this->clientQuery($tenantId, $locationId)
            ->whereBetween('created_at', [$range->from, $range->to])
            ->count();

        $active = (int) $this->clientQuery($tenantId, $locationId)
            ->where('is_active', true)
            ->count();

        $consentState = $this->latestConsentCounts($tenantId, $locationId);

        return [
            'total_clients' => $total,
            'new_clients_in_period' => $new,
            'active_clients' => $active,
            'marketing_email_opt_in_count' => $consentState[ClientConsentRecord::TYPE_MARKETING_EMAIL]['granted'] ?? 0,
            'marketing_sms_opt_in_count' => $consentState[ClientConsentRecord::TYPE_MARKETING_SMS]['granted'] ?? 0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function growth(string $tenantId, DateRange $range, ?string $locationId): array
    {
        $rows = $this->clientQuery($tenantId, $locationId)
            ->whereBetween('created_at', [$range->from, $range->to])
            ->get(['created_at']);

        return $this->dailySeries(
            $range,
            $rows,
            'created_at',
            fn ($row) => ['new_clients' => 1],
            ['new_clients' => 0],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tags(string $tenantId, ?string $locationId): array
    {
        $query = DB::table('client_client_tag as ct')
            ->join('clients as c', 'c.id', '=', 'ct.client_id')
            ->join('client_tags as t', 't.id', '=', 'ct.client_tag_id')
            ->where('c.tenant_id', $tenantId);

        if ($locationId !== null) {
            $query->where('c.primary_location_id', $locationId);
        }

        return $query
            ->selectRaw('t.id as tag_id, t.name as name, COUNT(*) as total')
            ->groupBy('t.id', 't.name')
            ->orderByDesc('total')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'tag_id' => $row->tag_id,
                'name' => $row->name,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function consents(string $tenantId, ?string $locationId): array
    {
        $counts = $this->latestConsentCounts($tenantId, $locationId);

        $result = [];
        foreach (ClientConsentRecord::types() as $type) {
            $result[$type] = [
                'granted' => $counts[$type]['granted'] ?? 0,
                'denied' => $counts[$type]['denied'] ?? 0,
            ];
        }

        return $result;
    }

    /**
     * @return array<string, int>
     */
    private function membershipAttachment(string $tenantId): array
    {
        $withMembership = (int) DB::table('client_memberships')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->distinct()
            ->count('client_id');

        $withPackage = (int) DB::table('client_packages')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->distinct()
            ->count('client_id');

        return [
            'clients_with_active_membership' => $withMembership,
            'clients_with_active_package' => $withPackage,
        ];
    }

    /**
     * Reduce the consent log to the latest record per (client, type) and count
     * granted vs denied. Performed in PHP so ordering ties are resolved
     * consistently across databases.
     *
     * @return array<string, array{granted: int, denied: int}>
     */
    private function latestConsentCounts(string $tenantId, ?string $locationId): array
    {
        $query = DB::table('client_consent_records as ccr')
            ->join('clients as c', 'c.id', '=', 'ccr.client_id')
            ->where('ccr.tenant_id', $tenantId);

        if ($locationId !== null) {
            $query->where('c.primary_location_id', $locationId);
        }

        $rows = $query
            ->orderByDesc('ccr.recorded_at')
            ->get(['ccr.client_id', 'ccr.consent_type', 'ccr.granted', 'ccr.recorded_at']);

        $seen = [];
        $counts = [];

        foreach ($rows as $row) {
            $key = $row->client_id.'|'.$row->consent_type;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $type = $row->consent_type;
            if (! isset($counts[$type])) {
                $counts[$type] = ['granted' => 0, 'denied' => 0];
            }

            if ((bool) $row->granted) {
                $counts[$type]['granted']++;
            } else {
                $counts[$type]['denied']++;
            }
        }

        return $counts;
    }

    private function clientQuery(string $tenantId, ?string $locationId): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('clients')->where('tenant_id', $tenantId);

        if ($locationId !== null) {
            $query->where('primary_location_id', $locationId);
        }

        return $query;
    }
}
