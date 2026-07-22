<?php

namespace Database\Seeders;

use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Notifications\Enums\NotificationCategory;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationPurpose;
use App\Domains\Notifications\Enums\NotificationSourceType;
use App\Domains\Notifications\Services\NotificationAutomationSettingService;
use App\Domains\Notifications\Services\NotificationMessageService;
use App\Domains\Notifications\Services\NotificationPreferenceService;
use App\Domains\Notifications\Services\NotificationTemplateService;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Seeds a realistic Module 11A (Notifications & Communications) demo footprint on
 * top of the CRM + Booking + Payments demo data.
 *
 * Everything is created through the notifications domain services so the seeded
 * state mirrors production behaviour: operational templates, tenant settings,
 * consent-projected preferences and simulated operational messages (sent,
 * failed and suppressed).
 */
class NotificationsDemoSeeder extends Seeder
{
    public function run(Tenant $tenant, Location $location, TeamMember $ownerMember): void
    {
        app(TenantContext::class)->set($tenant);

        $alex = Client::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', 'alex.taylor@example.com')
            ->first();

        $jordan = Client::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', 'jordan.lee@example.com')
            ->first();

        if ($alex === null) {
            return;
        }

        $templateService = app(NotificationTemplateService::class);
        $settingService = app(NotificationAutomationSettingService::class);
        $preferenceService = app(NotificationPreferenceService::class);
        $messageService = app(NotificationMessageService::class);

        // 1. Operational settings.
        $settingService->update([
            'default_booking_reminder_hours' => 24,
            'default_booking_reminder_minutes' => 45,
            'default_payment_reminder_days' => 3,
            'sender_name' => 'Demo Salon',
            'sender_email' => 'hello@demo.neatmeet.local',
            'sender_sms_name' => 'DemoSalon',
        ]);

        // 2. Operational templates.
        $templateService->create([
            'name' => 'Booking reminder (email)',
            'channel' => NotificationChannel::EMAIL,
            'category' => NotificationCategory::BOOKING,
            'subject' => 'Your appointment is coming up',
            'body_text' => 'Hi {{client_first_name}}, this is a reminder for your upcoming appointment.',
            'created_by_team_member_id' => $ownerMember->id,
        ]);

        $templateService->create([
            'name' => 'Payment link (SMS)',
            'channel' => NotificationChannel::SMS,
            'category' => NotificationCategory::PAYMENTS,
            'body_text' => 'Complete your payment here: {{payment_link}}',
            'created_by_team_member_id' => $ownerMember->id,
        ]);

        $templateService->create([
            'name' => 'Membership expiry (email)',
            'channel' => NotificationChannel::EMAIL,
            'category' => NotificationCategory::MEMBERSHIP,
            'subject' => 'Your membership is expiring soon',
            'body_text' => 'Hi {{client_first_name}}, your membership is expiring soon.',
            'created_by_team_member_id' => $ownerMember->id,
        ]);

        // 3. Preferences projected from CRM consent.
        $preferenceService->syncFromConsent($alex);
        if ($jordan !== null) {
            $preferenceService->syncFromConsent($jordan);
        }

        // 4. Operational messages (dispatched via simulation).
        // Booking reminder — will be sent.
        $messageService->createSystemMessage([
            'client_id' => $alex->id,
            'source_type' => NotificationSourceType::BOOKING,
            'purpose' => NotificationPurpose::BOOKING_REMINDER,
            'channel' => NotificationChannel::EMAIL,
            'subject' => 'Your appointment is coming up',
            'body_text' => 'Hi Alex, this is a reminder for your upcoming appointment.',
        ]);

        // Payment link — will be sent.
        $messageService->createSystemMessage([
            'client_id' => $alex->id,
            'source_type' => NotificationSourceType::PAYMENTS,
            'purpose' => NotificationPurpose::PAYMENT_LINK,
            'channel' => NotificationChannel::SMS,
            'body_text' => 'Complete your payment: https://demo.neatmeet.local/pay/abc',
        ]);

        // Failed message — recipient address forces a simulated failure.
        $messageService->createSystemMessage([
            'client_id' => $alex->id,
            'source_type' => NotificationSourceType::SYSTEM,
            'purpose' => NotificationPurpose::BOOKING_CONFIRMATION,
            'channel' => NotificationChannel::EMAIL,
            'recipient_address' => 'fail@demo.neatmeet.local',
            'subject' => 'Booking confirmed',
            'body_text' => 'This send is designed to fail in the simulation.',
        ]);

        // Suppressed message — disable booking notifications first.
        if ($jordan !== null) {
            $preferenceService->update($jordan, ['booking_notifications' => false]);
            $messageService->createSystemMessage([
                'client_id' => $jordan->id,
                'source_type' => NotificationSourceType::BOOKING,
                'purpose' => NotificationPurpose::BOOKING_REMINDER,
                'channel' => NotificationChannel::EMAIL,
                'subject' => 'Your appointment is coming up',
                'body_text' => 'This should be suppressed by the preference projection.',
            ]);
        }
    }
}
