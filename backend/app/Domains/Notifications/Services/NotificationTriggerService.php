<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\BookingChangeRequest;
use App\Domains\Booking\Models\WaitlistEntry;
use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientReferralSetting;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Memberships\Models\ClientMembership;
use App\Domains\Notifications\Enums\NotificationCategory;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationPreferenceCategory;
use App\Domains\Notifications\Enums\NotificationPurpose;
use App\Domains\Notifications\Enums\NotificationSourceType;
use App\Domains\Notifications\Models\NotificationMessage;
use App\Domains\Notifications\Services\PlatformWhatsAppSettingsService;
use App\Domains\Payments\Models\PaymentTransaction;
use App\Shared\Support\FrontendUrl;
use App\Shared\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Callable orchestration layer that other domains use to raise operational
 * communications. Simulation-first: every send routes through
 * NotificationMessageService -> NotificationDispatchSimulationService.
 *
 * Safe to call from Booking/Payments/Memberships/CRM.
 */
class NotificationTriggerService
{
    public function __construct(
        private readonly NotificationScopeValidator $scope,
        private readonly NotificationMessageService $messageService,
        private readonly NotificationTemplateService $templateService,
        private readonly NotificationAutomationSettingService $settingService,
        private readonly NotificationPreferenceService $preferences,
        private readonly TenantContext $tenantContext,
        private readonly PlatformWhatsAppSettingsService $platformWhatsApp,
    ) {}

    public function sendBookingConfirmation(Appointment $appointment, array $context = []): ?NotificationMessage
    {
        if (! $this->settingService->get()->booking_confirmation_enabled) {
            return null;
        }

        if ($appointment->client_id === null) {
            return null;
        }

        $client = $this->scope->findClient($appointment->client_id);
        $defaults = $this->defaultBookingCopy($appointment, NotificationPurpose::BOOKING_CONFIRMATION, $context);

        // Transactional booking confirmation: fan out Email + WhatsApp + in-app when available.
        return $this->fanOutClientChannels($client, [
            'source_type' => NotificationSourceType::BOOKING,
            'purpose' => NotificationPurpose::BOOKING_CONFIRMATION,
            'category' => NotificationCategory::BOOKING,
            'appointment_id' => $appointment->id,
            'context' => [
                'subject' => $context['subject'] ?? null,
                'body_text' => $context['body_text'] ?? null,
                'body_html' => $context['body_html'] ?? null,
                'fallback_subject' => $defaults['subject'],
                'fallback_body_text' => $defaults['body_text'],
                'metadata' => array_merge($defaults['metadata'], $context['metadata'] ?? []),
                'created_by_team_member_id' => $context['created_by_team_member_id'] ?? null,
            ],
        ], preferWhatsApp: true);
    }

    public function sendBookingReminder(Appointment $appointment, array $context = []): ?NotificationMessage
    {
        if (! $this->settingService->get()->booking_reminders_enabled) {
            return null;
        }

        return $this->sendForAppointment(
            $appointment,
            NotificationPurpose::BOOKING_REMINDER,
            NotificationCategory::BOOKING,
            $context,
        );
    }

    public function sendBookingCancellation(Appointment $appointment, array $context = []): ?NotificationMessage
    {
        if (! $this->settingService->get()->cancellation_notifications_enabled) {
            return null;
        }

        return $this->sendForAppointment(
            $appointment,
            NotificationPurpose::BOOKING_CANCELLATION,
            NotificationCategory::BOOKING,
            $context,
        );
    }

    public function sendBookingReschedule(Appointment $appointment, array $context = []): ?NotificationMessage
    {
        return $this->sendForAppointment(
            $appointment,
            NotificationPurpose::BOOKING_RESCHEDULE,
            NotificationCategory::BOOKING,
            $context,
        );
    }

    public function sendBookingChangeRequestToTenant(BookingChangeRequest $request): ?NotificationMessage
    {
        $request->loadMissing(['appointment.client', 'appointment.teamMember', 'appointment.location', 'appointment.serviceLines']);
        $appointment = $request->appointment;
        if ($appointment === null) {
            return null;
        }

        $links = $this->changeRequestLinks($request);
        $when = $appointment->starts_at?->toDayDateTimeString() ?? 'soon';
        $clientName = $appointment->client?->resolvedDisplayName() ?? 'Client';
        $ref = $appointment->booking_reference ?? $appointment->id;
        $fee = $request->late_fee_applies
            ? ' Late cancel fee: '.((int) ($request->late_fee_cents ?? 0)).' cents of deposit.'
            : ' Free window — please confirm (decline is not allowed).';
        $body = "Cancel request for {$ref}: {$clientName} at {$when}.{$fee}"
            .$this->linkBlock('Confirm', $links['accept_url'])
            .($request->decline_allowed ? $this->linkBlock('Decline', $links['decline_url']) : '');

        $internal = $this->messageService->createSystemMessage([
            'client_id' => $appointment->client_id,
            'appointment_id' => $appointment->id,
            'source_type' => NotificationSourceType::BOOKING,
            'purpose' => NotificationPurpose::BOOKING_CHANGE_REQUEST,
            'channel' => NotificationChannel::INTERNAL_NOTE,
            'recipient_name' => 'Front desk',
            'recipient_address' => 'desk@internal',
            'subject' => "Cancel request {$ref}",
            'body_text' => $body,
            'metadata' => [
                'change_request_id' => $request->id,
                'accept_url' => $links['accept_url'],
                'decline_url' => $links['decline_url'],
                'decline_allowed' => $request->decline_allowed,
            ],
        ]);

        $this->notifyTenantChannels(
            subject: "Cancel request {$ref}",
            body: $body,
            appointmentId: $appointment->id,
            purpose: NotificationPurpose::BOOKING_CHANGE_REQUEST,
            metadata: [
                'change_request_id' => $request->id,
                'accept_url' => $links['accept_url'],
                'decline_url' => $links['decline_url'],
            ],
        );

        return $internal;
    }

