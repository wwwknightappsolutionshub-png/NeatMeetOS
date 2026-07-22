<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Contracts\PaymentProviderAttemptContract;
use App\Domains\Integrations\DTO\OutboundProviderDispatchDto;
use App\Domains\Integrations\DTO\ProviderDispatchResultDto;
use App\Domains\Integrations\Enums\ProviderAttemptStatus;
use App\Domains\Integrations\Enums\ProviderCategory;
use App\Domains\Integrations\Enums\ProviderSourceDomain;
use App\Domains\Integrations\Enums\ProviderSourceType;
use App\Domains\Marketing\Models\MarketingMessage;
use App\Domains\Notifications\Enums\NotificationMessageStatus;
use App\Domains\Notifications\Models\NotificationMessage;
use App\Domains\Payments\Models\PaymentTransaction;

/**
 * Bridges domain-owned records to the shared provider dispatch layer.
 *
 * Domain message/transaction tables remain authoritative; provider_delivery_attempts
 * is the cross-domain external traffic ledger (dual-logged alongside domain attempts).
 */
class ProviderDispatchBridge implements PaymentProviderAttemptContract
{
    public function __construct(
        private readonly ProviderDispatchService $dispatch,
    ) {}

    public function recordNotificationDispatch(
        NotificationMessage $message,
        ?string $providerReference = null,
        ?string $outcomeStatus = null,
    ): ProviderDispatchResultDto {
        $forcedStatus = null;
        $forcedFailure = null;

        if ($outcomeStatus === NotificationMessageStatus::SUPPRESSED) {
            $forcedStatus = ProviderAttemptStatus::SUPPRESSED;
            $forcedFailure = $message->failure_reason;
        } elseif ($outcomeStatus === NotificationMessageStatus::FAILED) {
            $forcedStatus = ProviderAttemptStatus::FAILED;
            $forcedFailure = $message->failure_reason ?? 'Simulated provider failure.';
        }

        return $this->dispatch->dispatch(new OutboundProviderDispatchDto(
            tenantId: $message->tenant_id,
            providerCategory: ProviderCategory::fromNotificationChannel($message->channel),
            sourceDomain: ProviderSourceDomain::NOTIFICATIONS,
            sourceType: ProviderSourceType::NOTIFICATION_MESSAGE,
            sourceId: $message->id,
            relatedClientId: $message->client_id,
            relatedAppointmentId: $message->appointment_id,
            purpose: $message->purpose,
            recipientAddress: $message->channel === 'sms' || $message->channel === 'whatsapp'
                ? null
                : $message->recipient_address,
            recipientPhone: in_array($message->channel, ['sms', 'whatsapp'], true)
                ? $message->recipient_address
                : null,
            subject: $message->subject,
            bodyText: $message->body_text,
            payload: [
                'channel' => $message->channel,
                'template_key' => $message->template_key,
            ],
            idempotencyKey: 'notification:'.$message->id,
            metadata: $message->metadata ?? [],
            forcedStatus: $forcedStatus,
            forcedFailureMessage: $forcedFailure,
            providerReference: $providerReference,
        ));
    }

    public function recordMarketingDispatch(
        MarketingMessage $message,
        ?string $providerReference = null,
        ?string $outcomeStatus = null,
    ): ProviderDispatchResultDto {
        $forcedStatus = null;
        $forcedFailure = null;

        if ($outcomeStatus === 'failed') {
            $forcedStatus = ProviderAttemptStatus::FAILED;
            $forcedFailure = $message->error_message ?? 'Simulated provider failure.';
        }

        $purpose = $message->marketing_run_id !== null ? 'campaign' : 'workflow';

        return $this->dispatch->dispatch(new OutboundProviderDispatchDto(
            tenantId: $message->tenant_id,
            providerCategory: ProviderCategory::fromMarketingChannel($message->channel),
            sourceDomain: ProviderSourceDomain::MARKETING,
            sourceType: ProviderSourceType::MARKETING_MESSAGE,
            sourceId: $message->id,
            relatedClientId: $message->client_id,
            purpose: $purpose,
            recipientAddress: $message->channel === 'sms' ? null : $message->recipient_address,
            recipientPhone: $message->channel === 'sms' ? $message->recipient_address : null,
            subject: $message->subject,
            bodyText: $message->rendered_body_text,
            payload: [
                'channel' => $message->channel,
                'marketing_run_id' => $message->marketing_run_id,
                'workflow_execution_id' => $message->workflow_execution_id,
            ],
            idempotencyKey: 'marketing:'.$message->id,
            metadata: [],
            forcedStatus: $forcedStatus,
            forcedFailureMessage: $forcedFailure,
            providerReference: $providerReference,
        ));
    }

    public function recordPaymentLink(PaymentTransaction $transaction): ProviderDispatchResultDto
    {
        return $this->dispatch->dispatch(new OutboundProviderDispatchDto(
            tenantId: $transaction->tenant_id,
            providerCategory: ProviderCategory::PAYMENT_GATEWAY,
            sourceDomain: ProviderSourceDomain::PAYMENTS,
            sourceType: ProviderSourceType::PAYMENT_LINK,
            sourceId: $transaction->id,
            relatedClientId: $transaction->client_id,
            relatedAppointmentId: $transaction->appointment_id,
            relatedPaymentTransactionId: $transaction->id,
            purpose: 'payment_link',
            recipientAddress: $transaction->client?->email,
            payload: [
                'amount_cents' => $transaction->amount_cents,
                'currency' => $transaction->currency,
                'provider' => $transaction->provider,
            ],
            idempotencyKey: 'payment_link:'.$transaction->id,
            providerReference: $transaction->provider_reference,
        ));
    }
}
