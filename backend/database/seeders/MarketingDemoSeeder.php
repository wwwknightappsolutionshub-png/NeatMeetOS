<?php

namespace Database\Seeders;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\AppointmentServiceLine;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientConsentRecord;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Marketing\Enums\MarketingCampaignStatus;
use App\Domains\Marketing\Enums\MarketingCampaignType;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingMessageStatus;
use App\Domains\Marketing\Enums\MarketingRunSource;
use App\Domains\Marketing\Enums\MarketingSuppressionReason;
use App\Domains\Marketing\Enums\MarketingSuppressionSource;
use App\Domains\Marketing\Enums\MarketingTriggerType;
use App\Domains\Marketing\Enums\MarketingWorkflowStatus;
use App\Domains\Marketing\Enums\MarketingWorkflowTrigger;
use App\Domains\Marketing\Models\MarketingMessage;
use App\Domains\Marketing\Services\BookingReminderAutomationService;
use App\Domains\Marketing\Services\MarketingAutomationSettingService;
use App\Domains\Marketing\Services\MarketingAutomationWorkflowService;
use App\Domains\Marketing\Services\MarketingCampaignService;
use App\Domains\Marketing\Services\MarketingDeliveryService;
use App\Domains\Marketing\Services\MarketingDispatchSimulationService;
use App\Domains\Marketing\Services\MarketingStarterTemplateService;
use App\Domains\Marketing\Services\MarketingSuppressionService;
use App\Domains\Marketing\Services\MarketingTemplateService;
use App\Domains\Marketing\Services\MarketingWorkflowExecutionService;
use App\Domains\Marketing\Services\ReviewRequestAutomationService;
use App\Domains\Marketing\Services\WinBackAutomationService;
use App\Shared\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeds a realistic Module 10A (Marketing Automation) demo footprint on top of
 * the CRM + Booking demo data.
 *
 * Everything is created through the marketing domain services so the seeded
 * state mirrors production behaviour: automation settings, templates, active
 * automation campaigns, generated runs (with eligible + consent-suppressed
 * recipients) and simulated dispatch that yields real sent messages.
 */
