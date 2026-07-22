<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationDirection;
use App\Domains\Notifications\Enums\NotificationMessageStatus;
use App\Domains\Notifications\Enums\NotificationPurpose;
use App\Domains\Notifications\Enums\NotificationSourceType;
use App\Domains\Notifications\Models\NotificationMessage;
use App\Jobs\DispatchNotificationMessageJob;
use App\Shared\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NotificationMessageService
{
    public function __construct(
        private readonly NotificationScopeValidator $scope,
        private readonly NotificationPreferenceService $preferences,
        private readonly NotificationDispatchSimulationService $dispatch,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = NotificationMessage::query()
            ->with(['client', 'attempts', 'createdBy'])
            ->orderByDesc('created_at');

        if (! empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }
        if (! empty($filters['purpose'])) {
            $query->where('purpose', $filters['purpose']);
        }
        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }
        if (! empty($filters['appointment_id'])) {
            $query->where('appointment_id', $filters['appointment_id']);
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }
        if (filter_var($filters['desk_only'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('channel', NotificationChannel::INTERNAL_NOTE)
                ->where(function ($q) {
                    $q->where('metadata->desk_chat', true)
                        ->orWhere('purpose', NotificationPurpose::INTERNAL_NOTE_DELIVERY);
                });
        }

        return $query->paginate($perPage);
    }

    public function find(string $id): NotificationMessage
    {
        return $this->scope->findMessage($id)->load(['client', 'attempts', 'template', 'createdBy']);
    }

    /**
     * Create an operational notification record and (optionally) dispatch it via simulation.
     *
     * This is the canonical creation path used by both manual admin messages and
     * module-generated system communications through NotificationTriggerService.
     *
     * @param  array<string, mixed>  $data
     */
    public function createSystemMessage(array $data, bool $dispatch = true): NotificationMessage
    {
        $tenantId = $this->scope->tenantId();

        $channel = $data['channel'] ?? null;
        $purpose = $data['purpose'] ?? null;
        $sourceType = $data['source_type'] ?? NotificationSourceType::SYSTEM;

        $this->validateChannel($channel);
        $this->validatePurpose($purpose);
        $this->validateSourceType($sourceType);

        $client = null;
        if (! empty($data['client_id'])) {
            $client = $this->scope->findClient($data['client_id']);
        }

        $recipientAddress = $data['recipient_address'] ?? ($client ? $this->recipientAddressForClient($client, $channel) : null);
        $recipientName = $data['recipient_name'] ?? $client?->resolvedDisplayName();

        // Eligibility: operational preference projection gates delivery for known clients.
        $suppressedReason = null;
        if ($client !== null && $channel !== NotificationChannel::INTERNAL_NOTE) {
            $preferenceCategory = NotificationPurpose::preferenceCategory($purpose);
            if (! $this->preferences->allowsDelivery($client, $channel, $preferenceCategory)) {
                $suppressedReason = 'Blocked by client notification preference/consent projection.';
            }
        }

        return DB::transaction(function () use ($tenantId, $data, $channel, $purpose, $sourceType, $client, $recipientAddress, $recipientName, $suppressedReason, $dispatch) {
            $message = NotificationMessage::query()->create([
                'tenant_id' => $tenantId,
                'client_id' => $client?->id,
                'appointment_id' => $data['appointment_id'] ?? null,
                'checkout_id' => $data['checkout_id'] ?? null,
                'payment_transaction_id' => $data['payment_transaction_id'] ?? null,
                'client_membership_id' => $data['client_membership_id'] ?? null,
                'marketing_workflow_execution_id' => $data['marketing_workflow_execution_id'] ?? null,
                'notification_template_id' => $data['notification_template_id'] ?? null,
                'source_type' => $sourceType,
                'purpose' => $purpose,
                'channel' => $channel,
                'direction' => $data['direction'] ?? NotificationDirection::OUTBOUND,
                'status' => $suppressedReason !== null ? NotificationMessageStatus::SUPPRESSED : NotificationMessageStatus::QUEUED,
                'recipient_name' => $recipientName,
                'recipient_address' => $recipientAddress,
                'subject' => $data['subject'] ?? null,
                'body_text' => $data['body_text'] ?? null,
                'body_html' => $data['body_html'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'queued_at' => now(),
                'failure_reason' => $suppressedReason,
                'created_by_team_member_id' => $data['created_by_team_member_id'] ?? null,
            ]);

            $this->auditLogger->log('notification_message.created', $message, null, [
                'source_type' => $message->source_type,
                'purpose' => $message->purpose,
                'channel' => $message->channel,
                'status' => $message->status,
            ]);

            if ($dispatch) {
                // Sync queues (tests/local without worker): dispatch inline so API returns final status.
                // Async queues: job after commit for worker processing.
                if (config('queue.default') === 'sync') {
                    return $this->dispatch->dispatch($message)['message']
                        ->loadMissing(['client', 'attempts', 'createdBy']);
                }

                $messageId = $message->id;
                DB::afterCommit(function () use ($tenantId, $messageId) {
                    DispatchNotificationMessageJob::dispatch($tenantId, $messageId);
                });
            }

            return $message->fresh(['client', 'attempts', 'createdBy']);
        });
    }

    /**
     * Manual admin-generated client communication.
     *
     * @param  array<string, mixed>  $data
     */
    public function createManual(array $data): NotificationMessage
    {
        if (empty($data['client_id'])) {
            throw ValidationException::withMessages(['client_id' => ['A client is required for a manual message.']]);
        }

        $data['source_type'] = NotificationSourceType::MANUAL;
        $data['purpose'] = $data['purpose'] ?? NotificationPurpose::MANUAL_CLIENT_MESSAGE;

        return $this->createSystemMessage($data, dispatch: true);
    }

    /**
     * Staff desk note — tenant-visible internal feed for support/ops chat.
     *
     * @param  array<string, mixed>  $data
     */
    public function createDeskNote(array $data): NotificationMessage
    {
        $body = trim((string) ($data['body_text'] ?? ''));
        if ($body === '') {
            throw ValidationException::withMessages([
                'body_text' => ['A desk message is required.'],
            ]);
        }

        return $this->createSystemMessage([
            'client_id' => null,
            'source_type' => NotificationSourceType::MANUAL,
            'purpose' => NotificationPurpose::INTERNAL_NOTE_DELIVERY,
            'channel' => NotificationChannel::INTERNAL_NOTE,
            'direction' => NotificationDirection::OUTBOUND,
            'recipient_name' => 'Desk',
            'recipient_address' => 'desk@internal',
            'subject' => $data['subject'] ?? 'Desk note',
            'body_text' => $body,
            'metadata' => array_merge(
                ['desk_chat' => true],
                is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
            ),
            'created_by_team_member_id' => $data['created_by_team_member_id'] ?? null,
        ], dispatch: true)->loadMissing(['createdBy', 'client', 'attempts']);
    }

    public function cancel(NotificationMessage $message): NotificationMessage
    {
        $this->scope->assertTenantModel($message);

        if (in_array($message->status, NotificationMessageStatus::terminal(), true)) {
            throw ValidationException::withMessages([
                'status' => ['Only pending or queued messages can be cancelled.'],
            ]);
        }

        return DB::transaction(function () use ($message) {
            $old = ['status' => $message->status];
            $message->status = NotificationMessageStatus::CANCELLED;
            $message->cancelled_at = now();
            $message->save();

            $this->auditLogger->log('notification_message.cancelled', $message, $old, ['status' => NotificationMessageStatus::CANCELLED]);

            return $message->fresh(['client', 'attempts', 'createdBy']);
        });
    }

    /**
     * Admin correction / simulation utility to mark a sent message as delivered.
     */
    public function markDelivered(NotificationMessage $message): NotificationMessage
    {
        $this->scope->assertTenantModel($message);

        if (! in_array($message->status, [NotificationMessageStatus::SENT, NotificationMessageStatus::PROCESSING], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only sent messages can be marked delivered.'],
            ]);
        }

        return DB::transaction(function () use ($message) {
            $old = ['status' => $message->status];
            $message->status = NotificationMessageStatus::DELIVERED;
            $message->delivered_at = now();
            $message->save();

            $this->auditLogger->log('notification_message.delivered', $message, $old, ['status' => NotificationMessageStatus::DELIVERED]);

            return $message->fresh(['client', 'attempts', 'createdBy']);
        });
    }

    public function recipientAddressForClient(Client $client, string $channel): ?string
    {
        if ($channel === NotificationChannel::EMAIL) {
            $email = trim((string) ($client->email ?? ''));

            return $email !== '' ? $email : null;
        }

        if (in_array($channel, [NotificationChannel::SMS, NotificationChannel::WHATSAPP], true)) {
            $phone = trim((string) ($client->phone ?? ''));

            return $phone !== '' ? $phone : null;
        }

        if ($channel === NotificationChannel::IN_APP) {
            return 'client:'.$client->id;
        }

        return null;
    }

    private function validateChannel(?string $channel): void
    {
        if ($channel === null || ! in_array($channel, NotificationChannel::all(), true)) {
            throw ValidationException::withMessages(['channel' => ['Invalid notification channel.']]);
        }
    }

    private function validatePurpose(?string $purpose): void
    {
        if ($purpose === null || ! in_array($purpose, NotificationPurpose::all(), true)) {
            throw ValidationException::withMessages(['purpose' => ['Invalid notification purpose.']]);
        }
    }

    private function validateSourceType(?string $sourceType): void
    {
        if ($sourceType === null || ! in_array($sourceType, NotificationSourceType::all(), true)) {
            throw ValidationException::withMessages(['source_type' => ['Invalid notification source type.']]);
        }
    }
}
