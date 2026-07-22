<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\MemberPushSubscription;
use App\Domains\Crm\Services\ClientNoticeService;
use App\Domains\Crm\Services\MemberPushDispatchService;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingMessageStatus;
use App\Domains\Marketing\Models\MarketingMessage;
use App\Domains\Marketing\Models\MarketingMessageAttempt;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Internal marketing transports (Web Push + member in-app inbox).
 * External email/SMS/WhatsApp remain on ProviderDispatchBridge.
 */
class MarketingNativeChannelDispatchService
{
    public const PROVIDER_PUSH = 'webpush';

    public const PROVIDER_PUSH_SIMULATION = 'webpush_simulation';

    public const PROVIDER_IN_APP = 'in_app';

    public function __construct(
        private readonly MemberPushDispatchService $push,
        private readonly ClientNoticeService $notices,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function supports(string $channel): bool
    {
        return in_array($channel, [MarketingChannel::PUSH, MarketingChannel::IN_APP], true);
    }

    /**
     * @return array{message: MarketingMessage, attempt: MarketingMessageAttempt, simulated: bool}
     */
    public function dispatch(MarketingMessage $message, MarketingMessageAttempt $attempt): array
    {
        return match ($message->channel) {
            MarketingChannel::PUSH => $this->dispatchPush($message, $attempt),
            MarketingChannel::IN_APP => $this->dispatchInApp($message, $attempt),
            default => throw new \InvalidArgumentException('Unsupported native marketing channel: '.$message->channel),
        };
    }

    /**
     * @return array{message: MarketingMessage, attempt: MarketingMessageAttempt, simulated: bool}
     */
    private function dispatchPush(MarketingMessage $message, MarketingMessageAttempt $attempt): array
    {
        return DB::transaction(function () use ($message, $attempt) {
            if ($message->client_id === null) {
                return $this->fail($message, $attempt, self::PROVIDER_PUSH, 'Push requires a client recipient.');
            }

            $subscriptionCount = MemberPushSubscription::query()
                ->where('client_id', $message->client_id)
                ->count();

            if ($subscriptionCount === 0) {
                return $this->fail($message, $attempt, self::PROVIDER_PUSH, 'Client has no push subscriptions.');
            }

            $payload = [
                'title' => $message->subject ?: 'NeatMeet',
                'body' => (string) ($message->rendered_body_text ?? ''),
                'url' => $this->extractHref($message),
                'data' => [
                    'marketing_message_id' => $message->id,
                    'channel' => MarketingChannel::PUSH,
                ],
            ];

            if (! $this->push->isConfigured()) {
                return $this->succeed(
                    $message,
                    $attempt,
                    self::PROVIDER_PUSH_SIMULATION,
                    'push-sim-'.$message->id,
                    true,
                    ['outcome' => 'sent', 'simulated' => true, 'reason' => 'vapid_not_configured'],
                );
            }

            $result = $this->push->sendToClient($message->client_id, $payload);

            if (($result['sent'] ?? 0) > 0) {
                return $this->succeed(
                    $message,
                    $attempt,
                    self::PROVIDER_PUSH,
                    'push:'.$message->client_id,
                    false,
                    $result,
                );
            }

            return $this->fail(
                $message,
                $attempt,
                self::PROVIDER_PUSH,
                'Web push delivery failed for all subscriptions.',
                $result,
            );
        });
    }

    /**
     * @return array{message: MarketingMessage, attempt: MarketingMessageAttempt, simulated: bool}
     */
    private function dispatchInApp(MarketingMessage $message, MarketingMessageAttempt $attempt): array
    {
        return DB::transaction(function () use ($message, $attempt) {
            if ($message->client_id === null) {
                return $this->fail($message, $attempt, self::PROVIDER_IN_APP, 'In-app requires a client recipient.');
            }

            $client = Client::query()->find($message->client_id);
            if ($client === null) {
                return $this->fail($message, $attempt, self::PROVIDER_IN_APP, 'Client not found for in-app notice.');
            }

            $notice = $this->notices->createForClient($client, [
                'marketing_message_id' => $message->id,
                'title' => $message->subject ?: 'Message from your salon',
                'body' => (string) ($message->rendered_body_text ?? strip_tags((string) ($message->rendered_body_html ?? ''))),
                'href' => $this->extractHref($message),
                'data' => [
                    'marketing_message_id' => $message->id,
                    'purpose' => $message->purpose,
                ],
            ]);

            $message->status = MarketingMessageStatus::SENT;
            $message->sent_at = now();
            $message->delivered_at = now();
            $message->provider_message_reference = 'notice:'.$notice->id;
            $message->error_message = null;
            $message->save();

            $attempt->status = MarketingMessageStatus::SENT;
            $attempt->provider = self::PROVIDER_IN_APP;
            $attempt->provider_reference = $notice->id;
            $attempt->response_json = [
                'outcome' => 'sent',
                'client_notice_id' => $notice->id,
                'simulated' => false,
            ];
            $attempt->save();

            $this->auditLogger->log('marketing_message.sent', $message, null, [
                'channel' => MarketingChannel::IN_APP,
                'provider' => self::PROVIDER_IN_APP,
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
     * @param  array<string, mixed>  $response
     * @return array{message: MarketingMessage, attempt: MarketingMessageAttempt, simulated: bool}
     */
    private function succeed(
        MarketingMessage $message,
        MarketingMessageAttempt $attempt,
        string $provider,
        string $reference,
        bool $simulated,
        array $response,
    ): array {
        $message->status = MarketingMessageStatus::SENT;
        $message->sent_at = now();
        $message->provider_message_reference = $reference;
        $message->error_message = null;
        $message->save();

        $attempt->status = MarketingMessageStatus::SENT;
        $attempt->provider = $provider;
        $attempt->provider_reference = $reference;
        $attempt->response_json = array_merge(['outcome' => 'sent', 'simulated' => $simulated], $response);
        $attempt->save();

        $this->auditLogger->log('marketing_message.sent', $message, null, [
            'channel' => $message->channel,
            'provider' => $provider,
            'simulated' => $simulated,
        ]);

        return [
            'message' => $message->fresh(['attempts']),
            'attempt' => $attempt->fresh(),
            'simulated' => $simulated,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $response
     * @return array{message: MarketingMessage, attempt: MarketingMessageAttempt, simulated: bool}
     */
    private function fail(
        MarketingMessage $message,
        MarketingMessageAttempt $attempt,
        string $provider,
        string $error,
        ?array $response = null,
    ): array {
        $message->status = MarketingMessageStatus::FAILED;
        $message->failed_at = now();
        $message->error_message = $error;
        $message->save();

        $attempt->status = MarketingMessageStatus::FAILED;
        $attempt->provider = $provider;
        $attempt->error_message = $error;
        $attempt->response_json = array_merge(['outcome' => 'failed', 'simulated' => false], $response ?? []);
        $attempt->save();

        $this->auditLogger->log('marketing_message.failed', $message, null, [
            'channel' => $message->channel,
            'provider' => $provider,
            'reason' => $error,
        ]);

        return [
            'message' => $message->fresh(['attempts']),
            'attempt' => $attempt->fresh(),
            'simulated' => false,
        ];
    }

    private function extractHref(MarketingMessage $message): ?string
    {
        $vars = $message->variables_snapshot_json ?? [];
        if (is_array($vars)) {
            $booking = data_get($vars, 'booking.link');
            if (is_string($booking) && $booking !== '') {
                return $booking;
            }
            $review = data_get($vars, 'review.link');
            if (is_string($review) && $review !== '') {
                return $review;
            }
        }

        return null;
    }
}