class MarketingDemoSeeder extends Seeder
{
    public function run(Tenant $tenant, Location $location, TeamMember $ownerMember): void
    {
        app(TenantContext::class)->set($tenant);

        // Reuse the CRM demo clients seeded earlier in the bootstrap chain.
        $alex = Client::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', 'alex.taylor@example.com')
            ->first();

        if ($alex === null) {
            return;
        }

        // 1. Automation settings via the service.
        app(MarketingAutomationSettingService::class)->update([
            'booking_reminder_hours_before' => 24,
            'review_request_delay_hours' => 24,
            'rebooking_window_days' => 42,
            'win_back_inactivity_days' => 90,
            'review_request_enabled' => true,
        ]);

        // 2. Editable sample templates (rich HTML emails + SMS) via starter catalog.
        $starters = app(MarketingStarterTemplateService::class);
        $starters->installSamples();

        $bookingReminderTemplate = $starters->findByName('Sample — Booking reminder');
        $reviewRequestTemplate = $starters->findByName('Sample — Review request (SMS)');
        $winBackTemplate = $starters->findByName('Sample — Win-back');
        $rebookingTemplate = $starters->findByName('Sample — Rebooking nudge (SMS)');

        if ($bookingReminderTemplate === null || $reviewRequestTemplate === null
            || $winBackTemplate === null || $rebookingTemplate === null) {
            return;
        }

        // 3. Active automation campaigns linked to the templates.
        $campaignService = app(MarketingCampaignService::class);

        $campaignService->create([
            'name' => '24h Appointment Reminder',
            'campaign_type' => MarketingCampaignType::AUTOMATION,
            'trigger_type' => MarketingTriggerType::BOOKING_REMINDER,
            'channel' => MarketingChannel::EMAIL,
            'status' => MarketingCampaignStatus::ACTIVE,
            'template_id' => $bookingReminderTemplate->id,
            'created_by_team_member_id' => $ownerMember->id,
            'notes' => 'Sends an email reminder before each upcoming appointment.',
        ]);

        $campaignService->create([
            'name' => 'Review Request Follow-up',
            'campaign_type' => MarketingCampaignType::AUTOMATION,
            'trigger_type' => MarketingTriggerType::REVIEW_REQUEST,
            'channel' => MarketingChannel::SMS,
            'status' => MarketingCampaignStatus::ACTIVE,
            'template_id' => $reviewRequestTemplate->id,
            'created_by_team_member_id' => $ownerMember->id,
            'notes' => 'Requests a review by SMS after a completed appointment.',
        ]);

        $campaignService->create([
            'name' => 'We Miss You',
            'campaign_type' => MarketingCampaignType::AUTOMATION,
            'trigger_type' => MarketingTriggerType::WIN_BACK,
            'channel' => MarketingChannel::EMAIL,
            'status' => MarketingCampaignStatus::ACTIVE,
            'template_id' => $winBackTemplate->id,
            'created_by_team_member_id' => $ownerMember->id,
            'notes' => 'Re-engages clients who have not visited for 90+ days.',
        ]);

        // 4. Demo appointment history so the automations have real candidates.
        $service = BookableService::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Cut & Blow Dry')
            ->first();

        // 4a. Near-future confirmed appointment for Alex (email consent granted)
        // so the booking-reminder automation has an eligible recipient.
        $reminderSlot = Carbon::now()->addHours(6);
        $reminderAppointment = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'client_id' => $alex->id,
            'team_member_id' => $ownerMember->id,
            'starts_at' => $reminderSlot,
            'ends_at' => $reminderSlot->copy()->addMinutes(60),
            'status' => Appointment::STATUS_CONFIRMED,
            'booking_source' => Appointment::SOURCE_ADMIN,
            'created_by_team_member_id' => $ownerMember->id,
            'booking_reference' => 'NM-MKT-REM1',
            'deposit_status' => Appointment::DEPOSIT_NOT_REQUIRED,
        ]);

        if ($service !== null) {
            $this->addServiceLine($tenant, $reminderAppointment, $service);
        }

        // 4b. Completed appointment in the recent past for Alex (review request +
        // general visit history) — created only if none exists yet.
        $hasCompleted = Appointment::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('client_id', $alex->id)
            ->where('status', Appointment::STATUS_COMPLETED)
            ->exists();

        if (! $hasCompleted) {
            $completedEnd = Carbon::now()->subHours(24)->subMinutes(30);
            $completedAppointment = Appointment::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $location->id,
                'client_id' => $alex->id,
                'team_member_id' => $ownerMember->id,
                'starts_at' => $completedEnd->copy()->subMinutes(60),
                'ends_at' => $completedEnd,
                'status' => Appointment::STATUS_COMPLETED,
                'booking_source' => Appointment::SOURCE_ADMIN,
                'created_by_team_member_id' => $ownerMember->id,
                'booking_reference' => 'NM-MKT-CMP1',
                'deposit_status' => Appointment::DEPOSIT_NOT_REQUIRED,
            ]);

            if ($service !== null) {
                $this->addServiceLine($tenant, $completedAppointment, $service);
            }
        }

        // 4c. A lapsed client (email consent, no future booking, last visit 120
        // days ago) so the win-back automation has an eligible recipient.
        $lapsed = Client::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'email' => 'robin.fox@example.com'],
            [
                'first_name' => 'Robin',
                'last_name' => 'Fox',
                'phone' => '+447700900789',
                'primary_location_id' => $location->id,
                'is_active' => true,
            ],
        );

        ClientConsentRecord::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $lapsed->id,
            'consent_type' => ClientConsentRecord::TYPE_MARKETING_EMAIL,
            'granted' => true,
            'source' => ClientConsentRecord::SOURCE_IN_PERSON,
            'actor_user_id' => $ownerMember->user_id,
            'recorded_at' => now()->subMonths(6),
        ]);

        $lapsedEnd = Carbon::now()->subDays(120);
        $lapsedAppointment = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'client_id' => $lapsed->id,
            'team_member_id' => $ownerMember->id,
            'starts_at' => $lapsedEnd->copy()->subMinutes(60),
            'ends_at' => $lapsedEnd,
            'status' => Appointment::STATUS_COMPLETED,
            'booking_source' => Appointment::SOURCE_ADMIN,
            'created_by_team_member_id' => $ownerMember->id,
            'booking_reference' => 'NM-MKT-WB01',
            'deposit_status' => Appointment::DEPOSIT_NOT_REQUIRED,
        ]);

        if ($service !== null) {
            $this->addServiceLine($tenant, $lapsedAppointment, $service);
        }

        // 5. Generate automation runs via the domain services.
        //    - Booking reminder: Alex is eligible (email consent) -> pending message.
        //    - Review request (SMS): Alex has no SMS consent -> skipped message.
        //    - Win back: Robin is eligible (email consent, lapsed) -> pending message.
        $options = [
            'run_source' => MarketingRunSource::SCHEDULER,
            'created_by_team_member_id' => $ownerMember->id,
        ];

        $bookingRuns = app(BookingReminderAutomationService::class)->generate($options);
        app(ReviewRequestAutomationService::class)->generate($options);
        $winBackRuns = app(WinBackAutomationService::class)->generate($options);

        // 6. Simulate dispatch on the runs that produced pending messages so the
        //    demo has real sent traffic. Skipped messages stay skipped.
        $dispatch = app(MarketingDispatchSimulationService::class);

        foreach (array_merge($bookingRuns, $winBackRuns) as $run) {
            $dispatch->simulateRun($run->id);
        }

        $this->seedModule10B($tenant, $location, $ownerMember, $alex, $lapsed ?? null);
    }

    /**
     * Module 10B demo: workflows, executions, suppressions, operational message states.
     */
    private function seedModule10B(Tenant $tenant, Location $location, TeamMember $ownerMember, Client $alex, ?Client $lapsed): void
    {
        $workflowService = app(MarketingAutomationWorkflowService::class);
        $starters = app(MarketingStarterTemplateService::class);
        $suppressionService = app(MarketingSuppressionService::class);
        $executionService = app(MarketingWorkflowExecutionService::class);
        $deliveryService = app(MarketingDeliveryService::class);

        $birthdayTemplate = $starters->findByName('Sample — Birthday greeting');
        $welcomeTemplate = $starters->findByName('Sample — New client welcome');

        $templateService = app(MarketingTemplateService::class);
        $noShowTemplate = $templateService->create([
            'name' => 'No-show Follow-up — SMS',
            'category' => MarketingWorkflowTrigger::APPOINTMENT_NO_SHOW,
            'channel' => MarketingChannel::SMS,
            'body_text' => 'Hi {{client.first_name}}, we missed you at {{business.name}}. Rebook: {{booking.link}}',
        ]);

        if ($birthdayTemplate === null || $welcomeTemplate === null) {
            return;
        }

        $birthdayWorkflow = $workflowService->create([
            'name' => 'Birthday Email',
            'trigger_type' => MarketingWorkflowTrigger::BIRTHDAY,
            'channel' => MarketingChannel::EMAIL,
            'status' => MarketingWorkflowStatus::ACTIVE,
            'template_id' => $birthdayTemplate->id,
            'created_by_team_member_id' => $ownerMember->id,
        ]);

        $noShowWorkflow = $workflowService->create([
            'name' => 'No-show Follow-up',
            'trigger_type' => MarketingWorkflowTrigger::APPOINTMENT_NO_SHOW,
            'channel' => MarketingChannel::SMS,
            'status' => MarketingWorkflowStatus::ACTIVE,
            'template_id' => $noShowTemplate->id,
            'created_by_team_member_id' => $ownerMember->id,
        ]);

        $welcomeWorkflow = $workflowService->create([
            'name' => 'New Client Welcome',
            'trigger_type' => MarketingWorkflowTrigger::CLIENT_CREATED,
            'channel' => MarketingChannel::EMAIL,
            'status' => MarketingWorkflowStatus::ACTIVE,
            'template_id' => $welcomeTemplate->id,
            'created_by_team_member_id' => $ownerMember->id,
        ]);

        // Completed execution via test-run.
        $alex->update(['date_of_birth' => now()->format('Y-m-d')]);
        $completed = $executionService->createExecution(
            $welcomeWorkflow,
            $alex,
            MarketingWorkflowTrigger::CLIENT_CREATED,
            Client::class,
            $alex->id,
            ['demo' => 'completed'],
            $ownerMember->id,
            true,
        );

        // Queued execution (future scheduled).
        if ($lapsed !== null) {
            $welcomeWorkflow->delay_minutes = 60;
            $welcomeWorkflow->save();
            $executionService->createExecution(
                $welcomeWorkflow,
                $lapsed,
                MarketingWorkflowTrigger::MANUAL,
                null,
                null,
                ['demo' => 'queued'],
                $ownerMember->id,
                false,
            );
            $welcomeWorkflow->delay_minutes = 0;
            $welcomeWorkflow->save();
        }

        // Failed message example on a completed workflow message.
        if ($completed !== null) {
            $failedMessage = MarketingMessage::withoutGlobalScopes()
                ->where('workflow_execution_id', $completed->id)
                ->first();
            if ($failedMessage !== null) {
                $deliveryService->markFailed($failedMessage, 'hard_bounce', 'Simulated hard bounce for demo.');
            }
        }

        // Delivered message from a prior sent message.
        $sentMessage = MarketingMessage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', MarketingMessageStatus::SENT)
            ->first();
        if ($sentMessage !== null) {
            $deliveryService->markDelivered($sentMessage);
        }

        // Unsubscribed suppression for demo client.
        $suppressionService->create([
            'client_id' => $alex->id,
            'channel' => MarketingChannel::EMAIL,
            'contact_value' => $alex->email,
            'reason' => MarketingSuppressionReason::UNSUBSCRIBE,
            'source' => MarketingSuppressionSource::CLIENT_ACTION,
            'notes' => 'Demo unsubscribe for Module 10B.',
            'created_by_team_member_id' => $ownerMember->id,
        ]);
    }

    private function addServiceLine(Tenant $tenant, Appointment $appointment, BookableService $service): void
    {
        AppointmentServiceLine::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'appointment_id' => $appointment->id,
            'booking_service_id' => $service->id,
            'service_name' => $service->name,
            'duration_minutes' => $service->duration_minutes,
            'price_cents' => $service->base_price_cents,
            'sort_order' => 0,
        ]);
    }
}