    public function sendBookingChangeRequestToCustomer(BookingChangeRequest $request): ?NotificationMessage
    {
        $request->loadMissing(['appointment.client']);
        $appointment = $request->appointment;
        if ($appointment === null || $appointment->client_id === null) {
            return null;
        }

        $links = $this->changeRequestLinks($request);
        $when = $appointment->starts_at?->toDayDateTimeString() ?? 'soon';
        $newWhen = $request->proposed_starts_at?->toDayDateTimeString() ?? 'a new time';
        $ref = $appointment->booking_reference ?? $appointment->id;
        $body = "The salon proposed moving your appointment ({$ref}) from {$when} to {$newWhen}."
            .$this->linkBlock('Confirm', $links['accept_url'])
            .$this->linkBlock('Keep original time', $links['decline_url']);

        return $this->fanOutClientChannels($appointment->client, [
            'source_type' => NotificationSourceType::BOOKING,
            'purpose' => NotificationPurpose::BOOKING_CHANGE_REQUEST,
            'category' => NotificationCategory::BOOKING,
            'appointment_id' => $appointment->id,
            'context' => [
                'subject' => "Postpone request ({$ref})",
                'body_text' => $body,
                'fallback_subject' => "Postpone request ({$ref})",
                'fallback_body_text' => $body,
                'metadata' => [
                    'change_request_id' => $request->id,
                    'accept_url' => $links['accept_url'],
                    'decline_url' => $links['decline_url'],
                ],
            ],
        ]);
    }

    public function sendBookingChangeRequestReminder(BookingChangeRequest $request): ?NotificationMessage
    {
        if ($request->initiated_by === BookingChangeRequest::INITIATED_BY_CUSTOMER) {
            $request->loadMissing(['appointment']);
            $appointment = $request->appointment;
            if ($appointment === null) {
                return null;
            }
            $links = $this->changeRequestLinks($request);
            $ref = $appointment->booking_reference ?? $appointment->id;
            $body = "Reminder: cancel request {$ref} still needs a response (reminder {$request->reminder_count})."
                .$this->linkBlock('Confirm', $links['accept_url'])
                .($request->decline_allowed ? $this->linkBlock('Decline', $links['decline_url']) : '');

            $this->notifyTenantChannels(
                subject: "Reminder: cancel request {$ref}",
                body: $body,
                appointmentId: $appointment->id,
                purpose: NotificationPurpose::BOOKING_CHANGE_REQUEST_REMINDER,
                metadata: ['change_request_id' => $request->id],
            );

            return $this->messageService->createSystemMessage([
                'client_id' => $appointment->client_id,
                'appointment_id' => $appointment->id,
                'source_type' => NotificationSourceType::BOOKING,
                'purpose' => NotificationPurpose::BOOKING_CHANGE_REQUEST_REMINDER,
                'channel' => NotificationChannel::INTERNAL_NOTE,
                'recipient_name' => 'Front desk',
                'recipient_address' => 'desk@internal',
                'subject' => "Reminder: cancel request {$ref}",
                'body_text' => $body,
                'metadata' => ['change_request_id' => $request->id],
            ]);
        }

        $request->loadMissing(['appointment.client']);
        $appointment = $request->appointment;
        if ($appointment === null || $appointment->client_id === null) {
            return null;
        }

        $links = $this->changeRequestLinks($request);
        $ref = $appointment->booking_reference ?? $appointment->id;
        $body = "Reminder: please confirm or decline the proposed new time for booking {$ref}."
            .$this->linkBlock('Confirm', $links['accept_url'])
            .$this->linkBlock('Keep original time', $links['decline_url']);

        return $this->fanOutClientChannels($appointment->client, [
            'source_type' => NotificationSourceType::BOOKING,
            'purpose' => NotificationPurpose::BOOKING_CHANGE_REQUEST_REMINDER,
            'category' => NotificationCategory::BOOKING,
            'appointment_id' => $appointment->id,
            'context' => [
                'subject' => "Postpone reminder ({$ref})",
                'body_text' => $body,
                'fallback_subject' => "Postpone reminder ({$ref})",
                'fallback_body_text' => $body,
                'metadata' => ['change_request_id' => $request->id],
            ],
        ]);
    }

