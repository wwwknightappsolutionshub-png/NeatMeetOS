<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Integrations\Enums\ProviderAttemptStatus;
use App\Domains\Integrations\Services\ProviderDispatchBridge;
use App\Domains\Notifications\Enums\NotificationAttemptProvider;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationMessageStatus;
use App\Domains\Notifications\Models\NotificationMessage;
use App\Domains\Notifications\Models\NotificationMessageAttempt;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Operational notification transport (Module 11 + Integrations).
 *
 * Dispatches via ProviderDispatchBridge so Mailgun/Twilio live adapters run when
 * configured; otherwise simulation fallback from ProviderDispatchService applies.
 */
class NotificationDispatchSimulationService
{
    public const PROVIDER = NotificationAttemptProvider::SIMULATION;

    public function __construct(
        private readonly NotificationScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
        private readonly ProviderDispatchBridge $providerBridge,
        private readonly NotificationNativeChannelDispatchService $nativeDispatch,
        private readonly PlatformWhatsAppSettingsService $platformWhatsApp,
    ) {}

    /**
     * @return array{message: NotificationMessage, attempt: NotificationMessageAttempt|null, simulated: bool}
     */
    public function simulate(NotificationMessage $message): array
    {
        return $this->dispatch($message);
    }

    /**
     * @return array{message: NotificationMessage, attempt: NotificationMessageAttempt|null, simulated: bool}
     */
    public function dispatch(NotificationMessage $message): array
    {
        $this->scope->assertTenantModel($message);

        if ($message->status === NotificationMessageStatus::SUPPRESSED) {
            $attempt = $this->recordAttempt($message, NotificationMessageStatus::SUPPRESSED, [
                'outcome' => 'suppressed',
                'reason' => $message->failure_reason,
            ], null, self::PROVIDER);

            $this->auditLogger->log('notification_message.suppressed', $message, null, [
                'channel' => $message->channel,
                'purpose' => $message->purpose,
                'reason' => $message->failure_reason,
            ]);

            $this->providerBridge->recordNotificationDispatch($message, null, NotificationMessageStatus::SUPPRESSED);

            return ['message' => $message->fresh('attempts'), 'attempt' => $attempt, 'simulated' => false];
        }

        if (in_array($message->status, NotificationMessageStatus::terminal(), true)) {
            return ['message' => $message, 'attempt' => null, 'simulated' => false];
        }

        return DB::transaction(function () use ($message) {
            $message->status = NotificationMessageStatus::PROCESSING;
            $message->save();

            if ($this->nativeDispatch->supports((string) $message->channel)) {
                $attempt = $this->recordAttempt($message, NotificationMessageStatus::PROCESSING, [
                    'outcome' => 'processing',
                    'native' => true,
                ], null, NotificationAttemptProvider::IN_APP);

                return $this->nativeDispatch->dispatch($message, $attempt);
            }

            if ($message->channel === NotificationChannel::WHATSAPP && $this->platformWhatsApp->isGeniusReady()) {
                return $this->dispatchViaPlatformGenius($message);
            }

            if ($this->shouldForceDomainFailure($message)) {
                $message->status = NotificationMessageStatus::FAILED;
                $message->failed_at = now();
                $message->failure_reason = $message->failure_reason ?? 'Simulated provider failure.';
                $message->save();

                $attempt = $this->recordAttempt($message, NotificationMessageStatus::FAILED, [
                    'outcome' => 'failed',
                    'reason' => $message->failure_reason,
                ], null, self::PROVIDER);

                $this->auditLogger->log('notification_message.failed', $message, null, [
                    'channel' => $message->channel,
                    'purpose' => $message->purpose,
                    'provider' => self::PROVIDER,
                    'reason' => 'simulated_failure',
                ]);

                $this->providerBridge->recordNotificationDispatch($message, null, NotificationMessageStatus::FAILED);

                return ['message' => $message->fresh('attempts'), 'attempt' => $attempt, 'simulated' => true];
            }

            $result = $this->providerBridge->recordNotificationDispatch($message, null, null);
            $provider = $result->driver ?: self::PROVIDER;
            $failed = in_array($result->status, [
                ProviderAttemptStatus::FAILED,
                ProviderAttemptStatus::CANCELLED,
            ], true);

            if ($failed) {
                $message->status = NotificationMessageStatus::FAILED;
                $message->failed_at = now();
                $message->failure_reason = $result->failureMessage ?? 'Provider dispatch failed.';
                $message->save();

                $attempt = $this->recordAttempt($message, NotificationMessageStatus::FAILED, [
                    'outcome' => 'failed',
                    'reason' => $message->failure_reason,
                    'provider_delivery_attempt_id' => $result->providerDeliveryAttemptId,
                ], $result->providerReference, $provider);

                $this->auditLogger->log('notification_message.failed', $message, null, [
                    'channel' => $message->channel,
                    'purpose' => $message->purpose,
                    'provider' => $provider,
                    'simulated' => $result->simulated,
                ]);

                return ['message' => $message->fresh('attempts'), 'attempt' => $attempt, 'simulated' => $result->simulated];
            }

            $message->status = NotificationMessageStatus::SENT;
            $message->sent_at = now();
            $message->failure_reason = null;
            $message->save();

            $attempt = $this->recordAttempt($message, NotificationMessageStatus::SENT, [
                'outcome' => 'sent',
                'reference' => $result->providerReference,
                'provider_delivery_attempt_id' => $result->providerDeliveryAttemptId,
                'simulated' => $result->simulated,
            ], $result->providerReference, $provider);

            $this->auditLogger->log('notification_message.sent', $message, null, [
                'channel' => $message->channel,
                'purpose' => $message->purpose,
                'provider' => $provider,
                'provider_reference' => $result->providerReference,
                'simulated' => $result->simulated,
            ]);

            return ['message' => $message->fresh('attempts'), 'attempt' => $attempt, 'simulated' => $result->simulated];
        });
    }

