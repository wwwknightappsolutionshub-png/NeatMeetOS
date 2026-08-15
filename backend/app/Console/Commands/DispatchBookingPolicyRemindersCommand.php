<?php

namespace App\Console\Commands;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\BookingChangeRequest;
use App\Domains\Booking\Services\BookingChangeRequestService;
use App\Domains\Booking\Services\BookingPolicyService;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Notifications\Enums\NotificationPurpose;
use App\Domains\Notifications\Models\NotificationMessage;
use App\Domains\Notifications\Services\NotificationTriggerService;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

class DispatchBookingPolicyRemindersCommand extends Command
{
    protected $signature = 'booking:dispatch-policy-reminders
                            {--window=1 : Minutes either side of free-window reminder match}';

    protected $description = 'Send free-cancel-window reminders and change-request approval reminders / auto-accept';

    public function handle(
        TenantContext $tenantContext,
        BookingPolicyService $policy,
        BookingChangeRequestService $changeRequests,
        NotificationTriggerService $triggers,
    ): int {
        $halfWindow = max(1, (int) $this->option('window'));
        $freeWindowSent = 0;
        $approvalReminders = 0;
        $autoAccepted = 0;

        foreach (Tenant::query()->get() as $tenant) {
            $tenantContext->set($tenant);

            try {
                $settings = $policy->get($tenant);
                $offset = (int) $settings->free_change_window_minutes
                    + (int) $settings->free_window_reminder_lead_minutes;
                $from = now()->addMinutes($offset - $halfWindow);
                $to = now()->addMinutes($offset + $halfWindow);

                $appointments = Appointment::query()
                    ->with(['client'])
                    ->whereIn('status', [Appointment::STATUS_CONFIRMED, Appointment::STATUS_PENDING])
                    ->whereBetween('starts_at', [$from, $to])
                    ->get();

                foreach ($appointments as $appointment) {
                    $already = NotificationMessage::query()
                        ->where('appointment_id', $appointment->id)
                        ->where('purpose', NotificationPurpose::BOOKING_FREE_WINDOW_REMINDER)
                        ->exists();

                    if ($already) {
                        continue;
                    }

                    $message = $triggers->safe(
                        fn () => $triggers->sendBookingFreeWindowReminder($appointment)
                    );
                    if ($message !== null) {
                        $freeWindowSent++;
                    }
                }

                $interval = max(1, (int) $settings->approval_reminder_interval_minutes);
                $pending = BookingChangeRequest::query()
                    ->where('status', BookingChangeRequest::STATUS_PENDING)
                    ->get();

                foreach ($pending as $request) {
                    $last = $request->last_reminded_at ?? $request->created_at;
                    if ($last !== null && $last->gt(now()->subMinutes($interval))) {
                        continue;
                    }

                    $max = max(1, (int) $settings->approval_reminder_max_count);
                    if ((int) $request->reminder_count >= $max) {
                        $accepted = $changeRequests->autoAcceptIfDue($request);
                        if ($accepted !== null) {
                            $autoAccepted++;
                        }
                        continue;
                    }

                    $changeRequests->sendReminder($request);
                    $approvalReminders++;

                    $request->refresh();
                    if ((int) $request->reminder_count >= $max) {
                        $accepted = $changeRequests->autoAcceptIfDue($request);
                        if ($accepted !== null) {
                            $autoAccepted++;
                        }
                    }
                }
            } finally {
                $tenantContext->clear();
            }
        }

        $this->info(
            "Free-window reminders: {$freeWindowSent}; approval reminders: {$approvalReminders}; auto-accepted: {$autoAccepted}."
        );

        return self::SUCCESS;
    }
}
