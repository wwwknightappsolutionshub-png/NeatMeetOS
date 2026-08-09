<?php

namespace App\Console\Commands;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Services\StaffSosAlertService;
use App\Domains\Identity\Models\Tenant;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

class DispatchApproachingAppointmentSosCommand extends Command
{
    protected $signature = 'booking:dispatch-approaching-sos
                            {--lead=20 : Minutes before start to raise SOS}
                            {--window=2 : Match window either side of lead time}';

    protected $description = 'Raise staff SOS alerts for appointments approaching within the lead window';

    public function handle(
        TenantContext $tenantContext,
        StaffSosAlertService $sos,
    ): int {
        $lead = max(5, (int) $this->option('lead'));
        $half = max(1, (int) $this->option('window'));
        $raised = 0;

        foreach (Tenant::query()->orderBy('id')->get() as $tenant) {
            $tenantContext->set($tenant);

            try {
                $from = now()->addMinutes($lead - $half);
                $to = now()->addMinutes($lead + $half);

                $appointments = Appointment::query()
                    ->with(['client', 'teamMember', 'location', 'serviceLines'])
                    ->whereIn('status', [Appointment::STATUS_CONFIRMED, Appointment::STATUS_PENDING])
                    ->whereBetween('starts_at', [$from, $to])
                    ->get();

                foreach ($appointments as $appointment) {
                    $alert = $sos->raiseApproaching($appointment);
                    if ($alert !== null) {
                        $raised++;
                    }
                }
            } finally {
                $tenantContext->clear();
            }
        }

        $this->info("Raised {$raised} approaching SOS alert(s).");

        return self::SUCCESS;
    }
}