    public function sendBookingChangeRequestDeclined(BookingChangeRequest $request): ?NotificationMessage
    {
        $request->loadMissing(['appointment.client']);
        $appointment = $request->appointment;
        if ($appointment === null) {
            return null;
        }

        $ref = $appointment->booking_reference ?? $appointment->id;
        $when = $appointment->starts_at?->toDayDateTimeString() ?? 'the original time';

        if ($request->initiated_by === BookingChangeRequest::INITIATED_BY_TENANT
            && $appointment->client_id !== null
        ) {
            $body = "Your original appointment time stays: {$when}. Reference {$ref}.";

            return $this->fanOutClientChannels($appointment->client, [
                'source_type' => NotificationSourceType::BOOKING,
                'purpose' => NotificationPurpose::BOOKING_CHANGE_REQUEST_DECLINED,
                'category' => NotificationCategory::BOOKING,
                'appointment_id' => $appointment->id,
                'context' => [
                    'subject' => "Postpone declined ({$ref})",
                    'body_text' => $body,
                    'fallback_subject' => "Postpone declined ({$ref})",
                    'fallback_body_text' => $body,
                    'metadata' => ['change_request_id' => $request->id],
                ],
            ]);
        }

        if ($request->initiated_by === BookingChangeRequest::INITIATED_BY_CUSTOMER) {
            $body = "Your cancel request for {$ref} was declined. Appointment remains at {$when}.";
            if ($appointment->client_id !== null) {
                return $this->fanOutClientChannels($appointment->client, [
                    'source_type' => NotificationSourceType::BOOKING,
                    'purpose' => NotificationPurpose::BOOKING_CHANGE_REQUEST_DECLINED,
                    'category' => NotificationCategory::BOOKING,
                    'appointment_id' => $appointment->id,
                    'context' => [
                        'subject' => "Cancel declined ({$ref})",
                        'body_text' => $body,
                        'fallback_subject' => "Cancel declined ({$ref})",
                        'fallback_body_text' => $body,
                        'metadata' => ['change_request_id' => $request->id],
                    ],
                ]);
            }
        }

        return null;
    }

    public function sendBookingFreeWindowReminder(Appointment $appointment): ?NotificationMessage
    {
        if ($appointment->client_id === null) {
            return null;
        }

        $appointment->loadMissing(['client']);
        $ref = $appointment->booking_reference ?? $appointment->id;
        $when = $appointment->starts_at?->toDayDateTimeString() ?? 'soon';
        $manage = $this->manageLinksFor($appointment);
        $body = "Reminder: you have about 10 minutes left to cancel or postpone booking {$ref} (at {$when}) without a late fee."
            .$this->manageLinkBlock($manage['manage_url']);

        return $this->fanOutClientChannels($appointment->client, [
            'source_type' => NotificationSourceType::BOOKING,
            'purpose' => NotificationPurpose::BOOKING_FREE_WINDOW_REMINDER,
            'category' => NotificationCategory::BOOKING,
            'appointment_id' => $appointment->id,
            'context' => [
                'subject' => "Free cancel window closing ({$ref})",
                'body_text' => $body,
                'fallback_subject' => "Free cancel window closing ({$ref})",
                'fallback_body_text' => $body,
                'metadata' => [
                    'booking_reference' => $appointment->booking_reference,
                    'manage_url' => $manage['manage_url'],
                ],
            ],
        ], preferWhatsApp: true);
    }

    /**
     * Desk alert for new online bookings (internal note + tenant email/WhatsApp).
     */
    public function sendOnlineBookingStaffAlert(Appointment $appointment, array $context = []): ?NotificationMessage
    {
        $appointment->loadMissing(['client', 'teamMember.user', 'location', 'serviceLines']);

        $when = $appointment->starts_at?->toDayDateTimeString() ?? 'soon';
        $clientName = $appointment->client?->resolvedDisplayName() ?? 'Client';
        $ref = $appointment->booking_reference ?? $appointment->id;
        $services = $appointment->serviceLines->pluck('service_name')->filter()->implode(', ') ?: 'Service';
        $body = "New online booking {$ref}: {$clientName} — {$services} at {$when}"
            .($appointment->location ? ' @ '.$appointment->location->name : '')
            .($appointment->teamMember ? ' with '.$appointment->teamMember->display_name : '')
            .'.';

        $internal = $this->messageService->createSystemMessage([
            'client_id' => $appointment->client_id,
            'appointment_id' => $appointment->id,
            'source_type' => NotificationSourceType::BOOKING,
            'purpose' => NotificationPurpose::INTERNAL_NOTE_DELIVERY,
            'channel' => NotificationChannel::INTERNAL_NOTE,
            'recipient_name' => 'Front desk',
            'recipient_address' => 'desk@internal',
            'subject' => "Online booking {$ref}",
            'body_text' => $body,
            'metadata' => array_merge([
                'alert' => 'online_booking_staff',
                'booking_reference' => $appointment->booking_reference,
            ], $context['metadata'] ?? []),
        ]);

        $this->notifyTenantChannels(
            subject: "New online booking {$ref}",
            body: $body,
            appointmentId: $appointment->id,
            purpose: NotificationPurpose::INTERNAL_NOTE_DELIVERY,
            metadata: array_merge([
                'alert' => 'online_booking_staff',
                'booking_reference' => $appointment->booking_reference,
            ], $context['metadata'] ?? []),
            fallbackEmail: trim((string) ($appointment->teamMember?->user?->email ?? '')),
        );

        return $internal;
    }

