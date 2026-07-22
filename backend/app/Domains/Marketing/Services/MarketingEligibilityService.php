<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientConsentRecord;
use App\Domains\Crm\Models\MemberPushSubscription;
use App\Domains\Crm\Services\ClientConsentService;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Models\MarketingWorkflowExecution;

/**
 * Centralises marketing contactability rules for a client on a given channel.
 *
 * Every audience resolution and automation run funnels recipients through this
 * service so consent, active state, contact details, suppression, and location
 * scope are evaluated identically across the domain.
 */
class MarketingEligibilityService
{
    public const REASON_MISSING_EMAIL = 'missing_email';

    public const REASON_MISSING_PHONE = 'missing_phone';

    public const REASON_MISSING_PUSH_SUBSCRIPTION = 'missing_push_subscription';

    public const REASON_NO_CHANNEL_CONSENT = 'no_channel_consent';

    public const REASON_INACTIVE_CLIENT = 'inactive_client';

    public const REASON_NO_LOCATION_SCOPE = 'no_matching_location_scope';

    public const REASON_SUPPRESSED = 'contact_suppressed';

    public const REASON_COOLDOWN = 'cooldown_active';

    public const REASON_MAX_EXECUTIONS = 'max_executions_reached';

    public function __construct(
        private readonly ClientConsentService $consentService,
        private readonly MarketingSuppressionService $suppressionService,
    ) {}

    /**
     * Evaluate whether a client can be contacted on a channel.
     *
     * @param  array{location_ids?: array<int, string>, require_email_consent?: bool, require_sms_consent?: bool}  $context
     * @return array{eligible: bool, skipped_reason: string|null, recipient_address: string|null, channel: string}
     */
    public function evaluate(Client $client, string $channel, array $context = []): array
    {
        $channel = $this->normaliseChannel($channel);

        if (! $client->is_active) {
            return $this->result(false, self::REASON_INACTIVE_CLIENT, null, $channel);
        }

        if (! $this->matchesLocationScope($client, $context['location_ids'] ?? [])) {
            return $this->result(false, self::REASON_NO_LOCATION_SCOPE, null, $channel);
        }

        $address = $this->recipientAddress($client, $channel);

        if ($address === null) {
            $reason = match ($channel) {
                MarketingChannel::EMAIL => self::REASON_MISSING_EMAIL,
                MarketingChannel::PUSH => self::REASON_MISSING_PUSH_SUBSCRIPTION,
                MarketingChannel::IN_APP => self::REASON_INACTIVE_CLIENT,
                default => self::REASON_MISSING_PHONE,
            };

            return $this->result(false, $reason, null, $channel);
        }

        if (! $this->hasChannelConsent($client, $channel)) {
            return $this->result(false, self::REASON_NO_CHANNEL_CONSENT, null, $channel);
        }

        if ($this->suppressionService->isSuppressed($client, $channel, $address)) {
            return $this->result(false, self::REASON_SUPPRESSED, null, $channel);
        }

        if (($context['require_email_consent'] ?? false) && ! $this->hasConsent($client, ClientConsentRecord::TYPE_MARKETING_EMAIL)) {
            return $this->result(false, self::REASON_NO_CHANNEL_CONSENT, null, $channel);
        }

        if (($context['require_sms_consent'] ?? false) && ! $this->hasConsent($client, ClientConsentRecord::TYPE_MARKETING_SMS)) {
            return $this->result(false, self::REASON_NO_CHANNEL_CONSENT, null, $channel);
        }

        return $this->result(true, null, $address, $channel);
    }

    public function isEligible(Client $client, string $channel, array $context = []): bool
    {
        return $this->evaluate($client, $channel, $context)['eligible'];
    }

