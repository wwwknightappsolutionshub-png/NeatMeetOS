<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\Enums\NotificationCategory;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationPurpose;
use App\Domains\Notifications\Models\NotificationTemplate;
use App\Shared\Audit\AuditLogger;

/**
 * Idempotent operational starter templates (email + in-app preferred).
 * Matched by stable name so re-running never duplicates.
 */
class NotificationStarterTemplateService
{
    public function __construct(
        private readonly NotificationScopeValidator $scope,
        private readonly NotificationTemplateService $templates,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array{created: int, skipped: int, templates: list<NotificationTemplate>}
     */
    public function installSamples(): array
    {
        $this->scope->tenantId();

        $created = 0;
        $skipped = 0;
        $templates = [];

        foreach ($this->definitions() as $definition) {
            $existing = NotificationTemplate::query()
                ->where('name', $definition['name'])
                ->first();

            if ($existing !== null) {
                $skipped++;
                $templates[] = $existing;
                continue;
            }

            $template = $this->templates->create([
                ...$definition,
                'is_system' => false,
                'is_active' => true,
            ]);

            $created++;
            $templates[] = $template;
        }

        if ($created > 0) {
            $this->auditLogger->log('notification_template.samples_installed', null, null, [
                'created' => $created,
                'skipped' => $skipped,
            ]);
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'templates' => $templates,
        ];
    }

    /**
     * @return list<array{
     *   name: string,
     *   slug: string,
     *   channel: string,
     *   category: string,
     *   subject?: string|null,
     *   body_text: string,
     *   body_html?: string|null
     * }>
     */
    public function definitions(): array
    {
        $defs = [];

        foreach ($this->purposeCopy() as $purpose => $copy) {
            $category = $this->categoryForPurpose($purpose);
            $slugBase = str_replace('_', '-', $purpose);

            $defs[] = [
                'name' => $copy['name'].' (Email)',
                'slug' => $slugBase.'-email',
                'channel' => NotificationChannel::EMAIL,
                'category' => $category,
                'subject' => $copy['subject'],
                'body_text' => $copy['body_text'],
                'body_html' => '<p style="margin:0 0 12px;">'.nl2br(e($copy['body_text'])).'</p>',
            ];

            $defs[] = [
                'name' => $copy['name'].' (In-app)',
                'slug' => $slugBase.'-in-app',
                'channel' => NotificationChannel::IN_APP,
                'category' => $category,
                'subject' => $copy['subject'],
                'body_text' => $copy['in_app_body'] ?? $copy['body_text'],
                'body_html' => null,
            ];
        }

        return $defs;
    }

    /**
     * @return array<string, array{name: string, subject: string, body_text: string, in_app_body?: string}>
     */
    private function purposeCopy(): array
    {
        return [
            NotificationPurpose::BOOKING_CONFIRMATION => [
                'name' => 'Sample — Booking confirmation',
                'subject' => 'Booking confirmed',
                'body_text' => "Your appointment is confirmed. We look forward to seeing you.\n\nManage your booking from the link in this message if provided.",
                'in_app_body' => 'Your appointment is confirmed. Open your booking for details.',
            ],
            NotificationPurpose::BOOKING_REMINDER => [
                'name' => 'Sample — Booking reminder',
                'subject' => 'Appointment reminder',
                'body_text' => "Friendly reminder about your upcoming appointment.\n\nReply or use your manage link if you need to reschedule.",
                'in_app_body' => 'Reminder: you have an upcoming appointment. Check your booking details.',
            ],
            NotificationPurpose::BOOKING_CANCELLATION => [
                'name' => 'Sample — Booking cancellation',
                'subject' => 'Booking cancelled',
                'body_text' => "Your appointment has been cancelled. Book again any time when you are ready.",
                'in_app_body' => 'Your appointment was cancelled. You can book again when ready.',
            ],
            NotificationPurpose::WAITLIST_CONTACT => [
                'name' => 'Sample — Waitlist contact',
                'subject' => 'A spot opened up',
                'body_text' => "A space is available that matches your waitlist request. Reply soon to claim it.",
                'in_app_body' => 'A waitlist spot opened — open the member app or reply to claim it.',
            ],
            NotificationPurpose::PAYMENT_LINK => [
                'name' => 'Sample — Payment link',
                'subject' => 'Payment link for your visit',
                'body_text' => "Here is your payment link for your recent visit. Pay securely when ready.",
                'in_app_body' => 'Your payment link is ready. Complete payment when convenient.',
            ],
            NotificationPurpose::PAYMENT_REMINDER => [
                'name' => 'Sample — Payment reminder',
                'subject' => 'Payment reminder',
                'body_text' => "This is a reminder that a payment is still outstanding. Use your payment link to settle.",
                'in_app_body' => 'Reminder: a payment is still outstanding.',
            ],
            NotificationPurpose::MEMBERSHIP_RENEWAL_NOTICE => [
                'name' => 'Sample — Membership renewal',
                'subject' => 'Membership renewal',
                'body_text' => "Your membership renews soon. Keep enjoying member benefits — no action needed unless you want to change plan.",
                'in_app_body' => 'Your membership renews soon. Benefits continue unless you change plan.',
            ],
            NotificationPurpose::MEMBERSHIP_EXPIRY_NOTICE => [
                'name' => 'Sample — Membership expiry',
                'subject' => 'Membership ending soon',
                'body_text' => "Your membership is ending soon. Renew to keep your benefits and member rates.",
                'in_app_body' => 'Your membership ends soon — renew to keep benefits.',
            ],
            NotificationPurpose::CRM_JOIN_WELCOME => [
                'name' => 'Sample — CRM join welcome',
                'subject' => 'Welcome',
                'body_text' => "Welcome — thanks for joining our client list. Open the membership app and book online any time.",
                'in_app_body' => 'Welcome! Explore memberships, loyalty, and book your next visit.',
            ],
            NotificationPurpose::REFERRAL_THANK_YOU => [
                'name' => 'Sample — Referral thank you',
                'subject' => 'Thank you for your referral',
                'body_text' => "Thank you for inviting your friend. You have been rewarded accordingly — keep sharing the love.",
                'in_app_body' => 'Thanks for your referral — your reward is on the way.',
            ],
            NotificationPurpose::MANUAL_CLIENT_MESSAGE => [
                'name' => 'Sample — General notice',
                'subject' => 'A note from us',
                'body_text' => "A quick note from the team. Edit this template with your operational message.",
                'in_app_body' => 'You have a new note from the salon.',
            ],
        ];
    }

    private function categoryForPurpose(string $purpose): string
    {
        return match ($purpose) {
            NotificationPurpose::BOOKING_CONFIRMATION,
            NotificationPurpose::BOOKING_REMINDER,
            NotificationPurpose::BOOKING_CANCELLATION,
            NotificationPurpose::WAITLIST_CONTACT => NotificationCategory::BOOKING,
            NotificationPurpose::PAYMENT_LINK,
            NotificationPurpose::PAYMENT_REMINDER => NotificationCategory::PAYMENTS,
            NotificationPurpose::MEMBERSHIP_RENEWAL_NOTICE,
            NotificationPurpose::MEMBERSHIP_EXPIRY_NOTICE => NotificationCategory::MEMBERSHIP,
            NotificationPurpose::CRM_JOIN_WELCOME,
            NotificationPurpose::REFERRAL_THANK_YOU => NotificationCategory::CRM,
            default => NotificationCategory::GENERAL,
        };
    }
}