    public function sendWaitlistContact(WaitlistEntry $entry, array $context = []): ?NotificationMessage
    {
        if ($entry->client_id === null) {
            return null;
        }

        $client = $this->scope->findClient($entry->client_id);

        return $this->create($client, [
            'source_type' => NotificationSourceType::BOOKING,
            'purpose' => NotificationPurpose::WAITLIST_CONTACT,
            'category' => NotificationCategory::BOOKING,
            'context' => $context,
        ]);
    }

    public function sendPaymentLink(PaymentTransaction $payment, array $context = []): ?NotificationMessage
    {
        if (! $this->settingService->get()->payment_link_notifications_enabled) {
            return null;
        }
        if ($payment->client_id === null) {
            return null;
        }

        $client = $this->scope->findClient($payment->client_id);

        return $this->create($client, [
            'source_type' => NotificationSourceType::PAYMENTS,
            'purpose' => NotificationPurpose::PAYMENT_LINK,
            'category' => NotificationCategory::PAYMENTS,
            'payment_transaction_id' => $payment->id,
            'context' => $context,
        ]);
    }

    public function sendPaymentReminder(PaymentTransaction $payment, array $context = []): ?NotificationMessage
    {
        if (! $this->settingService->get()->payment_reminders_enabled) {
            return null;
        }
        if ($payment->client_id === null) {
            return null;
        }

        $client = $this->scope->findClient($payment->client_id);

        return $this->create($client, [
            'source_type' => NotificationSourceType::PAYMENTS,
            'purpose' => NotificationPurpose::PAYMENT_REMINDER,
            'category' => NotificationCategory::PAYMENTS,
            'payment_transaction_id' => $payment->id,
            'context' => $context,
        ]);
    }

    public function sendMembershipExpiryNotice(ClientMembership $membership, array $context = []): ?NotificationMessage
    {
        if (! $this->settingService->get()->membership_expiry_notifications_enabled) {
            return null;
        }

        $client = $this->scope->findClient($membership->client_id);

        return $this->create($client, [
            'source_type' => NotificationSourceType::MEMBERSHIPS,
            'purpose' => NotificationPurpose::MEMBERSHIP_EXPIRY_NOTICE,
            'category' => NotificationCategory::MEMBERSHIP,
            'client_membership_id' => $membership->id,
            'context' => $context,
        ]);
    }

    public function sendMembershipRenewalNotice(ClientMembership $membership, array $context = []): ?NotificationMessage
    {
        if (! $this->settingService->get()->membership_renewal_notifications_enabled) {
            return null;
        }

        $client = $this->scope->findClient($membership->client_id);

        return $this->create($client, [
            'source_type' => NotificationSourceType::MEMBERSHIPS,
            'purpose' => NotificationPurpose::MEMBERSHIP_RENEWAL_NOTICE,
            'category' => NotificationCategory::MEMBERSHIP,
            'client_membership_id' => $membership->id,
            'context' => $context,
        ]);
    }

