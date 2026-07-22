<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientNotice;
use App\Domains\Crm\Services\ClientNoticeService;
use App\Domains\Notifications\Enums\NotificationAttemptProvider;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationMessageStatus;
use App\Domains\Notifications\Models\NotificationMessage;
use App\Domains\Notifications\Models\NotificationMessageAttempt;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Native operational transports (member in-app inbox).
 * Email/SMS/WhatsApp remain on ProviderDispatchBridge.
 */
class NotificationNativeChannelDispatchService
{
    public function __construct(
        private readonly ClientNoticeService $notices,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function supports(string $channel): bool
    {
        return NotificationChannel::isNative($channel);
    }

    /**
     * @return array{message: NotificationMessage, attempt: NotificationMessageAttempt, simulated: bool}
     */
    public function dispatch(NotificationMessage $message, NotificationMessageAttempt $attempt): array
    {
        return match ($message->channel) {
            NotificationChannel::IN_APP => $this->dispatchInApp($message, $attempt),
            default => throw new \InvalidArgumentException('Unsupported native notification channel: '.$message->channel),
        };
    }

    /**
     * @return array{message: NotificationMessage, attempt: NotificationMessageAttempt, simulated: bool}
     */
    private function dispatchInApp(NotificationMessage $message, NotificationMessageAttempt $attempt): array
    {
        return DB::transaction(function () use ($message, $attempt) {
            if ($message->client_id === null) {
                return $this->fail($message, $attempt, 'In-app requires a client recipient.');
            }

            $client = Client::query()->find($message->client_id);
            if ($client === null) {
                return $this->fail($message, $attempt, 'Client not found for in-app notice.');
            }

            $href = data_get($message->metadata ?? [], 'manage_url')
                ?? data_get($message->metadata ?? [], 'href');

            $notice = $this->notices->createForClient($client, [
                'type' => ClientNotice::TYPE_OPERATIONAL_IN_APP,
                'title' => $message->subject ?: 'Message from your salon',
                'body' => (string) ($message->body_text ?? strip_tags((string) ($message->body_html ?? ''))),
                'href' => is_string($href) && $href !== '' ? $href : null,
                'data' => [
                    'notification_message_id' => $message->id,
                    'purpose' => $message->purpose,
                    'source_type' => $message->source_type,
                ],
            ]);

            $message->status = NotificationMessageStatus::SENT;
            $message->sent_at = now();
            $message->delivered_at = now();
            $message->failure_reason = null;
            $message->save();

            $attempt->status = NotificationMessageStatus::SENT;
            $attempt->provider = NotificationAttemptProvider::IN_APP;
            $attempt->provider_reference = $notice->id;
            $attempt->response_payload = [
                'outcome' => 'sent',
                'client_notice_id' => $notice->id,
                'simulated' => false,
            ];
            $attempt->attempted_at = now();
            $attempt->delivered_at = now();
            $attempt->save();

            $this->auditLogger->log('notification_message.sent', $message, null, [
                'channel' => NotificationChannel::IN_APP,
                'provider' => NotificationAttemptProvider::IN_APP,
                'client_notice_id' => $notice->id,
            ]);

            return [
                'message' => $message->fresh(['attempts']),
                'attempt' => $attempt->fresh(),
                'simulated' => false,
            ];
        });
    }

    /**
     * @return array{message: NotificationMessage, attempt: NotificationMessageAttempt, simulated: bool}
     */
    private function fail(NotificationMessage $message, NotificationMessageAttempt $attempt, string $error): array
    {
        $message->status = NotificationMessageStatus::FAILED;
        $message->failed_at = now();
        $message->failure_reason = $error;
        $message->save();

        $attempt->status = NotificationMessageStatus::FAILED;
        $attempt->provider = NotificationAttemptProvider::IN_APP;
        $attempt->failure_reason = $error;
        $attempt->response_payload = ['outcome' => 'failed', 'simulated' => false];
        $attempt->attempted_at = now();
        $attempt->failed_at = now();
        $attempt->save();

        $this->auditLogger->log('notification_message.failed', $message, null, [
            'channel' => NotificationChannel::IN_APP,
            'provider' => NotificationAttemptProvider::IN_APP,
            'reason' => $error,
        ]);

        return [
            'message' => $message->fresh(['attempts']),
            'attempt' => $attempt->fresh(),
            'simulated' => false,
        ];
    }
}
