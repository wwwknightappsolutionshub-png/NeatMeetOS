<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\BookingChangeRequest;
use App\Domains\Booking\Models\StaffSosAlert;
use App\Domains\Notifications\Services\NotificationTriggerService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Commerce\Enums\DepositLifecycleState;
use App\Shared\Commerce\Models\CommerceDepositRecord;
use App\Shared\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingChangeRequestService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BookingScopeValidator $scope,
        private readonly BookingPolicyService $policy,
        private readonly AppointmentBookingService $appointments,
        private readonly AppointmentSchedulingValidator $schedulingValidator,
        private readonly StaffSosAlertService $staffSos,
        private readonly NotificationTriggerService $notificationTriggers,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return Collection<int, BookingChangeRequest>
     */
    public function listPending(): Collection
    {
        return BookingChangeRequest::query()
            ->with(['appointment.client', 'appointment.teamMember', 'appointment.location', 'appointment.serviceLines'])
            ->where('status', BookingChangeRequest::STATUS_PENDING)
            ->orderByDesc('created_at')
            ->get();
    }

    public function find(string $id): BookingChangeRequest
    {
        return BookingChangeRequest::query()
            ->with(['appointment.client', 'appointment.teamMember', 'appointment.location', 'appointment.serviceLines'])
            ->findOrFail($id);
    }

    public function findByIdAndToken(string $id, string $token): BookingChangeRequest
    {
        $request = BookingChangeRequest::query()
            ->with(['appointment.client', 'appointment.teamMember', 'appointment.location', 'appointment.serviceLines'])
            ->where('id', $id)
            ->where('action_token', $token)
            ->first();

        if ($request === null) {
            throw ValidationException::withMessages([
                'token' => ['Change request not found or link is invalid.'],
            ]);
        }

        return $request;
    }

    public function requestCustomerCancel(Appointment $appointment, ?string $reason = null): BookingChangeRequest
    {
        $this->scope->assertTenantModel($appointment);
        $this->assertCancellable($appointment);
        $this->assertNoPendingRequest($appointment);

        $settings = $this->policy->get();
        $free = $this->policy->isInsideFreeChangeWindow($appointment, $settings);
        $lateFeeApplies = ! $free;
        $lateFeeCents = $lateFeeApplies
            ? $this->policy->lateCancelFeeCents($appointment, $settings)
            : 0;

        $request = $this->createRequest([
            'appointment_id' => $appointment->id,
            'type' => BookingChangeRequest::TYPE_CANCEL,
            'initiated_by' => BookingChangeRequest::INITIATED_BY_CUSTOMER,
            'decline_allowed' => $lateFeeApplies,
            'late_fee_applies' => $lateFeeApplies,
            'late_fee_cents' => $lateFeeApplies ? $lateFeeCents : null,
            'reason' => $reason,
            'metadata' => [
                'minutes_until_start' => $this->policy->minutesUntilStart($appointment),
            ],
        ]);

        $alert = $this->staffSos->raiseForChangeRequest($request);
        $request->staff_sos_alert_id = $alert->id;
        $request->save();

        $this->notificationTriggers->safe(
            fn () => $this->notificationTriggers->sendBookingChangeRequestToTenant($request)
        );

        return $request->fresh([
            'appointment.client',
            'appointment.teamMember',
            'appointment.location',
            'appointment.serviceLines',
        ]) ?? $request;
    }

    /**
     * @param  array{starts_at: string, team_member_id?: string|null, workspace_id?: string|null, reason?: string|null}  $data
     */
    public function requestTenantPostpone(Appointment $appointment, array $data, ?string $teamMemberId = null): BookingChangeRequest
    {
        $this->scope->assertTenantModel($appointment);
        $this->assertCancellable($appointment);
        $this->assertNoPendingRequest($appointment);

        $settings = $this->policy->get();
        if (! $this->policy->isInsideFreeChangeWindow($appointment, $settings)) {
            $window = (int) $settings->free_change_window_minutes;
            throw ValidationException::withMessages([
                'starts_at' => ["Postponement requests require at least {$window} minutes before the appointment."],
            ]);
        }

        $startsAt = Carbon::parse($data['starts_at']);
        $this->policy->assertStartsAtMeetsAdvanceNotice($startsAt, $settings);

        $teamMemberIdResolved = $data['team_member_id'] ?? $appointment->team_member_id;
        $workspaceId = $data['workspace_id'] ?? $appointment->workspace_id;
        $duration = max(1, (int) $appointment->starts_at->diffInMinutes($appointment->ends_at));
        $endsAt = $startsAt->copy()->addMinutes($duration);

        $this->schedulingValidator->validate(
            $teamMemberIdResolved,
            $appointment->location_id,
            $workspaceId,
            $startsAt,
            $endsAt,
            $appointment->id,
        );

        $request = $this->createRequest([
            'appointment_id' => $appointment->id,
            'type' => BookingChangeRequest::TYPE_POSTPONE,
            'initiated_by' => BookingChangeRequest::INITIATED_BY_TENANT,
            'decline_allowed' => true,
            'late_fee_applies' => false,
            'proposed_starts_at' => $startsAt,
            'proposed_ends_at' => $endsAt,
            'proposed_team_member_id' => $teamMemberIdResolved,
            'proposed_workspace_id' => $workspaceId,
            'reason' => $data['reason'] ?? null,
            'resolved_by_team_member_id' => $teamMemberId,
            'metadata' => [
                'original_starts_at' => $appointment->starts_at?->toIso8601String(),
            ],
        ]);

        $this->notificationTriggers->safe(
            fn () => $this->notificationTriggers->sendBookingChangeRequestToCustomer($request)
        );

        return $request->fresh([
            'appointment.client',
            'appointment.teamMember',
            'appointment.location',
            'appointment.serviceLines',
        ]) ?? $request;
    }

    public function accept(
        BookingChangeRequest $request,
        string $resolvedVia,
        ?string $teamMemberId = null,
    ): BookingChangeRequest {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => ['This change request has already been resolved.'],
            ]);
        }

        return DB::transaction(function () use ($request, $resolvedVia, $teamMemberId) {
            $appointment = $this->appointments->find($request->appointment_id);

            if ($request->type === BookingChangeRequest::TYPE_CANCEL) {
                if ($request->late_fee_applies) {
                    $this->applyLateCancelFee($appointment, (int) ($request->late_fee_cents ?? 0));
                }
                $this->appointments->cancel(
                    $appointment,
                    $request->reason ?? 'Cancelled via booking change request',
                );
            } else {
                $this->appointments->update($appointment, [
                    'starts_at' => $request->proposed_starts_at?->toIso8601String(),
                    'team_member_id' => $request->proposed_team_member_id ?? $appointment->team_member_id,
                    'workspace_id' => $request->proposed_workspace_id ?? $appointment->workspace_id,
                ], [
                    'created_by_team_member_id' => $teamMemberId,
                ]);
            }

            $status = $resolvedVia === BookingChangeRequest::RESOLVED_VIA_AUTO
                ? BookingChangeRequest::STATUS_AUTO_ACCEPTED
                : BookingChangeRequest::STATUS_ACCEPTED;

            $request->status = $status;
            $request->resolved_at = now();
            $request->resolved_via = $resolvedVia;
            if ($teamMemberId !== null) {
                $request->resolved_by_team_member_id = $teamMemberId;
            }
            $request->save();

            $this->resolveSosAlert($request);
            $this->auditLogger->log('booking_change_request.accepted', $request, null, [
                'resolved_via' => $resolvedVia,
                'type' => $request->type,
            ]);

            return $request->fresh([
                'appointment.client',
                'appointment.teamMember',
                'appointment.location',
                'appointment.serviceLines',
            ]) ?? $request;
        });
    }

    public function decline(
        BookingChangeRequest $request,
        string $resolvedVia,
        ?string $teamMemberId = null,
    ): BookingChangeRequest {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => ['This change request has already been resolved.'],
            ]);
        }

        if (! $request->decline_allowed) {
            throw ValidationException::withMessages([
                'status' => ['This request cannot be declined within the free cancellation window.'],
            ]);
        }

        $request->status = BookingChangeRequest::STATUS_DECLINED;
        $request->resolved_at = now();
        $request->resolved_via = $resolvedVia;
        if ($teamMemberId !== null) {
            $request->resolved_by_team_member_id = $teamMemberId;
        }
        $request->save();

        $this->resolveSosAlert($request);
        $this->auditLogger->log('booking_change_request.declined', $request, null, [
            'resolved_via' => $resolvedVia,
            'type' => $request->type,
        ]);

        $this->notificationTriggers->safe(
            fn () => $this->notificationTriggers->sendBookingChangeRequestDeclined($request)
        );

        return $request->fresh([
            'appointment.client',
            'appointment.teamMember',
            'appointment.location',
            'appointment.serviceLines',
        ]) ?? $request;
    }

    public function sendReminder(BookingChangeRequest $request): void
    {
        if (! $request->isPending()) {
            return;
        }

        $request->reminder_count = (int) $request->reminder_count + 1;
        $request->last_reminded_at = now();
        $request->save();

        $this->notificationTriggers->safe(
            fn () => $this->notificationTriggers->sendBookingChangeRequestReminder($request)
        );
    }

    public function autoAcceptIfDue(BookingChangeRequest $request): ?BookingChangeRequest
    {
        if (! $request->isPending()) {
            return null;
        }

        $settings = $this->policy->get();
        $max = max(1, (int) $settings->approval_reminder_max_count);

        if ((int) $request->reminder_count < $max) {
            return null;
        }

        return $this->accept($request, BookingChangeRequest::RESOLVED_VIA_AUTO);
    }

    /**
     * @return array{manage_path: string, manage_url: string, accept_url: string, decline_url: string}
     */
    public function actionLinks(BookingChangeRequest $request): array
    {
        $tenant = $this->tenantContext->get();
        $slug = $tenant?->slug ?? '';
        $base = rtrim((string) config('app.frontend_url'), '/');
        $path = '/book/'.$slug.'/change-request?id='.urlencode($request->id)
            .'&token='.urlencode($request->action_token);

        return [
            'manage_path' => $path,
            'manage_url' => $base.$path,
            'accept_url' => $base.$path.'&action=accept',
            'decline_url' => $base.$path.'&action=decline',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createRequest(array $attributes): BookingChangeRequest
    {
        return BookingChangeRequest::query()->create([
            'tenant_id' => $this->tenantContext->id(),
            'status' => BookingChangeRequest::STATUS_PENDING,
            'action_token' => $this->generateActionToken(),
            'reminder_count' => 0,
            'last_reminded_at' => now(),
            ...$attributes,
        ]);
    }

    private function generateActionToken(): string
    {
        do {
            $token = Str::lower(Str::random(48));
        } while (BookingChangeRequest::withoutGlobalScopes()->where('action_token', $token)->exists());

        return $token;
    }

    private function assertCancellable(Appointment $appointment): void
    {
        if (in_array($appointment->status, [
            Appointment::STATUS_CANCELLED,
            Appointment::STATUS_COMPLETED,
            Appointment::STATUS_NO_SHOW,
        ], true)) {
            throw ValidationException::withMessages([
                'appointment' => ['This booking can no longer be changed.'],
            ]);
        }

        if ($appointment->starts_at !== null && $appointment->starts_at->lte(now())) {
            throw ValidationException::withMessages([
                'appointment' => ['Past appointments cannot be changed online.'],
            ]);
        }
    }

    private function assertNoPendingRequest(Appointment $appointment): void
    {
        $exists = BookingChangeRequest::query()
            ->where('appointment_id', $appointment->id)
            ->where('status', BookingChangeRequest::STATUS_PENDING)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'appointment' => ['A pending cancel/postpone request already exists for this booking.'],
            ]);
        }
    }

    private function applyLateCancelFee(Appointment $appointment, int $feeCents): void
    {
        if ($feeCents <= 0) {
            return;
        }

        $snapshot = is_array($appointment->deposit_rule_snapshot)
            ? $appointment->deposit_rule_snapshot
            : [];
        $snapshot['late_cancel_fee_cents'] = $feeCents;
        $snapshot['late_cancel_fee_applied_at'] = now()->toIso8601String();
        $appointment->deposit_rule_snapshot = $snapshot;
        $appointment->save();

        $record = CommerceDepositRecord::query()
            ->where('appointment_id', $appointment->id)
            ->orderByDesc('created_at')
            ->first();

        if ($record === null) {
            return;
        }

        $rule = is_array($record->rule_snapshot) ? $record->rule_snapshot : [];
        $rule['late_cancel_fee_cents'] = $feeCents;
        $rule['late_cancel_fee_applied_at'] = now()->toIso8601String();

        $updates = ['rule_snapshot' => $rule];
        if ($record->lifecycle_state === DepositLifecycleState::COLLECTED
            && (int) ($record->collected_cents ?? 0) > 0
            && $feeCents >= (int) $record->collected_cents
        ) {
            $updates['lifecycle_state'] = DepositLifecycleState::FORFEITED;
        } elseif ($record->lifecycle_state === DepositLifecycleState::COLLECTED) {
            $updates['manual_notes'] = trim(
                ((string) ($record->manual_notes ?? ''))."\n"
                ."Late cancel fee {$feeCents} cents applied (partial deposit forfeit)."
            );
        }

        $record->update($updates);

        $this->auditLogger->log('booking.late_cancel_fee_applied', $appointment, null, [
            'late_fee_cents' => $feeCents,
            'commerce_deposit_record_id' => $record->id,
        ]);
    }

    private function resolveSosAlert(BookingChangeRequest $request): void
    {
        if ($request->staff_sos_alert_id === null) {
            return;
        }

        $alert = StaffSosAlert::query()->find($request->staff_sos_alert_id);
        if ($alert !== null && $alert->isActive()) {
            $alert->status = StaffSosAlert::STATUS_RESOLVED;
            $alert->save();
        }
    }
}
