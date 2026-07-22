<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientConsentRecord;
use App\Domains\Crm\Services\ClientConsentService;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Models\NotificationPreference;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Operational communication preferences for clients.
 *
 * IMPORTANT: this is a derived operational projection used to gate reminders and
 * operational messaging. It is NOT the legal source of truth — CRM consent
 * records remain authoritative and are never mutated here.
 */
class NotificationPreferenceService
{
    public function __construct(
        private readonly NotificationScopeValidator $scope,
        private readonly ClientConsentService $consentService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function getOrCreateForClient(Client $client): NotificationPreference
    {
        $this->scope->assertTenantModel($client);

        $preference = NotificationPreference::query()
            ->where('client_id', $client->id)
            ->first();

        if ($preference !== null) {
            return $preference;
        }

        return NotificationPreference::query()->create([
            'tenant_id' => $client->tenant_id,
            'client_id' => $client->id,
        ]);
    }

    public function update(Client $client, array $data): NotificationPreference
    {
        $preference = $this->getOrCreateForClient($client);

        $fields = array_intersect_key($data, array_flip([
            'allow_email', 'allow_sms', 'allow_whatsapp', 'allow_push',
            'booking_notifications', 'payment_notifications',
            'membership_notifications', 'general_notifications',
            'preferred_channel',
        ]));

        foreach (['allow_email', 'allow_sms', 'allow_whatsapp', 'allow_push',
            'booking_notifications', 'payment_notifications',
            'membership_notifications', 'general_notifications'] as $boolField) {
            if (array_key_exists($boolField, $fields)) {
                $fields[$boolField] = filter_var($fields[$boolField], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return DB::transaction(function () use ($preference, $fields) {
            $old = $preference->only(array_keys($fields));
            $preference->fill($fields);
            $preference->save();

            $this->auditLogger->log('notification_preference.updated', $preference, $old, $preference->only(array_keys($fields)));

            return $preference->fresh();
        });
    }

    /**
     * Project the client's current CRM consent state onto operational preference flags.
     * Never mutates CRM consent records.
     */
    public function syncFromConsent(Client $client): NotificationPreference
    {
        $preference = $this->getOrCreateForClient($client);
        $state = $this->consentService->currentState($client);

        $emailGranted = $this->consentGranted($state, ClientConsentRecord::TYPE_MARKETING_EMAIL, true);
        $smsGranted = $this->consentGranted($state, ClientConsentRecord::TYPE_MARKETING_SMS, true);

        return DB::transaction(function () use ($preference, $emailGranted, $smsGranted) {
            $old = $preference->only(['allow_email', 'allow_sms', 'preferred_channel', 'last_synced_from_consent_at']);

            $preference->allow_email = $emailGranted;
            $preference->allow_sms = $smsGranted;
            $preference->preferred_channel = $this->derivePreferredChannel($emailGranted, $smsGranted, $preference->preferred_channel);
            $preference->last_synced_from_consent_at = now();
            $preference->save();

            $this->auditLogger->log('notification_preference.synced_from_consent', $preference, $old, [
                'allow_email' => $emailGranted,
                'allow_sms' => $smsGranted,
                'preferred_channel' => $preference->preferred_channel,
            ]);

            return $preference->fresh();
        });
    }

    /**
     * Determine whether a client may be contacted for a given channel + preference category.
     */
    public function allowsDelivery(Client $client, string $channel, string $preferenceCategory): bool
    {
        $preference = $this->getOrCreateForClient($client);

        $channelAllowed = match ($channel) {
            NotificationChannel::EMAIL => (bool) $preference->allow_email,
            NotificationChannel::SMS => (bool) $preference->allow_sms,
            NotificationChannel::WHATSAPP => (bool) $preference->allow_whatsapp,
            NotificationChannel::PUSH => (bool) $preference->allow_push,
            // Member inbox is shown only when authenticated; no separate opt-in flag.
            NotificationChannel::IN_APP, NotificationChannel::INTERNAL_NOTE => true,
            default => false,
        };

        if (! $channelAllowed) {
            return false;
        }

        $column = \App\Domains\Notifications\Enums\NotificationPreferenceCategory::column($preferenceCategory);

        return (bool) $preference->{$column};
    }

    private function consentGranted(array $state, string $type, bool $default): bool
    {
        if (! array_key_exists($type, $state)) {
            return $default;
        }

        return (bool) ($state[$type]['granted'] ?? $default);
    }

    private function derivePreferredChannel(bool $emailGranted, bool $smsGranted, ?string $current): ?string
    {
        if ($current === NotificationChannel::EMAIL && $emailGranted) {
            return $current;
        }
        if ($current === NotificationChannel::SMS && $smsGranted) {
            return $current;
        }

        if ($emailGranted) {
            return NotificationChannel::EMAIL;
        }
        if ($smsGranted) {
            return NotificationChannel::SMS;
        }

        return null;
    }
}
