<?php

namespace App\Console\Commands;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Notifications\Enums\NotificationPurpose;
use App\Domains\Notifications\Models\NotificationMessage;
use App\Domains\Notifications\Services\NotificationAutomationSettingService;
use App\Domains\Notifications\Services\NotificationTriggerService;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

class DispatchBookingRemindersCommand extends Command
{
    protected $signature = 'notifications:dispatch-booking-reminders
                            {--window=5 : Minutes either side of the reminder lead time to match}';

    protected $description = 'Dispatch operational booking reminders for appointments approaching the configured lead time';

    public function handle(
        TenantContext $tenantContext,
        NotificationAutomationSettingService $settings,
        NotificationTriggerService $triggers,
    ): int {
        $halfWindow = max(1, (int) $this->option('window'));
        $sent = 0;

        $tenants = Tenant::query()->get();

        foreach ($tenants as $tenant) {
            $tenantContext->set($tenant);

            try {
                $leadMinutes = $settings->bookingReminderLeadMinutes();
                $from = now()->addMinutes($leadMinutes - $halfWindow);
                $to = now()->addMinutes($leadMinutes + $halfWindow);

                $appointments = Appointment::query()
                    ->with(['client', 'teamMember', 'location', 'serviceLines'])
                    ->whereIn('status', [Appointment::STATUS_CONFIRMED, Appointment::STATUS_PENDING])
                    ->whereBetween('starts_at', [$from, $to])
                    ->get();

                foreach ($appointments as $appointment) {
                    $already = NotificationMessage::query()
                        ->where('appointment_id', $appointment->id)
                        ->where('purpose', NotificationPurpose::BOOKING_REMINDER)
                        ->exists();

                    if ($already) {
                        continue;
                    }

                    $message = $triggers->safe(
                        fn () => $triggers->sendBookingReminder($appointment)
                    );

                    if ($message !== null) {
                        $sent++;
                    }
                }
            } finally {
                $tenantContext->clear();
            }
        }

        $this->info("Dispatched {$sent} booking reminder(s).");

        return self::SUCCESS;
    }
}
