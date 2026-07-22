<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Integrations\Enums\ProviderAttemptStatus;
use App\Domains\Integrations\Services\ProviderDispatchBridge;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingMessageStatus;
use App\Domains\Marketing\Models\MarketingMessage;
use App\Domains\Marketing\Models\MarketingMessageAttempt;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Marketing message transport via Integrations provider dispatch (live or simulation fallback).
 */
class MarketingDispatchSimulationService
{
    public const PROVIDER = 'simulation';

    public function __construct(
        private readonly MarketingScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
        private readonly ProviderDispatchBridge $providerBridge,
        private readonly MarketingNativeChannelDispatchService $nativeChannels,
    ) {}

    /**
     * @return array{message: MarketingMessage, attempt: MarketingMessageAttempt|null, simulated: bool}
     */
    public function simulate(MarketingMessage $message): array
    {
        return $this->dispatch($message);
    }

    /**
     * @return array{message: MarketingMessage, attempt: MarketingMessageAttempt|null, simulated: bool}
     */
    public function dispatch(MarketingMessage $message): array
    {
        $this->scope->assertTenantModel($message);

        if ($message->status === MarketingMessageStatus::SKIPPED) {
            return [
                'message' => $message,
                'attempt' => null,
                'simulated' => false,
            ];
        }

        return DB::transaction(function () use ($message) {
            $message->status = MarketingMessageStatus::PROCESSING;
            $message->save();

            $attempt = MarketingMessageAttempt::query()->create([
                'tenant_id' => $message->tenant_id,
                'marketing_message_id' => $message->id,
                'status' => MarketingMessageStatus::PROCESSING,
                'attempted_at' => now(),
                'provider' => self::PROVIDER,
                'payload_json' => [
                    'channel' => $message->channel,
                    'recipient_address' => $message->recipient_address,
                    'subject' => $message->subject,
                ],
            ]);

            if ($this->shouldForceDomainFailure($message)) {
                $message->status = MarketingMessageStatus::FAILED;
                $message->failed_at = now();
                $message->error_message = 'Simulated provider failure.';
                $message->save();

                $attempt->status = MarketingMessageStatus::FAILED;
                $attempt->error_message = 'Simulated provider failure.';
                $attempt->response_json = ['simulated' => true, 'outcome' => 'failed'];
                $attempt->save();

                $this->auditLogger->log('marketing_message.failed', $message, null, [
                    'channel' => $message->channel,
                    'provider' => self::PROVIDER,
                    'reason' => 'simulated_failure',
                ]);

                if (! MarketingChannel::isNative((string) $message->channel)) {
                    $this->providerBridge->recordMarketingDispatch($message, null, 'failed');
                }

                return [
                    'message' => $message->fresh(['attempts']),
                    'attempt' => $attempt->fresh(),
                    'simulated' => true,
                ];
            }

            if ($this->nativeChannels->supports((string) $message->channel)) {
                return $this->nativeChannels->dispatch($message, $attempt);
            }

            $result = $this->providerBridge->recordMarketingDispatch($message, null, null);
            $provider = $result->driver ?: self::PROVIDER;
            $failed = in_array($result->status, [
                ProviderAttemptStatus::FAILED,
                ProviderAttemptStatus::CANCELLED,
            ], true);

            if ($failed) {
                $message->status = MarketingMessageStatus::FAILED;
                $message->failed_at = now();
                $message->error_message = $result->failureMessage ?? 'Provider dispatch failed.';
                $message->save();

                $attempt->status = MarketingMessageStatus::FAILED;
                $attempt->provider = $provider;
                $attempt->error_message = $message->error_message;
                $attempt->provider_reference = $result->providerReference;
                $attempt->response_json = [
                    'simulated' => $result->simulated,
                    'outcome' => 'failed',
                    'provider_delivery_attempt_id' => $result->providerDeliveryAttemptId,
                ];
                $attempt->save();

                $this->auditLogger->log('marketing_message.failed', $message, null, [
                    'channel' => $message->channel,
                    'provider' => $provider,
                    'simulated' => $result->simulated,
                ]);
            } else {
                $message->status = MarketingMessageStatus::SENT;
                $message->sent_at = now();
                $message->provider_message_reference = $result->providerReference;
                $message->error_message = null;
                $message->save();

                $attempt->status = MarketingMessageStatus::SENT;
                $attempt->provider = $provider;
                $attempt->provider_reference = $result->providerReference;
                $attempt->response_json = [
                    'simulated' => $result->simulated,
                    'outcome' => 'sent',
                    'reference' => $result->providerReference,
                    'provider_delivery_attempt_id' => $result->providerDeliveryAttemptId,
                ];
                $attempt->save();

                $this->auditLogger->log('marketing_message.sent', $message, null, [
                    'channel' => $message->channel,
                    'provider' => $provider,
                    'provider_reference' => $result->providerReference,
                    'simulated' => $result->simulated,
                ]);
            }

            return [
                'message' => $message->fresh(['attempts']),
                'attempt' => $attempt->fresh(),
                'simulated' => $result->simulated,
            ];
        });
    }

    /**
     * @return array{processed: int, sent: int, failed: int, skipped: int}
     */
    public function simulateRun(string $runId): array
    {
        $run = $this->scope->findRun($runId);

        $messages = MarketingMessage::query()
            ->where('marketing_run_id', $run->id)
            ->whereIn('status', [MarketingMessageStatus::PENDING, MarketingMessageStatus::PROCESSING])
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($messages as $message) {
            $result = $this->dispatch($message);
            if ($result['message']->status === MarketingMessageStatus::SENT) {
                $sent++;
            } elseif ($result['message']->status === MarketingMessageStatus::FAILED) {
                $failed++;
            }
        }

        $skipped = MarketingMessage::query()
            ->where('marketing_run_id', $run->id)
            ->where('status', MarketingMessageStatus::SKIPPED)
            ->count();

        $summary = [
            'processed' => $messages->count(),
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
        ];

        $this->auditLogger->log('marketing_run.dispatched', $run, null, $summary);

        return $summary;
    }

    private function shouldForceDomainFailure(MarketingMessage $message): bool
    {
        return $message->recipient_address === null || $message->recipient_address === '';
    }
}
