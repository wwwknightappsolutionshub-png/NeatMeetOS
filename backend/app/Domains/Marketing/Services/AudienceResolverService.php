<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Crm\Models\Client;
use App\Domains\Marketing\Enums\MarketingChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Turns an audience rule set into concrete eligible / skipped recipient lists.
 *
 * DB-level predicates narrow the candidate pool, then every candidate is passed
 * through MarketingEligibilityService for the target channel so consent and
 * contactability decisions stay centralised.
 */
class AudienceResolverService
{
    public function __construct(
        private readonly MarketingEligibilityService $eligibility,
    ) {}

    /**
     * @param  array<string, mixed>  $rules
     * @param  array{load?: array<int, string>}  $options
     * @return array{
     *     channel: string,
     *     eligible: Collection,
     *     skipped: array<int, array{client_id: string, client_name: string, reason: string}>,
     *     counts: array{matched: int, eligible: int, skipped: int, by_reason: array<string, int>}
     * }
     */
    public function resolve(array $rules, string $channel, array $options = []): array
    {
        $candidates = $this->matchCandidates($rules, $options['load'] ?? []);

        $context = [
            'location_ids' => $this->normaliseList($rules['location_ids'] ?? []),
            'require_email_consent' => (bool) ($rules['requires_email_consent'] ?? false),
            'require_sms_consent' => (bool) ($rules['requires_sms_consent'] ?? false),
        ];

        $eligible = new Collection;
        $skipped = [];
        $byReason = [];
        $resolvedChannel = in_array($channel, MarketingChannel::all(), true) ? $channel : MarketingChannel::EMAIL;

        foreach ($candidates as $client) {
            $result = $this->eligibility->evaluate($client, $channel, $context);

            if ($result['eligible']) {
                $client->setAttribute('marketing_recipient_address', $result['recipient_address']);
                $eligible->push($client);

                continue;
            }

            $reason = $result['skipped_reason'] ?? 'ineligible';
            $skipped[] = [
                'client_id' => $client->id,
                'client_name' => $client->resolvedDisplayName(),
                'reason' => $reason,
            ];
            $byReason[$reason] = ($byReason[$reason] ?? 0) + 1;
        }

        return [
            'channel' => $resolvedChannel,
            'eligible' => $eligible,
            'skipped' => $skipped,
            'counts' => [
                'matched' => $candidates->count(),
                'eligible' => $eligible->count(),
                'skipped' => count($skipped),
                'by_reason' => $byReason,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<int, string>  $with
     */
    public function matchCandidates(array $rules, array $with = []): Collection
    {
        $query = Client::query()->with(array_merge(['primaryLocation'], $with));

        $this->applyStatus($query, $rules['client_status'] ?? 'active');

        $locationIds = $this->normaliseList($rules['location_ids'] ?? []);
        if ($locationIds !== []) {
            $query->whereIn('primary_location_id', $locationIds);
        }

        $tagIds = $this->normaliseList($rules['client_tag_ids'] ?? []);
        if ($tagIds !== []) {
            $query->whereHas('tags', fn (Builder $q) => $q->whereIn('client_tags.id', $tagIds));
        }

        $teamMemberIds = $this->normaliseList($rules['preferred_team_member_ids'] ?? []);
        if ($teamMemberIds !== []) {
            $query->whereIn('preferred_team_member_id', $teamMemberIds);
        }

        $loyaltyStatuses = $this->normaliseList($rules['loyalty_display_statuses'] ?? []);
        if ($loyaltyStatuses !== []) {
            $query->whereIn('loyalty_display_status', $loyaltyStatuses);
        }

        if (array_key_exists('has_future_booking', $rules) && $rules['has_future_booking'] !== null) {
            $futureIds = $this->clientsWithFutureBooking();
            if (filter_var($rules['has_future_booking'], FILTER_VALIDATE_BOOLEAN)) {
                $query->whereIn('id', $futureIds ?: ['__none__']);
            } else {
                $query->whereNotIn('id', $futureIds ?: ['__none__']);
            }
        }

        $before = $this->parseDate($rules['last_visit_before'] ?? null);
        $after = $this->parseDate($rules['last_visit_after'] ?? null);

        if ($before !== null || $after !== null) {
            $lastVisits = $this->lastVisitByClient();
            $matching = [];

            foreach ($lastVisits as $clientId => $lastVisit) {
                $visit = Carbon::parse($lastVisit);

                if ($before !== null && $visit->greaterThan($before)) {
                    continue;
                }

                if ($after !== null && $visit->lessThan($after)) {
                    continue;
                }

                $matching[] = $clientId;
            }

            $query->whereIn('id', $matching ?: ['__none__']);
        }

        return $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    private function applyStatus(Builder $query, mixed $status): void
    {
        if (is_array($status)) {
            $status = $status[0] ?? 'active';
        }

        $status = is_string($status) ? strtolower($status) : 'active';

        if ($status === 'all' || $status === 'any') {
            return;
        }

        $query->where('is_active', $status !== 'inactive');
    }

    /**
     * @return array<int, string>
     */
    private function clientsWithFutureBooking(): array
    {
        return Appointment::query()
            ->whereNotNull('client_id')
            ->where('starts_at', '>', now())
            ->whereIn('status', [
                Appointment::STATUS_PENDING,
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_CHECKED_IN,
            ])
            ->distinct()
            ->pluck('client_id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function lastVisitByClient(): array
    {
        return Appointment::query()
            ->whereNotNull('client_id')
            ->where('status', Appointment::STATUS_COMPLETED)
            ->selectRaw('client_id, MAX(ends_at) as last_visit')
            ->groupBy('client_id')
            ->pluck('last_visit', 'client_id')
            ->all();
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    /**
     * @return array<int, string>
     */
    private function normaliseList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_unique(array_filter(array_map(
            static fn ($item) => is_string($item) ? trim($item) : $item,
            $items,
        ), static fn ($item) => $item !== null && $item !== '')));
    }
}