    /**
     * Check workflow-specific repeat/cooldown constraints for a client.
     *
     * @return array{eligible: bool, skipped_reason: string|null}
     */
    public function evaluateWorkflowConstraints(
        Client $client,
        string $workflowId,
        ?int $cooldownDays,
        bool $allowRepeat,
        ?int $maxExecutions,
    ): array {
        if (! $allowRepeat && $maxExecutions === null) {
            $existing = MarketingWorkflowExecution::query()
                ->where('tenant_id', $client->tenant_id)
                ->where('workflow_id', $workflowId)
                ->where('client_id', $client->id)
                ->whereNotIn('status', ['cancelled', 'skipped', 'failed'])
                ->exists();

            if ($existing) {
                return ['eligible' => false, 'skipped_reason' => self::REASON_COOLDOWN];
            }
        }

        if ($cooldownDays !== null && $cooldownDays > 0) {
            $recent = MarketingWorkflowExecution::query()
                ->where('tenant_id', $client->tenant_id)
                ->where('workflow_id', $workflowId)
                ->where('client_id', $client->id)
                ->where('created_at', '>=', now()->subDays($cooldownDays))
                ->whereNotIn('status', ['cancelled', 'skipped', 'failed'])
                ->exists();

            if ($recent) {
                return ['eligible' => false, 'skipped_reason' => self::REASON_COOLDOWN];
            }
        }

        if ($maxExecutions !== null && $maxExecutions > 0) {
            $count = MarketingWorkflowExecution::query()
                ->where('tenant_id', $client->tenant_id)
                ->where('workflow_id', $workflowId)
                ->where('client_id', $client->id)
                ->whereNotIn('status', ['cancelled', 'skipped', 'failed'])
                ->count();

            if ($count >= $maxExecutions) {
                return ['eligible' => false, 'skipped_reason' => self::REASON_MAX_EXECUTIONS];
            }
        }

        return ['eligible' => true, 'skipped_reason' => null];
    }

    public function recipientAddress(Client $client, string $channel): ?string
    {
        $channel = $this->normaliseChannel($channel);

        if ($channel === MarketingChannel::EMAIL) {
            $email = trim((string) ($client->email ?? ''));

            return $email !== '' ? $email : null;
        }

        if ($channel === MarketingChannel::PUSH) {
            $hasPush = MemberPushSubscription::query()
                ->where('client_id', $client->id)
                ->exists();

            return $hasPush ? 'push:'.$client->id : null;
        }

        if ($channel === MarketingChannel::IN_APP) {
            return 'client:'.$client->id;
        }

        $phone = trim((string) ($client->phone ?? ''));

        return $phone !== '' ? $phone : null;
    }

    public function consentTypeForChannel(string $channel): ?string
    {
        return match ($this->normaliseChannel($channel)) {
            MarketingChannel::EMAIL => ClientConsentRecord::TYPE_MARKETING_EMAIL,
            MarketingChannel::SMS, MarketingChannel::WHATSAPP => ClientConsentRecord::TYPE_MARKETING_SMS,
            // Push marketing inherits email marketing consent (existing CRM consent model).
            MarketingChannel::PUSH => ClientConsentRecord::TYPE_MARKETING_EMAIL,
            // In-app inbox is shown only to authenticated members; no external marketing consent.
            MarketingChannel::IN_APP => null,
            default => ClientConsentRecord::TYPE_MARKETING_EMAIL,
        };
    }

    private function hasChannelConsent(Client $client, string $channel): bool
    {
        $type = $this->consentTypeForChannel($channel);
        if ($type === null) {
            return true;
        }

        return $this->hasConsent($client, $type);
    }

    private function hasConsent(Client $client, string $consentType): bool
    {
        $state = $this->consentService->currentState($client);

        return (bool) ($state[$consentType]['granted'] ?? false);
    }

    /**
     * @param  array<int, string>  $locationIds
     */
    private function matchesLocationScope(Client $client, array $locationIds): bool
    {
        $locationIds = array_values(array_filter($locationIds));

        if ($locationIds === []) {
            return true;
        }

        return $client->primary_location_id !== null
            && in_array($client->primary_location_id, $locationIds, true);
    }

    private function normaliseChannel(string $channel): string
    {
        return in_array($channel, MarketingChannel::all(), true)
            ? $channel
            : MarketingChannel::EMAIL;
    }

    /**
     * @return array{eligible: bool, skipped_reason: string|null, recipient_address: string|null, channel: string}
     */
    private function result(bool $eligible, ?string $reason, ?string $address, string $channel): array
    {
        return [
            'eligible' => $eligible,
            'skipped_reason' => $reason,
            'recipient_address' => $address,
            'channel' => $channel,
        ];
    }
}