    /**
     * Platform Genius WhatsApp (KhayaOS-style) — preferred path when Super Admin enabled Genius.
     *
     * @return array{message: NotificationMessage, attempt: NotificationMessageAttempt, simulated: bool}
     */
    private function dispatchViaPlatformGenius(NotificationMessage $message): array
    {
        $to = (string) ($message->recipient_address ?? '');
        $body = (string) ($message->body_text ?? $message->subject ?? '');
        $send = $this->platformWhatsApp->sendOperational($to, $body, [
            'type' => 'notification',
            'purpose' => $message->purpose,
            'notification_message_id' => $message->id,
            'tenant_id' => $message->tenant_id,
        ]);

        if (! ($send['ok'] ?? false)) {
            $message->status = NotificationMessageStatus::FAILED;
            $message->failed_at = now();
            $message->failure_reason = $send['error'] ?? 'Genius WhatsApp send failed.';
            $message->save();

            $attempt = $this->recordAttempt($message, NotificationMessageStatus::FAILED, [
                'outcome' => 'failed',
                'reason' => $message->failure_reason,
                'provider' => NotificationAttemptProvider::GENIUS,
            ], null, NotificationAttemptProvider::GENIUS);

            $this->auditLogger->log('notification_message.failed', $message, null, [
                'channel' => $message->channel,
                'purpose' => $message->purpose,
                'provider' => NotificationAttemptProvider::GENIUS,
            ]);

            $this->providerBridge->recordNotificationDispatch($message, null, NotificationMessageStatus::FAILED);

            return ['message' => $message->fresh('attempts'), 'attempt' => $attempt, 'simulated' => false];
        }

        $message->status = NotificationMessageStatus::SENT;
        $message->sent_at = now();
        $message->failure_reason = null;
        $message->save();

        $reference = 'genius:'.substr(md5($to.now()->timestamp), 0, 12);
        $attempt = $this->recordAttempt($message, NotificationMessageStatus::SENT, [
            'outcome' => 'sent',
            'reference' => $reference,
            'provider' => NotificationAttemptProvider::GENIUS,
            'simulated' => false,
        ], $reference, NotificationAttemptProvider::GENIUS);

        $this->auditLogger->log('notification_message.sent', $message, null, [
            'channel' => $message->channel,
            'purpose' => $message->purpose,
            'provider' => NotificationAttemptProvider::GENIUS,
            'provider_reference' => $reference,
            'simulated' => false,
        ]);

        return ['message' => $message->fresh('attempts'), 'attempt' => $attempt, 'simulated' => false];
    }

    private function recordAttempt(
        NotificationMessage $message,
        string $status,
        array $response,
        ?string $reference = null,
        string $provider = self::PROVIDER,
    ): NotificationMessageAttempt {
        $attemptNumber = (int) NotificationMessageAttempt::query()
            ->where('notification_message_id', $message->id)
            ->max('attempt_number') + 1;

        return NotificationMessageAttempt::query()->create([
            'tenant_id' => $message->tenant_id,
            'notification_message_id' => $message->id,
            'attempt_number' => $attemptNumber,
            'provider' => $provider,
            'provider_reference' => $reference,
            'status' => $status,
            'request_payload' => [
                'channel' => $message->channel,
                'recipient_address' => $message->recipient_address,
                'subject' => $message->subject,
            ],
            'response_payload' => $response,
            'attempted_at' => now(),
            'delivered_at' => $status === NotificationMessageStatus::DELIVERED ? now() : null,
            'failed_at' => $status === NotificationMessageStatus::FAILED ? now() : null,
            'failure_reason' => $status === NotificationMessageStatus::FAILED ? ($response['reason'] ?? null) : null,
        ]);
    }

    private function shouldForceDomainFailure(NotificationMessage $message): bool
    {
        if (($message->metadata['simulate_failure'] ?? false) === true) {
            return true;
        }

        return $message->recipient_address === null || $message->recipient_address === '';
    }
}