    /**
     * Branded welcome email after CRM join QR signup (email required on client).
     */
    public function sendCrmJoinWelcome(Client $client, array $offers = []): ?NotificationMessage
    {
        $this->scope->assertTenantModel($client);

        $email = trim((string) ($client->email ?? ''));
        if ($email === '') {
            return null;
        }

        $tenant = $this->tenantContext->get();
        $branding = $tenant?->getBranding() ?? [];
        $salonName = trim((string) ($branding['brand_display_name'] ?? ''))
            ?: ($tenant?->trading_name ?: $tenant?->name ?: 'Your salon');
        $primary = trim((string) ($branding['primary_color'] ?? '')) ?: '#2f5a45';
        $slug = (string) ($tenant?->slug ?? '');
        $pwaUrl = FrontendUrl::memberApp($slug);
        $bookUrl = FrontendUrl::bookingPage($slug);
        $first = trim((string) ($client->first_name ?? 'there'));
        $salonNameEsc = e($salonName);
        $firstEsc = e($first);
        $primaryEsc = e($primary);
        $pwaUrlEsc = e($pwaUrl);
        $bookUrlEsc = e($bookUrl);

        $membershipLines = '';
        foreach ($offers['memberships'] ?? [] as $plan) {
            $price = isset($plan['price_cents']) ? '£'.number_format(((int) $plan['price_cents']) / 100, 2) : '';
            $desc = e((string) ($plan['description'] ?? ''));
            $name = e((string) ($plan['name'] ?? 'Membership'));
            $membershipLines .= "<li style=\"margin:0 0 8px;\"><strong>{$name}</strong>"
                .($price !== '' ? " — {$price}" : '')
                .($desc !== '' ? "<br><span style=\"color:#555;\">{$desc}</span>" : '')
                .'</li>';
        }
        foreach ($offers['packages'] ?? [] as $pkg) {
            $price = isset($pkg['price_cents']) ? '£'.number_format(((int) $pkg['price_cents']) / 100, 2) : '';
            $desc = e((string) ($pkg['description'] ?? ''));
            $name = e((string) ($pkg['name'] ?? 'Package'));
            $membershipLines .= "<li style=\"margin:0 0 8px;\"><strong>{$name}</strong>"
                .($price !== '' ? " — {$price}" : '')
                .($desc !== '' ? "<br><span style=\"color:#555;\">{$desc}</span>" : '')
                .'</li>';
        }

        $loyaltyBlock = '';
        if (! empty($offers['loyalty']['enabled'])) {
            $loyaltyDesc = e((string) ($offers['loyalty']['description'] ?? 'Earn points and redeem rewards on every visit.'));
            $loyaltyHeadline = e((string) ($offers['loyalty']['headline'] ?? 'Loyalty rewards'));
            $loyaltyBlock = "<h3 style=\"margin:24px 0 8px;color:{$primaryEsc};font-size:16px;\">{$loyaltyHeadline}</h3>"
                ."<p style=\"margin:0;color:#444;line-height:1.5;\">{$loyaltyDesc}</p>";
        }

        $offersSection = $membershipLines !== ''
            ? '<h3 style="margin:24px 0 8px;color:'.$primaryEsc.';font-size:16px;">Membership &amp; packages</h3>'
                .'<ul style="padding-left:18px;margin:0;color:#222;">'.$membershipLines.'</ul>'
            : '<p style="margin:16px 0 0;color:#555;">Ask us about membership and package options on your next visit.</p>';

        $bodyHtml = <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#18181b;">
  <div style="background:{$primaryEsc};color:#fff;padding:20px 24px;border-radius:12px 12px 0 0;">
    <p style="margin:0;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;opacity:0.85;">Thank you and Welcome</p>
    <h1 style="margin:8px 0 0;font-size:24px;">{$salonNameEsc}</h1>
  </div>
  <div style="border:1px solid #e4e4e7;border-top:none;padding:24px;border-radius:0 0 12px 12px;">
    <p style="margin:0 0 12px;font-size:16px;">Hi {$firstEsc},</p>
    <p style="margin:0 0 12px;line-height:1.5;color:#444;">
      Thanks for joining the {$salonNameEsc} client list. We’re glad you’re here.
    </p>
    {$offersSection}
    {$loyaltyBlock}
    <div style="margin:28px 0 12px;padding:16px;background:#f4f4f5;border-radius:10px;">
      <p style="margin:0 0 8px;font-weight:700;color:{$primaryEsc};">Get the {$salonNameEsc} app</p>
      <p style="margin:0 0 12px;color:#555;font-size:14px;line-height:1.45;">
        Open your membership app to view benefits, unlock member pricing, and book faster.
      </p>
      <a href="{$pwaUrlEsc}" style="display:inline-block;background:{$primaryEsc};color:#fff;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:600;font-size:14px;">
        Download / open app
      </a>
    </div>
    <p style="margin:16px 0 0;font-size:14px;">
      <a href="{$bookUrlEsc}" style="color:{$primaryEsc};">Book an appointment</a>
    </p>
  </div>
</div>
HTML;

        $bodyText = "Hi {$first},\n\nThank you and welcome to {$salonName}!\n\n"
            ."Open your membership app: {$pwaUrl}\n"
            ."Book online: {$bookUrl}\n";

        $emailMessage = $this->messageService->createSystemMessage([
            'client_id' => $client->id,
            'source_type' => NotificationSourceType::CRM,
            'purpose' => NotificationPurpose::CRM_JOIN_WELCOME,
            'channel' => NotificationChannel::EMAIL,
            'recipient_name' => $client->resolvedDisplayName(),
            'recipient_address' => $email,
            'subject' => "Thank you and Welcome — {$salonName}",
            'body_text' => $bodyText,
            'body_html' => $bodyHtml,
            'metadata' => [
                'via' => 'crm_join_form',
                'pwa_url' => $pwaUrl,
                'href' => $pwaUrl,
                'tenant_slug' => $slug,
            ],
        ]);

        try {
            $this->messageService->createSystemMessage([
                'client_id' => $client->id,
                'source_type' => NotificationSourceType::CRM,
                'purpose' => NotificationPurpose::CRM_JOIN_WELCOME,
                'channel' => NotificationChannel::IN_APP,
                'subject' => "Thank you and Welcome — {$salonName}",
                'body_text' => "Thank you and welcome to {$salonName}! Open the app to explore memberships and book.",
                'metadata' => [
                    'via' => 'crm_join_form',
                    'href' => $pwaUrl,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('CRM join welcome in-app failed', ['error' => $e->getMessage()]);
        }

        return $emailMessage;
    }

    /**
     * Thank-you email to referrer after a successful referral join conversion.
     *
     * @param  array{subject?: string, body_text?: string, points?: int, referred_client_id?: string}  $context
     */
    public function sendReferralThankYou(Client $referrer, array $context = []): ?NotificationMessage
    {
        $this->scope->assertTenantModel($referrer);

        $email = trim((string) ($referrer->email ?? ''));
        if ($email === '') {
            return null;
        }

        $tenant = $this->tenantContext->get();
        $salonName = $tenant?->trading_name ?: $tenant?->name ?: 'Your salon';
        $first = trim((string) ($referrer->first_name ?? 'there'));
        $points = (int) ($context['points'] ?? 0);
        $subject = trim((string) ($context['subject'] ?? 'Thank you for your referral'))
            ?: 'Thank you for your referral';
        $bodyText = trim((string) ($context['body_text'] ?? ClientReferralSetting::DEFAULT_THANK_YOU_BODY))
            ?: 'Thank you for inviting your friend, you have been rewarded accordingly. Keep up with the energy, we appreciate you.';

        if ($points > 0 && ! str_contains($bodyText, (string) $points)) {
            $bodyText .= "\n\nYou earned {$points} loyalty points.";
        }

        $bodyHtml = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#18181b;">'
            .'<p style="margin:0 0 12px;font-size:16px;">Hi '.e($first).',</p>'
            .'<p style="margin:0;line-height:1.5;color:#444;">'.nl2br(e($bodyText)).'</p>'
            .'<p style="margin:20px 0 0;color:#71717a;font-size:13px;">'.$salonName.'</p>'
            .'</div>';

        $emailMessage = $this->messageService->createSystemMessage([
            'client_id' => $referrer->id,
            'source_type' => NotificationSourceType::CRM,
            'purpose' => NotificationPurpose::REFERRAL_THANK_YOU,
            'channel' => NotificationChannel::EMAIL,
            'recipient_name' => $referrer->resolvedDisplayName(),
            'recipient_address' => $email,
            'subject' => $subject,
            'body_text' => $bodyText,
            'body_html' => $bodyHtml,
            'metadata' => [
                'via' => 'client_referral_conversion',
                'points' => $points,
                'referred_client_id' => $context['referred_client_id'] ?? null,
            ],
        ]);

        try {
            $this->messageService->createSystemMessage([
                'client_id' => $referrer->id,
                'source_type' => NotificationSourceType::CRM,
                'purpose' => NotificationPurpose::REFERRAL_THANK_YOU,
                'channel' => NotificationChannel::IN_APP,
                'subject' => $subject,
                'body_text' => $bodyText,
                'metadata' => [
                    'via' => 'client_referral_conversion',
                    'points' => $points,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Referral thank-you in-app failed', ['error' => $e->getMessage()]);
        }

        return $emailMessage;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendManualClientMessage(Client $client, array $payload): NotificationMessage
    {
        $this->scope->assertTenantModel($client);

        return $this->messageService->createManual([
            'client_id' => $client->id,
            'channel' => $payload['channel'] ?? $this->preferredChannel($client),
            'subject' => $payload['subject'] ?? null,
            'body_text' => $payload['body_text'] ?? null,
            'body_html' => $payload['body_html'] ?? null,
            'notification_template_id' => $payload['notification_template_id'] ?? null,
            'metadata' => $payload['metadata'] ?? null,
            'created_by_team_member_id' => $payload['created_by_team_member_id'] ?? null,
        ]);
    }

    /**
     * Domain-safe helper: never let a notification failure break the caller.
     */
    public function safe(callable $callback): ?NotificationMessage
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            Log::warning('Notification trigger failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function sendForAppointment(
        Appointment $appointment,
        string $purpose,
        string $category,
        array $context,
    ): ?NotificationMessage {
        if ($appointment->client_id === null) {
            return null;
        }

        $client = $this->scope->findClient($appointment->client_id);
        $defaults = $this->defaultBookingCopy($appointment, $purpose, $context);

        return $this->create($client, [
            'source_type' => NotificationSourceType::BOOKING,
            'purpose' => $purpose,
            'category' => $category,
            'appointment_id' => $appointment->id,
            'context' => [
                'subject' => $context['subject'] ?? null,
                'body_text' => $context['body_text'] ?? null,
                'body_html' => $context['body_html'] ?? null,
                'fallback_subject' => $defaults['subject'],
                'fallback_body_text' => $defaults['body_text'],
                'metadata' => array_merge($defaults['metadata'], $context['metadata'] ?? []),
                'created_by_team_member_id' => $context['created_by_team_member_id'] ?? null,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{subject: string, body_text: string, metadata: array<string, mixed>}
     */
    private function defaultBookingCopy(Appointment $appointment, string $purpose, array $context = []): array
    {
        $appointment->loadMissing(['location', 'teamMember', 'serviceLines']);
        $when = $appointment->starts_at?->toDayDateTimeString() ?? 'your scheduled time';
        $ref = $appointment->booking_reference ?? $appointment->id;
        $manage = $this->manageLinksFor($appointment);
        $manageBlock = $this->manageLinkBlock($manage['manage_url']);

        $metadata = [
            'booking_reference' => $appointment->booking_reference,
            'public_manage_token' => $appointment->public_manage_token,
            'manage_url' => $manage['manage_url'],
        ];

        return match ($purpose) {
            NotificationPurpose::BOOKING_CONFIRMATION => [
                'subject' => "Booking confirmed ({$ref})",
                'body_text' => "Your appointment is confirmed for {$when}. Reference {$ref}.{$manageBlock}",
                'metadata' => $metadata,
            ],
            NotificationPurpose::BOOKING_REMINDER => [
                'subject' => "Appointment reminder ({$ref})",
                'body_text' => "Reminder: your appointment is at {$when}. Reference {$ref}.{$manageBlock}",
                'metadata' => $metadata,
            ],
            NotificationPurpose::BOOKING_CANCELLATION => [
                'subject' => "Booking cancelled ({$ref})",
                'body_text' => "Your appointment ({$ref}) scheduled for {$when} has been cancelled.",
                'metadata' => $metadata,
            ],
            NotificationPurpose::BOOKING_RESCHEDULE => [
                'subject' => "Booking rescheduled ({$ref})",
                'body_text' => $this->rescheduleBody($appointment, $when, $ref, $manageBlock, $context),
                'metadata' => $metadata,
            ],
            default => [
                'subject' => "Booking update ({$ref})",
                'body_text' => "Update for booking {$ref} at {$when}.",
                'metadata' => $metadata,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function rescheduleBody(
        Appointment $appointment,
        string $when,
        string $ref,
        string $manageBlock,
        array $context,
    ): string {
        $shift = isset($context['shift_minutes']) ? (int) $context['shift_minutes'] : null;
        $prev = isset($context['previous_starts_at'])
            ? Carbon::parse((string) $context['previous_starts_at'])->toDayDateTimeString()
            : null;

        $intro = $shift
            ? "We've moved your appointment forward by {$shift} minutes."
            : 'Your appointment has been rescheduled.';

        $prevLine = $prev ? " Previous time: {$prev}." : '';

        return "{$intro} New time: {$when}. Reference {$ref}.{$prevLine}{$manageBlock}";
    }

    /**
     * WhatsApp-friendly link: label on one line, URL alone on the next (better link preview).
     */
    private function linkBlock(string $label, ?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        return "\n\n{$label}\n{$url}";
    }

    private function manageLinkBlock(?string $url): string
    {
        return $this->linkBlock('Manage your booking', $url);
    }

    /**
     * @return array{manage_path: string|null, manage_url: string|null}
     */
    private function manageLinksFor(Appointment $appointment): array
    {
        if (empty($appointment->booking_reference) || empty($appointment->public_manage_token)) {
            return ['manage_path' => null, 'manage_url' => null];
        }

        $slug = $this->tenantContext->get()?->slug ?? '';
        $path = '/book/'.$slug.'/manage?ref='.urlencode((string) $appointment->booking_reference)
            .'&token='.urlencode((string) $appointment->public_manage_token);

        return [
            'manage_path' => $path,
            'manage_url' => rtrim((string) config('app.frontend_url'), '/').$path,
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function create(Client $client, array $spec): NotificationMessage
    {
        $primaryChannel = $spec['channel'] ?? $this->preferredChannel($client);
        $primary = $this->createOnChannel($client, $spec, $primaryChannel);

        $alsoInApp = (bool) ($spec['also_in_app'] ?? true);
        if ($alsoInApp
            && $primaryChannel !== NotificationChannel::IN_APP
            && $client->is_active
        ) {
            try {
                $this->createOnChannel($client, $spec, NotificationChannel::IN_APP);
            } catch (\Throwable $e) {
                Log::warning('Notification in-app fan-out failed', [
                    'client_id' => $client->id,
                    'purpose' => $spec['purpose'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $primary;
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function createOnChannel(Client $client, array $spec, string $channel): NotificationMessage
    {
        $template = $this->templateService->resolveFor(
            $spec['category'],
            $channel,
            $spec['notification_template_id'] ?? null,
            $spec['purpose'] ?? null,
        );

        $context = $spec['context'] ?? [];
        $subject = $context['subject'] ?? $template?->subject ?? ($context['fallback_subject'] ?? null);
        $bodyText = $context['body_text'] ?? $template?->body_text ?? ($context['fallback_body_text'] ?? null);
        $bodyHtml = $channel === NotificationChannel::EMAIL
            ? ($context['body_html'] ?? $template?->body_html)
            : null;

        if ($channel === NotificationChannel::IN_APP && $template !== null) {
            $subject = $template->subject ?? $subject;
            $bodyText = $template->body_text ?? $bodyText;
        }

        return $this->messageService->createSystemMessage([
            'client_id' => $client->id,
            'appointment_id' => $spec['appointment_id'] ?? null,
            'payment_transaction_id' => $spec['payment_transaction_id'] ?? null,
            'client_membership_id' => $spec['client_membership_id'] ?? null,
            'checkout_id' => $spec['checkout_id'] ?? null,
            'source_type' => $spec['source_type'],
            'purpose' => $spec['purpose'],
            'channel' => $channel,
            'notification_template_id' => $template?->id,
            'subject' => $subject,
            'body_text' => $bodyText,
            'body_html' => $bodyHtml,
            'metadata' => $context['metadata'] ?? null,
            'created_by_team_member_id' => $context['created_by_team_member_id'] ?? null,
        ]);
    }

    private function preferredChannel(Client $client): string
    {
        $phone = trim((string) ($client->phone ?? ''));
        if ($phone !== ''
            && $this->preferences->allowsDelivery(
                $client,
                NotificationChannel::WHATSAPP,
                NotificationPreferenceCategory::BOOKING,
            )
        ) {
            return NotificationChannel::WHATSAPP;
        }

        $email = trim((string) ($client->email ?? ''));
        if ($email !== '') {
            return NotificationChannel::EMAIL;
        }

        if ($phone !== '') {
            return NotificationChannel::SMS;
        }

        return NotificationChannel::IN_APP;
    }

    /**
     * @return array{manage_url: string, accept_url: string, decline_url: string}
     */
    private function changeRequestLinks(BookingChangeRequest $request): array
    {
        $slug = $this->tenantContext->get()?->slug ?? '';
        $base = rtrim((string) config('app.frontend_url'), '/');
        $path = '/book/'.$slug.'/change-request?id='.urlencode($request->id)
            .'&token='.urlencode($request->action_token);

        return [
            'manage_url' => $base.$path,
            'accept_url' => $base.$path.'&action=accept',
            'decline_url' => $base.$path.'&action=decline',
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function notifyTenantChannels(
        string $subject,
        string $body,
        string $appointmentId,
        string $purpose,
        array $metadata = [],
        string $fallbackEmail = '',
    ): void {
        $contacts = $this->resolveTenantDeskContacts($fallbackEmail);

        if ($contacts['email'] !== '') {
            try {
                $this->messageService->createSystemMessage([
                    'client_id' => null,
                    'appointment_id' => $appointmentId,
                    'source_type' => NotificationSourceType::BOOKING,
                    'purpose' => $purpose,
                    'channel' => NotificationChannel::EMAIL,
                    'recipient_name' => 'Salon desk',
                    'recipient_address' => $contacts['email'],
                    'subject' => $subject,
                    'body_text' => $body,
                    'metadata' => $metadata,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Tenant operational email failed', ['error' => $e->getMessage()]);
            }
        }

        if ($contacts['whatsapp'] === '') {
            return;
        }

        try {
            $this->messageService->createSystemMessage([
                'client_id' => null,
                'appointment_id' => $appointmentId,
                'source_type' => NotificationSourceType::BOOKING,
                'purpose' => $purpose,
                'channel' => NotificationChannel::WHATSAPP,
                'recipient_name' => 'Salon owner',
                'recipient_address' => $contacts['whatsapp'],
                'subject' => $subject,
                'body_text' => $body,
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Tenant operational WhatsApp message failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @return array{email: string, whatsapp: string}
     */
    private function resolveTenantDeskContacts(string $fallbackEmail = ''): array
    {
        $tenant = $this->tenantContext->get();
        $email = trim((string) (($tenant?->getBranding()['support_email'] ?? null) ?: ''));
        if ($email === '') {
            $email = trim($fallbackEmail);
        }
        if ($email === '' && $tenant !== null) {
            $owner = TeamMember::query()
                ->where('employment_type', TeamMember::EMPLOYMENT_OWNER)
                ->where('is_active', true)
                ->with('user')
                ->orderBy('created_at')
                ->first();
            $email = trim((string) ($owner?->user?->email ?? ''));
        }

        $whatsapp = trim((string) ($tenant?->owner_whatsapp ?? ''));
        if ($whatsapp === '' && $tenant !== null) {
            $owner = $owner ?? TeamMember::query()
                ->where('employment_type', TeamMember::EMPLOYMENT_OWNER)
                ->where('is_active', true)
                ->orderBy('created_at')
                ->first();
            $whatsapp = trim((string) ($owner?->phone ?? ''));
        }

        return [
            'email' => $email,
            'whatsapp' => $whatsapp,
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function fanOutClientChannels(Client $client, array $spec, bool $preferWhatsApp = false): ?NotificationMessage
    {
        $channels = [];
        $phone = trim((string) ($client->phone ?? ''));
        $email = trim((string) ($client->email ?? ''));
        $prefCategory = NotificationPurpose::preferenceCategory(
            (string) ($spec['purpose'] ?? NotificationPurpose::BOOKING_CONFIRMATION)
        );

        // Transactional booking fan-out: enable WhatsApp + email so confirmations are not suppressed.
        if ($preferWhatsApp) {
            $updates = [];
            if ($phone !== ''
                && ! $this->preferences->allowsDelivery($client, NotificationChannel::WHATSAPP, $prefCategory)
            ) {
                $updates['allow_whatsapp'] = true;
                $updates['preferred_channel'] = NotificationChannel::WHATSAPP;
            }
            if ($email !== ''
                && ! $this->preferences->allowsDelivery($client, NotificationChannel::EMAIL, $prefCategory)
            ) {
                $updates['allow_email'] = true;
            }
            if ($updates !== []) {
                try {
                    $this->preferences->update($client, $updates);
                } catch (\Throwable $e) {
                    Log::warning('Failed to enable channels for transactional fan-out', [
                        'client_id' => $client->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($phone !== ''
            && $this->preferences->allowsDelivery($client, NotificationChannel::WHATSAPP, $prefCategory)
        ) {
            $channels[] = NotificationChannel::WHATSAPP;
        }
        if ($email !== ''
            && ($preferWhatsApp || $this->preferences->allowsDelivery($client, NotificationChannel::EMAIL, $prefCategory))
        ) {
            $channels[] = NotificationChannel::EMAIL;
        }
        $channels[] = NotificationChannel::IN_APP;

        if ($preferWhatsApp && in_array(NotificationChannel::WHATSAPP, $channels, true)) {
            $channels = array_values(array_unique([
                NotificationChannel::WHATSAPP,
                ...$channels,
            ]));
        }

        $primary = null;
        foreach (array_unique($channels) as $channel) {
            try {
                $message = $this->createOnChannel($client, $spec, $channel);
                $primary ??= $message;
            } catch (\Throwable $e) {
                Log::warning('Client notification fan-out failed', [
                    'channel' => $channel,
                    'client_id' => $client->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $primary;
    }
}
