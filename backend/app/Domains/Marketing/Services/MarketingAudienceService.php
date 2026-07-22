<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Models\MarketingAudience;
use App\Shared\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketingAudienceService
{
    /**
     * Supported audience rule keys mapped to their expected value type.
     *
     * @var array<string, string>
     */
    private const RULE_SCHEMA = [
        'location_ids' => 'array',
        'client_tag_ids' => 'array',
        'client_status' => 'string',
        'requires_email_consent' => 'bool',
        'requires_sms_consent' => 'bool',
        'preferred_team_member_ids' => 'array',
        'loyalty_display_statuses' => 'array',
        'has_future_booking' => 'bool',
        'last_visit_before' => 'date',
        'last_visit_after' => 'date',
    ];

    public function __construct(
        private readonly MarketingScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
        private readonly AudienceResolverService $resolver,
    ) {}

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = MarketingAudience::query()->with('createdBy')->orderBy('name');

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where('name', 'like', $search);
        }

        return $query->paginate($perPage);
    }

    public function find(string $id): MarketingAudience
    {
        return $this->scope->findAudience($id);
    }

    public function create(array $data): MarketingAudience
    {
        $tenantId = $this->scope->tenantId();
        $rules = $this->validateRules($data['rules'] ?? $data['rules_json'] ?? []);

        return DB::transaction(function () use ($tenantId, $data, $rules) {
            $audience = MarketingAudience::query()->create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'rules_json' => $rules,
                'description' => $data['description'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'created_by_team_member_id' => $data['created_by_team_member_id'] ?? null,
            ]);

            $this->auditLogger->log('marketing_audience.created', $audience, null, [
                'name' => $audience->name,
                'rules_json' => $audience->rules_json,
            ]);

            return $audience->fresh('createdBy');
        });
    }

    public function update(MarketingAudience $audience, array $data): MarketingAudience
    {
        $this->scope->assertTenantModel($audience);

        $fields = array_intersect_key($data, array_flip(['name', 'description', 'is_active']));

        if (array_key_exists('rules', $data) || array_key_exists('rules_json', $data)) {
            $fields['rules_json'] = $this->validateRules($data['rules'] ?? $data['rules_json'] ?? []);
        }

        return DB::transaction(function () use ($audience, $fields) {
            $old = $audience->only(array_keys($fields));
            $audience->fill($fields);
            $audience->save();

            $this->auditLogger->log('marketing_audience.updated', $audience, $old, $audience->only(array_keys($fields)));

            return $audience->fresh('createdBy');
        });
    }

    public function archive(MarketingAudience $audience): MarketingAudience
    {
        $this->scope->assertTenantModel($audience);

        return DB::transaction(function () use ($audience) {
            $old = ['is_active' => $audience->is_active];
            $audience->is_active = false;
            $audience->save();

            $this->auditLogger->log('marketing_audience.archived', $audience, $old, ['is_active' => false]);

            return $audience->fresh();
        });
    }

    /**
     * Preview the resolved audience for a channel without creating messages.
     *
     * @return array{
     *     channel: string,
     *     counts: array<string, mixed>,
     *     eligible_sample: array<int, array{client_id: string, client_name: string, recipient_address: string|null}>,
     *     skipped_sample: array<int, array{client_id: string, client_name: string, reason: string}>
     * }
     */
    public function preview(MarketingAudience $audience, string $channel, int $sampleSize = 20): array
    {
        $this->scope->assertTenantModel($audience);

        return $this->previewRules($audience->rules_json ?? [], $channel, $sampleSize);
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public function previewRules(array $rules, string $channel, int $sampleSize = 20): array
    {
        $this->validateChannel($channel);
        $resolved = $this->resolver->resolve($this->validateRules($rules), $channel);

        return [
            'channel' => $resolved['channel'],
            'counts' => $resolved['counts'],
            'eligible_sample' => $resolved['eligible']
                ->take($sampleSize)
                ->map(fn ($client) => [
                    'client_id' => $client->id,
                    'client_name' => $client->resolvedDisplayName(),
                    'recipient_address' => $client->getAttribute('marketing_recipient_address'),
                ])
                ->values()
                ->all(),
            'skipped_sample' => array_slice($resolved['skipped'], 0, $sampleSize),
        ];
    }

    /**
     * @param  mixed  $rules
     * @return array<string, mixed>
     */
    public function validateRules($rules): array
    {
        if ($rules === null || $rules === []) {
            return [];
        }

        if (! is_array($rules)) {
            throw ValidationException::withMessages(['rules_json' => ['Audience rules must be an object.']]);
        }

        $clean = [];

        foreach ($rules as $key => $value) {
            if (! array_key_exists($key, self::RULE_SCHEMA)) {
                throw ValidationException::withMessages(['rules_json' => ["Unknown audience rule: {$key}."]]);
            }

            if ($value === null) {
                continue;
            }

            $clean[$key] = $this->castRuleValue($key, self::RULE_SCHEMA[$key], $value);
        }

        return $clean;
    }

    private function castRuleValue(string $key, string $type, mixed $value): mixed
    {
        return match ($type) {
            'array' => $this->requireArray($key, $value),
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'date' => $this->requireDate($key, $value),
            default => is_scalar($value) ? (string) $value : $this->rejectRule($key),
        };
    }

    private function requireArray(string $key, mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(
            static fn ($item) => is_string($item) ? trim($item) : $item,
            $items,
        ), static fn ($item) => $item !== null && $item !== ''));
    }

    private function requireDate(string $key, mixed $value): string
    {
        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['rules_json' => ["Rule {$key} must be a valid date."]]);
        }
    }

    private function rejectRule(string $key): never
    {
        throw ValidationException::withMessages(['rules_json' => ["Rule {$key} has an invalid value."]]);
    }

    private function validateChannel(string $channel): void
    {
        if (! in_array($channel, MarketingChannel::all(), true)) {
            throw ValidationException::withMessages(['channel' => ['Invalid marketing channel.']]);
        }
    }
}
