<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Marketing\Enums\MarketingMessageStatus;
use App\Domains\Marketing\Models\MarketingMessage;
use App\Domains\Marketing\Models\MarketingMessageAttempt;
use App\Jobs\DispatchMarketingMessageJob;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Operational marketing delivery (Module 10B + Integrations live transport).
 *
 * Dispatch runs through ProviderDispatchBridge (Mailgun/Twilio when configured).
 * Admin mark* helpers remain for engagement/testing corrections.
 */
class MarketingDeliveryService
{
    public const PROVIDER = 'simulation';

    public function __construct(
        private readonly MarketingScopeValidator $scope,
        private readonly MarketingDispatchSimulationService $simulation,
        private readonly MarketingSuppressionService $suppressionService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Dispatch a single message via provider integrations (queued job when async).
     */
    public function dispatchMessage(MarketingMessage $message): MarketingMessage
    {
        $this->scope->assertTenantModel($message);

        if ($message->status === MarketingMessageStatus::SKIPPED
            || $message->status === MarketingMessageStatus::SUPPRESSED) {
            return $message;
        }

        if (config('queue.default') === 'sync') {
            $result = $this->simulation->dispatch($message);

            return $result['message'];
        }

        DispatchMarketingMessageJob::dispatch($message->tenant_id, $message->id);

        return $message->fresh(['attempts', 'client']);
    }

    /**
     * Mark a message as delivered (simulated provider confirmation).
     */
    public function markDelivered(MarketingMessage $message): MarketingMessage
    {
        $this->scope->assertTenantModel($message);
        $this->assertOperationalStatus($message, [MarketingMessageStatus::SENT, MarketingMessageStatus::PROCESSING]);

        return DB::transaction(function () use ($message) {
            $message->status = MarketingMessageStatus::DELIVERED;
            $message->delivered_at = now();
            $message->save();

            $this->auditLogger->log('marketing_message.delivered', $message, null, [
                'channel' => $message->channel,
                'provider' => self::PROVIDER,
            ]);

            return $message->fresh(['attempts', 'client']);
        });
    }

    /**
     * Mark a message as opened (simulated engagement tracking).
     */
    public function markOpened(MarketingMessage $message): MarketingMessage
    {
        $this->scope->assertTenantModel($message);
        $this->assertOperationalStatus($message, [
            MarketingMessageStatus::SENT,
            MarketingMessageStatus::DELIVERED,
        ]);

        $message->opened_at = now();
        $message->save();

        return $message->fresh(['attempts', 'client']);
    }

    /**
     * Mark a message as clicked (simulated engagement tracking).
     */
    public function markClicked(MarketingMessage $message): MarketingMessage
    {
        $this->scope->assertTenantModel($message);
        $this->assertOperationalStatus($message, [
            MarketingMessageStatus::SENT,
            MarketingMessageStatus::DELIVERED,
        ]);

        $message->clicked_at = now();
        if ($message->opened_at === null) {
            $message->opened_at = now();
        }
        $message->save();

        return $message->fresh(['attempts', 'client']);
    }

    /**
     * Mark a message as failed with a failure category.
     */
    public function markFailed(MarketingMessage $message, ?string $failureCategory = null, ?string $errorMessage = null): MarketingMessage
    {
        $this->scope->assertTenantModel($message);

        return DB::transaction(function () use ($message, $failureCategory, $errorMessage) {
            $message->status = MarketingMessageStatus::FAILED;
            $message->failed_at = now();
            $message->failure_category = $failureCategory ?? 'manual_failure';
            $message->error_message = $errorMessage ?? 'Marked failed by admin.';
            $message->save();

            MarketingMessageAttempt::query()->create([
                'tenant_id' => $message->tenant_id,
                'marketing_message_id' => $message->id,
                'status' => MarketingMessageStatus::FAILED,
                'attempted_at' => now(),
                'provider' => self::PROVIDER,
                'failure_category' => $message->failure_category,
                'error_message' => $message->error_message,
                'response_json' => ['simulated' => true, 'outcome' => 'failed', 'admin_action' => true],
            ]);

            $this->auditLogger->log('marketing_message.failed', $message, null, [
                'channel' => $message->channel,
                'failure_category' => $message->failure_category,
            ]);

            return $message->fresh(['attempts', 'client']);
        });
    }

    /**
     * Unsubscribe a message recipient: mark message + create suppression.
     */
    public function unsubscribe(MarketingMessage $message): MarketingMessage
    {
        $this->scope->assertTenantModel($message);

        return DB::transaction(function () use ($message) {
            $message->status = MarketingMessageStatus::UNSUBSCRIBED;
            $message->unsubscribed_at = now();
            $message->save();

            if ($message->client_id !== null) {
                $client = $this->scope->findClient($message->client_id);
                $this->suppressionService->suppressFromUnsubscribe(
                    $client,
                    $message->channel,
                    $message->recipient_address,
                    'Unsubscribed via message '.$message->id,
                );
            }

            $this->auditLogger->log('marketing_message.unsubscribed', $message, null, [
                'channel' => $message->channel,
                'recipient_address' => $message->recipient_address,
            ]);

            return $message->fresh(['attempts', 'client']);
        });
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function assertOperationalStatus(MarketingMessage $message, array $allowed): void
    {
        if (! in_array($message->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ["Message must be in one of [".implode(', ', $allowed)."] to perform this action. Current: {$message->status}."],
            ]);
        }
    }
}
