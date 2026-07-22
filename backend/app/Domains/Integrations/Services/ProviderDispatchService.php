<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\DTO\OutboundProviderDispatchDto;
use App\Domains\Integrations\DTO\ProviderDispatchResultDto;
use App\Domains\Integrations\Enums\ProviderAttemptDirection;
use App\Domains\Integrations\Enums\ProviderAttemptStatus;
use App\Domains\Integrations\Enums\ProviderCategory;
use App\Domains\Integrations\Enums\ProviderDriver;
use App\Domains\Integrations\Models\ProviderAccount;
use App\Domains\Integrations\Models\ProviderDeliveryAttempt;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Normalized outbound dispatch for provider integrations (Module 13A + 13B).
 *
 * Simulation-first: when no active account exists, credentials are invalid, or the
 * resolved driver is simulation/manual, attempts complete locally without SDK calls.
 */
class ProviderDispatchService
{
    public function __construct(
        private readonly ProviderRoutingService $routing,
        private readonly ProviderAdapterRegistry $adapters,
        private readonly ProviderCredentialValidator $credentials,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function dispatch(OutboundProviderDispatchDto $dto): ProviderDispatchResultDto
    {
        if ($dto->idempotencyKey !== null) {
            $existing = ProviderDeliveryAttempt::query()
                ->where('tenant_id', $dto->tenantId)
                ->where('idempotency_key', $dto->idempotencyKey)
                ->first();

            if ($existing !== null) {
                return ProviderDispatchResultDto::fromAttempt(
                    $existing,
                    simulated: $this->isSimulatedDriver($existing->metadata_json['driver'] ?? ProviderDriver::SIMULATION),
                    driver: $existing->metadata_json['driver'] ?? null,
                );
            }
        }

        return DB::transaction(function () use ($dto) {
            $account = $this->routing->resolveDefaultAccount($dto->providerCategory, $dto->providerAccountId);
            $driver = $account?->driver ?? ProviderDriver::SIMULATION;
            $simulated = $this->isSimulatedDriver($driver);

            $attempt = ProviderDeliveryAttempt::query()->create([
                'tenant_id' => $dto->tenantId,
                'provider_account_id' => $account?->id,
                'category' => $dto->providerCategory,
                'source_domain' => $dto->sourceDomain,
                'source_type' => $dto->sourceType,
                'source_id' => $dto->sourceId,
                'related_client_id' => $dto->relatedClientId,
                'related_appointment_id' => $dto->relatedAppointmentId,
                'related_payment_transaction_id' => $dto->relatedPaymentTransactionId,
                'direction' => ProviderAttemptDirection::OUTBOUND,
                'purpose' => $dto->purpose,
                'recipient_address' => $dto->recipientAddress,
                'recipient_phone' => $dto->recipientPhone,
                'subject' => $dto->subject,
                'payload_json' => $this->buildPayload($dto),
                'idempotency_key' => $dto->idempotencyKey,
                'status' => ProviderAttemptStatus::PENDING,
                'requested_at' => now(),
                'metadata_json' => array_merge($dto->metadata ?? [], [
                    'driver' => $driver,
                    'simulation_fallback' => $account === null,
                ]),
            ]);

            $this->auditLogger->log('provider_attempt.created', $attempt, null, [
                'category' => $attempt->category,
                'source_domain' => $attempt->source_domain,
                'source_type' => $attempt->source_type,
                'driver' => $driver,
            ]);

            if ($dto->forcedStatus === ProviderAttemptStatus::SUPPRESSED) {
                $attempt->status = ProviderAttemptStatus::SUPPRESSED;
                $attempt->failure_message = $dto->forcedFailureMessage;
                $attempt->save();

                return ProviderDispatchResultDto::fromAttempt($attempt, $simulated, $driver);
            }

            if ($dto->forcedStatus === ProviderAttemptStatus::FAILED) {
                $attempt->status = ProviderAttemptStatus::FAILED;
                $attempt->failed_at = now();
                $attempt->failure_message = $dto->forcedFailureMessage ?? 'Simulated provider failure.';
                $attempt->save();

                $this->auditLogger->log('provider_attempt.failed', $attempt, null, [
                    'driver' => $driver,
                    'reason' => 'domain_forced_failure',
                ]);

                return ProviderDispatchResultDto::fromAttempt($attempt, $simulated, $driver);
            }

            if ($simulated) {
                return $this->completeSimulatedAttempt($attempt, $dto, $driver);
            }

            $credentialCheck = $account !== null
                ? $this->credentials->validate($account)
                : ['valid' => false, 'missing' => ['no_account']];

            if ($account === null || ! $credentialCheck['valid'] || ! $this->adapters->hasLiveAdapter($driver)) {
                $attempt->metadata_json = array_merge($attempt->metadata_json ?? [], [
                    'simulation_fallback' => true,
                    'live_fallback_reason' => $account === null
                        ? 'no_active_account'
                        : (! $credentialCheck['valid'] ? 'missing_credentials' : 'no_adapter'),
                    'missing_credential_fields' => $credentialCheck['missing'] ?? [],
                    'intended_driver' => $driver,
                ]);
                $attempt->save();

                return $this->completeSimulatedAttempt($attempt, $dto, ProviderDriver::SIMULATION);
            }

            $adapter = $this->adapters->resolve($driver);
            $adapterResult = $adapter->dispatch($account, $dto);

            return $this->completeAdapterAttempt($attempt, $adapterResult, $driver);
        });
    }

    public function retry(ProviderDeliveryAttempt $attempt): ProviderDispatchResultDto
    {
        if ($attempt->status !== ProviderAttemptStatus::FAILED) {
            abort(422, 'Only failed attempts can be retried.');
        }

        $metadata = $attempt->metadata_json ?? [];
        $driver = $metadata['driver'] ?? ProviderDriver::SIMULATION;

        if (! $this->isSimulatedDriver($driver)) {
            abort(422, 'Live provider retry is not available in this release.');
        }

        $dto = new OutboundProviderDispatchDto(
            tenantId: $attempt->tenant_id,
            providerCategory: $attempt->category,
            sourceDomain: $attempt->source_domain,
            sourceType: $attempt->source_type,
            sourceId: $attempt->source_id,
            relatedClientId: $attempt->related_client_id,
            relatedAppointmentId: $attempt->related_appointment_id,
            relatedPaymentTransactionId: $attempt->related_payment_transaction_id,
            purpose: $attempt->purpose,
            recipientAddress: $attempt->recipient_address,
            recipientPhone: $attempt->recipient_phone,
            subject: $attempt->subject,
            bodyText: $attempt->payload_json['body_text'] ?? null,
            payload: $attempt->payload_json,
            providerAccountId: $attempt->provider_account_id,
            metadata: array_merge($metadata, ['retry_of' => $attempt->id]),
        );

        return $this->completeSimulatedAttempt($attempt->fresh(), $dto, $driver);
    }

    private function completeSimulatedAttempt(
        ProviderDeliveryAttempt $attempt,
        OutboundProviderDispatchDto $dto,
        string $driver,
    ): ProviderDispatchResultDto {
        $shouldFail = $dto->forcedStatus === ProviderAttemptStatus::FAILED
            || $this->shouldSimulateFailure($dto);

        if ($shouldFail) {
            $attempt->status = ProviderAttemptStatus::FAILED;
            $attempt->failed_at = now();
            $attempt->failure_message = $dto->forcedFailureMessage ?? 'Simulated provider failure.';
            $attempt->save();

            $this->auditLogger->log('provider_attempt.failed', $attempt, null, [
                'driver' => $driver,
                'reason' => 'simulated_failure',
            ]);

            return ProviderDispatchResultDto::fromAttempt($attempt, true, $driver);
        }

        $reference = $dto->providerReference ?? 'pint_'.Str::lower(Str::random(12));

        $attempt->status = ProviderAttemptStatus::DELIVERED;
        $attempt->provider_reference = $reference;
        $attempt->sent_at = now();
        $attempt->delivered_at = now();
        $attempt->metadata_json = array_merge($attempt->metadata_json ?? [], [
            'simulated' => true,
        ]);
        $attempt->save();

        $this->auditLogger->log('provider_attempt.sent', $attempt, null, [
            'driver' => $driver,
            'provider_reference' => $reference,
        ]);

        return ProviderDispatchResultDto::fromAttempt($attempt, true, $driver);
    }

    private function completeAdapterAttempt(
        ProviderDeliveryAttempt $attempt,
        \App\Domains\Integrations\DTO\ProviderAdapterResultDto $result,
        string $driver,
    ): ProviderDispatchResultDto {
        $metadata = array_merge($attempt->metadata_json ?? [], $result->metadata, [
            'simulated' => $result->simulated,
            'remote_status' => $result->remoteStatus,
        ]);

        if (! $result->success) {
            $attempt->status = ProviderAttemptStatus::FAILED;
            $attempt->failed_at = now();
            $attempt->failure_message = $result->failureMessage;
            $attempt->failure_code = $result->failureCode;
            $attempt->metadata_json = $metadata;
            $attempt->save();

            $this->auditLogger->log('provider_attempt.failed', $attempt, null, [
                'driver' => $driver,
                'reason' => 'adapter_failure',
            ]);

            return ProviderDispatchResultDto::fromAttempt($attempt, false, $driver);
        }

        $attempt->status = ProviderAttemptStatus::DELIVERED;
        $attempt->provider_reference = $result->providerReference;
        $attempt->sent_at = now();
        $attempt->delivered_at = now();
        $attempt->metadata_json = $metadata;
        $attempt->save();

        $this->auditLogger->log('provider_attempt.sent', $attempt, null, [
            'driver' => $driver,
            'provider_reference' => $result->providerReference,
            'transport' => $result->metadata['transport'] ?? 'live',
        ]);

        return ProviderDispatchResultDto::fromAttempt($attempt, $result->simulated, $driver);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(OutboundProviderDispatchDto $dto): array
    {
        $payload = $dto->payload ?? [];

        if ($dto->bodyText !== null) {
            $payload['body_text'] = $dto->bodyText;
        }

        return $payload;
    }

    private function shouldSimulateFailure(OutboundProviderDispatchDto $dto): bool
    {
        if ($dto->providerCategory === ProviderCategory::PAYMENT_GATEWAY) {
            return ($dto->metadata['simulate_failure'] ?? false) === true;
        }

        if (($dto->metadata['simulate_failure'] ?? false) === true) {
            return true;
        }

        $address = $dto->recipientAddress ?? $dto->recipientPhone ?? '';

        if ($address === '') {
            return true;
        }

        return str_contains(strtolower($address), 'fail');
    }

    private function isSimulatedDriver(string $driver): bool
    {
        return in_array($driver, [ProviderDriver::SIMULATION, ProviderDriver::MANUAL], true);
    }
}
