<?php

namespace Database\Seeders;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\AppointmentRecurrenceSeries;
use App\Domains\Booking\Models\AppointmentServiceLine;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Booking\Models\WaitlistEntry;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BookingDemoSeeder extends Seeder
{
    public function run(
        Tenant $tenant,
        Location $location,
        Workspace $workspace,
        TeamMember $ownerMember,
    ): void {
        $cut = BookableService::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Cut & Blow Dry',
            'category' => 'hair',
            'description' => 'Consultation, cut, and finish blow-dry for a polished everyday look.',
            'duration_minutes' => 60,
            'base_price_cents' => 4500,
            'membership_price_cents' => 3800,
            'loyalty_price_cents' => 4050,
            'is_active' => true,
            'is_bookable_online' => true,
            'display_order' => 1,
        ]);

        $colour = BookableService::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Full Colour',
            'category' => 'colour',
            'description' => 'Full-head colour application with toner and finish. Deposit required.',
            'duration_minutes' => 120,
            'base_price_cents' => 9500,
            'membership_price_cents' => 8100,
            'loyalty_price_cents' => 8550,
            'deposit_required' => true,
            'deposit_amount_cents' => 3000,
            'min_lead_time_hours' => 24,
            'cancellation_window_hours' => 48,
            'is_active' => true,
            'is_bookable_online' => true,
            'display_order' => 2,
        ]);

        $client = Client::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', 'alex.taylor@example.com')
            ->first();

        if ($client === null) {
            return;
        }

        $nextMonday = Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0);

        $appointment = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'client_id' => $client->id,
            'team_member_id' => $ownerMember->id,
            'workspace_id' => $workspace->id,
            'starts_at' => $nextMonday,
            'ends_at' => $nextMonday->copy()->addMinutes(60),
            'status' => Appointment::STATUS_CONFIRMED,
            'booking_source' => Appointment::SOURCE_ADMIN,
            'client_notes' => 'Prefers afternoon if rescheduling',
            'internal_notes' => 'Demo appointment with workspace assigned',
            'created_by_team_member_id' => $ownerMember->id,
            'booking_reference' => 'NM-DEMO0001',
            'deposit_status' => Appointment::DEPOSIT_NOT_REQUIRED,
        ]);

        AppointmentServiceLine::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'appointment_id' => $appointment->id,
            'booking_service_id' => $cut->id,
            'service_name' => $cut->name,
            'duration_minutes' => $cut->duration_minutes,
            'price_cents' => $cut->base_price_cents,
            'sort_order' => 0,
        ]);

        $stylist = TeamMember::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('display_name', 'Sam Stylist')
            ->first();

        if ($stylist !== null) {
            $nextTuesday = Carbon::now()->next(Carbon::TUESDAY)->setTime(11, 0);

            $appt2 = Appointment::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $location->id,
                'client_id' => $client->id,
                'team_member_id' => $stylist->id,
                'workspace_id' => null,
                'starts_at' => $nextTuesday,
                'ends_at' => $nextTuesday->copy()->addMinutes(120),
                'status' => Appointment::STATUS_PENDING,
                'booking_source' => Appointment::SOURCE_ADMIN,
                'created_by_team_member_id' => $ownerMember->id,
                'booking_reference' => 'NM-DEMO0002',
                'deposit_status' => Appointment::DEPOSIT_PENDING,
                'deposit_required_cents' => 3000,
                'deposit_rule_snapshot' => [
                    'services' => [
                        [
                            'booking_service_id' => $colour->id,
                            'service_name' => $colour->name,
                            'deposit_amount_cents' => 3000,
                        ],
                    ],
                ],
            ]);

            AppointmentServiceLine::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'appointment_id' => $appt2->id,
                'booking_service_id' => $colour->id,
                'service_name' => $colour->name,
                'duration_minutes' => $colour->duration_minutes,
                'price_cents' => $colour->base_price_cents,
                'sort_order' => 0,
            ]);
        }

        $seriesStart = Carbon::now()->next(Carbon::MONDAY)->addWeeks(3)->setTime(9, 0);
        $series = AppointmentRecurrenceSeries::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'pattern' => AppointmentRecurrenceSeries::PATTERN_WEEKLY,
            'interval_weeks' => 1,
            'anchor_starts_at' => $seriesStart,
            'occurrence_count' => 4,
            'status' => AppointmentRecurrenceSeries::STATUS_ACTIVE,
            'client_id' => $client->id,
            'team_member_id' => $ownerMember->id,
            'location_id' => $location->id,
            'workspace_id' => $workspace->id,
            'service_template' => [['booking_service_id' => $cut->id, 'sort_order' => 0]],
            'created_by_team_member_id' => $ownerMember->id,
        ]);

        for ($i = 0; $i < 2; $i++) {
            $slot = $seriesStart->copy()->addWeeks($i);
            $recAppt = Appointment::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $location->id,
                'client_id' => $client->id,
                'team_member_id' => $ownerMember->id,
                'workspace_id' => $workspace->id,
                'starts_at' => $slot,
                'ends_at' => $slot->copy()->addMinutes(60),
                'status' => Appointment::STATUS_CONFIRMED,
                'booking_source' => Appointment::SOURCE_ADMIN,
                'recurrence_series_id' => $series->id,
                'occurrence_index' => $i,
                'booking_reference' => 'NM-REC'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            ]);

            AppointmentServiceLine::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'appointment_id' => $recAppt->id,
                'booking_service_id' => $cut->id,
                'service_name' => $cut->name,
                'duration_minutes' => $cut->duration_minutes,
                'price_cents' => $cut->base_price_cents,
                'sort_order' => 0,
            ]);
        }

        $waitlist = WaitlistEntry::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'location_id' => $location->id,
            'team_member_id' => $ownerMember->id,
            'preferred_starts_at' => Carbon::now()->next(Carbon::SATURDAY)->setTime(15, 0),
            'availability_notes' => 'Saturday afternoon preferred',
            'status' => WaitlistEntry::STATUS_WAITING,
            'notes' => 'Wants colour consultation slot',
            'created_by_team_member_id' => $ownerMember->id,
        ]);

        $waitlist->bookableServices()->attach($colour->id, [
            'service_name' => $colour->name,
            'sort_order' => 0,
        ]);

        $walkInClient = Client::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', 'jordan.lee@example.com')
            ->first();

        if ($walkInClient !== null) {
            $walkInAppt = Appointment::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $location->id,
                'client_id' => $walkInClient->id,
                'team_member_id' => null,
                'workspace_id' => null,
                'starts_at' => now(),
                'ends_at' => now()->addMinutes(60),
                'status' => Appointment::STATUS_PENDING,
                'booking_source' => Appointment::SOURCE_WALK_IN,
                'walk_in_stage' => Appointment::WALK_IN_WAITING,
                'arrived_at' => now(),
                'internal_notes' => 'Demo walk-in waiting for chair',
                'created_by_team_member_id' => $ownerMember->id,
                'booking_reference' => 'NM-WALKIN01',
            ]);

            AppointmentServiceLine::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'appointment_id' => $walkInAppt->id,
                'booking_service_id' => $cut->id,
                'service_name' => $cut->name,
                'duration_minutes' => $cut->duration_minutes,
                'price_cents' => $cut->base_price_cents,
                'sort_order' => 0,
            ]);
        }

        if ($stylist !== null) {
            $noShowClient = Client::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'first_name' => 'Morgan',
                'last_name' => 'Smith',
                'email' => 'morgan.smith.demo@example.com',
                'is_active' => true,
            ]);

            $pastSlot = Carbon::now()->subDays(2)->setTime(15, 0);
            $noShowAppt = Appointment::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $location->id,
                'client_id' => $noShowClient->id,
                'team_member_id' => $stylist->id,
                'workspace_id' => $workspace->id,
                'starts_at' => $pastSlot,
                'ends_at' => $pastSlot->copy()->addMinutes(60),
                'status' => Appointment::STATUS_NO_SHOW,
                'booking_source' => Appointment::SOURCE_ADMIN,
                'no_show_reason' => 'Did not arrive — demo no-show',
                'booking_reference' => 'NM-NOSHOW01',
            ]);

            AppointmentServiceLine::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'appointment_id' => $noShowAppt->id,
                'booking_service_id' => $cut->id,
                'service_name' => $cut->name,
                'duration_minutes' => $cut->duration_minutes,
                'price_cents' => $cut->base_price_cents,
                'sort_order' => 0,
            ]);
        }

        WaitlistEntry::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', WaitlistEntry::STATUS_WAITING)
            ->limit(1)
            ->update([
                'status' => WaitlistEntry::STATUS_CONTACTED,
                'contacted_at' => now()->subHours(2),
                'notes' => 'Called client — prefers next Saturday afternoon',
            ]);
    }
}
