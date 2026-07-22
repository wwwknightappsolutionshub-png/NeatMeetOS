<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingSuppressionReason;
use App\Domains\Marketing\Enums\MarketingSuppressionSource;
use App\Domains\Marketing\Models\MarketingContactSuppression;
use App\Shared\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketingSuppressionService
{
    public function __construct(
        private readonly MarketingScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = MarketingContactSuppression::query()
            ->with(['client', 'createdBy'])
            ->orderByDesc('created_at');

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (! empty($filters['reason'])) {
            $query->where('reason', $filters['reason']);
        }

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where('contact_value', 'like', $search);
        }

        return $query->paginate($perPage);
    }

    public function find(string $id): MarketingContactSuppression
    {
        return $this->scope->findSuppression($id)->load(['client', 'createdBy']);
    }

    public function create(array $data): MarketingContactSuppression
    {
        $tenantId = $this->scope->tenantId();
        $this->validateChannel($data['channel'] ?? null);
        $this->validateReason($data['reason'] ?? null);

        if (! empty($data['client_id'])) {
            $this->scope->findClient($data['client_id']);
        }

        return DB::transaction(function () use ($tenantId, $data) {
            $suppression = MarketingContactSuppression::query()->create([
                'tenant_id' => $tenantId,
                'client_id' => $data['client_id'] ?? null,
                'channel' => $data['channel'],
                'contact_value' => $data['contact_value'],
                'reason' => $data['reason'] ?? MarketingSuppressionReason::MANUAL,
                'source' => $data['source'] ?? MarketingSuppressionSource::STAFF_ACTION,
                'is_active' => true,
                'notes' => $data['notes'] ?? null,
                'created_by_team_member_id' => $data['created_by_team_member_id'] ?? null,
            ]);

            $this->auditLogger->log('marketing_suppression.created', $suppression, null, [
                'channel' => $suppression->channel,
                'contact_value' => $suppression->contact_value,
                'reason' => $suppression->reason,
            ]);

            return $suppression->fresh(['client', 'createdBy']);
        });
    }

    /**
     * Lift (deactivate) a suppression so the destination can be contacted again.
     */
    public function lift(MarketingContactSuppression $suppression): MarketingContactSuppression
    {
        $this->scope->assertTenantModel($suppression);

        return DB::transaction(function () use ($suppression) {
            $old = ['is_active' => $suppression->is_active];
            $suppression->is_active = false;
            $suppression->lifted_at = now();
            $suppression->save();

            $this->auditLogger->log('marketing_suppression.lifted', $suppression, $old, [
                'is_active' => false,
                'lifted_at' => $suppression->lifted_at?->toIso8601String(),
            ]);

            return $suppression->fresh();
        });
    }

    /**
     * @deprecated Prefer {@see lift()}; retained for the /deactivate route alias.
     */
    public function deactivate(MarketingContactSuppression $suppression): MarketingContactSuppression
    {
        return $this->lift($suppression);
    }

    public function reactivate(MarketingContactSuppression $suppression): MarketingContactSuppression
    {
        $this->scope->assertTenantModel($suppression);

        return DB::transaction(function () use ($suppression) {
            $old = ['is_active' => $suppression->is_active];
            $suppression->is_active = true;
            $suppression->lifted_at = null;
            $suppression->save();

            $this->auditLogger->log('marketing_suppression.reactivated', $suppression, $old, ['is_active' => true]);

            return $suppression->fresh();
        });
    }

    /**
     * Create a suppression from an unsubscribe action (message or client).
     */
    public function suppressFromUnsubscribe(
        Client $client,
        string $channel,
        ?string $contactValue = null,
        ?string $notes = null,
    ): MarketingContactSuppression {
        $address = $contactValue ?? $this->contactValueForClient($client, $channel);

        if ($address === null || $address === '') {
            throw ValidationException::withMessages(['contact_value' => ['No contact address available for suppression.']]);
        }

        $existing = MarketingContactSuppression::query()
            ->where('tenant_id', $this->scope->tenantId())
            ->where('channel', $channel)
            ->where('contact_value', $address)
            ->where('is_active', true)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->create([
            'client_id' => $client->id,
            'channel' => $channel,
            'contact_value' => $address,
            'reason' => MarketingSuppressionReason::UNSUBSCRIBE,
            'source' => MarketingSuppressionSource::CLIENT_ACTION,
            'notes' => $notes,
        ]);
    }

    /**
     * Check whether a client/contact is suppressed for a channel.
     */
    public function isSuppressed(?Client $client, string $channel, ?string $contactValue = null): bool
    {
        $tenantId = $this->scope->tenantId();
        $channel = $this->normaliseChannel($channel);

        $query = MarketingContactSuppression::query()
            ->where('tenant_id', $tenantId)
            ->where('channel', $channel)
            ->where('is_active', true);

        if ($contactValue !== null && $contactValue !== '') {
            $query->where('contact_value', $contactValue);
        } elseif ($client !== null) {
            $query->where(function ($q) use ($client) {
                $q->where('client_id', $client->id)
                    ->orWhereIn('contact_value', array_filter([
                        $client->email,
                        $client->phone,
                    ]));
            });
        } else {
            return false;
        }

        return $query->exists();
    }

    public function contactValueForClient(Client $client, string $channel): ?string
    {
        $channel = $this->normaliseChannel($channel);

        if ($channel === MarketingChannel::EMAIL) {
            $email = trim((string) ($client->email ?? ''));

            return $email !== '' ? $email : null;
        }

        $phone = trim((string) ($client->phone ?? ''));

        return $phone !== '' ? $phone : null;
    }

    private function validateChannel(?string $channel): void
    {
        if ($channel === null || ! in_array($channel, MarketingChannel::all(), true)) {
            throw ValidationException::withMessages(['channel' => ['Invalid marketing channel.']]);
        }
    }

    private function validateReason(?string $reason): void
    {
        if ($reason !== null && ! in_array($reason, MarketingSuppressionReason::all(), true)) {
            throw ValidationException::withMessages(['reason' => ['Invalid suppression reason.']]);
        }
    }

    private function normaliseChannel(string $channel): string
    {
        return in_array($channel, MarketingChannel::all(), true)
            ? $channel
            : MarketingChannel::EMAIL;
    }
}
