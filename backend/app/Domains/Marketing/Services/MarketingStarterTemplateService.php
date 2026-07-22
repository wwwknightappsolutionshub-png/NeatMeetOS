<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingTriggerType;
use App\Domains\Marketing\Enums\MarketingWorkflowTrigger;
use App\Domains\Marketing\Models\MarketingTemplate;
use App\Shared\Audit\AuditLogger;

/**
 * Idempotent starter email (and a few SMS) templates tenants can edit freely.
 * Matched by stable name so re-running never duplicates.
 */
class MarketingStarterTemplateService
{
    public function __construct(
        private readonly MarketingScopeValidator $scope,
        private readonly MarketingTemplateService $templates,
        private readonly AuditLogger $auditLogger,
        private readonly TemplateRendererService $renderer,
    ) {}

    /**
     * @return array{created: int, skipped: int, templates: list<MarketingTemplate>}
     */
    public function installSamples(): array
    {
        $this->scope->tenantId();

        $created = 0;
        $skipped = 0;
        $templates = [];

        foreach ($this->definitions() as $definition) {
            $existing = MarketingTemplate::query()
                ->where('name', $definition['name'])
                ->first();

            if ($existing !== null) {
                $skipped++;
                $templates[] = $existing;
                continue;
            }

            $template = $this->templates->create([
                ...$definition,
                'variables_json' => $this->renderer->supportedPlaceholders(),
                'is_system' => false,
                'is_active' => true,
            ]);

            $created++;
            $templates[] = $template;
        }

        if ($created > 0) {
            $this->auditLogger->log('marketing_template.samples_installed', null, null, [
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
     * Find a starter (or any) template by exact name within the current tenant.
     */
    public function findByName(string $name): ?MarketingTemplate
    {
        return MarketingTemplate::query()->where('name', $name)->first();
    }

    /**
     * @return list<array{
     *   name: string,
     *   category: string,
     *   channel: string,
     *   subject?: string|null,
     *   body_text: string,
     *   body_html?: string|null
     * }>
     */
    public function definitions(): array
    {
        return [
            [
                'name' => 'Sample — Booking reminder',
                'category' => MarketingTriggerType::BOOKING_REMINDER,
                'channel' => MarketingChannel::EMAIL,
                'subject' => 'Reminder: your appointment at {{business.name}}',
                'body_text' => "Hi {{client.first_name}},\n\n"
                    ."This is a reminder for your appointment ({{appointment.service_summary}}) "
                    ."at {{location.name}} on {{appointment.start_at}}.\n\n"
                    ."Need to reschedule? {{booking.link}}\n\n"
                    ."See you soon,\n{{business.name}}",
                'body_html' => $this->emailBody(
                    'Appointment reminder',
                    '<p style="margin:0 0 16px;">Hi {{client.first_name}},</p>'
                    .'<p style="margin:0 0 16px;">This is a friendly reminder for your upcoming appointment:</p>'
                    .'<table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 20px;">'
                    .'<tr><td style="padding:12px 14px;background:#f4f4f5;border-radius:8px;">'
                    .'<p style="margin:0 0 6px;"><strong>{{appointment.service_summary}}</strong></p>'
                    .'<p style="margin:0;color:#52525b;">{{appointment.start_at}} · {{location.name}}</p>'
                    .'</td></tr></table>'
                    .'<p style="margin:0 0 20px;">'
                    .'<a class="nm-cta" href="{{booking.link}}" style="display:inline-block;padding:10px 18px;background:#18181b;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Manage booking</a>'
                    .'</p>'
                    .'<p style="margin:0;">See you soon,<br><strong>{{business.name}}</strong></p>'
                ),
            ],
            [
                'name' => 'Sample — Review request',
                'category' => MarketingTriggerType::REVIEW_REQUEST,
                'channel' => MarketingChannel::EMAIL,
                'subject' => 'How was your visit to {{business.name}}?',
                'body_text' => "Hi {{client.first_name}},\n\n"
                    ."Thanks for visiting {{business.name}}! We would love your feedback: {{review.link}}\n\n"
                    ."{{business.name}}",
                'body_html' => $this->emailBody(
                    'We value your feedback',
                    '<p style="margin:0 0 16px;">Hi {{client.first_name}},</p>'
                    .'<p style="margin:0 0 16px;">Thank you for choosing <strong>{{business.name}}</strong>. '
                    .'A short review helps other clients and helps us keep improving.</p>'
                    .'<p style="margin:0 0 20px;">'
                    .'<a class="nm-cta" href="{{review.link}}" style="display:inline-block;padding:10px 18px;background:#18181b;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Leave a review</a>'
                    .'</p>'
                    .'<p style="margin:0;color:#52525b;">With thanks,<br>{{business.name}}</p>'
                ),
            ],
            [
                'name' => 'Sample — Win-back',
                'category' => MarketingTriggerType::WIN_BACK,
                'channel' => MarketingChannel::EMAIL,
                'subject' => 'We miss you at {{business.name}}',
                'body_text' => "Hi {{client.first_name}},\n\n"
                    ."It has been a while since your last visit to {{business.name}}. "
                    ."We would love to welcome you back — book here: {{booking.link}}\n\n"
                    ."Warm regards,\n{{business.name}}",
                'body_html' => $this->emailBody(
                    'We miss you',
                    '<p style="margin:0 0 16px;">Hi {{client.first_name}},</p>'
                    .'<p style="margin:0 0 16px;">It has been a while since we last saw you at <strong>{{business.name}}</strong>. '
                    .'We would love to welcome you back whenever you are ready.</p>'
                    .'<p style="margin:0 0 20px;">'
                    .'<a class="nm-cta" href="{{booking.link}}" style="display:inline-block;padding:10px 18px;background:#18181b;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Book your next visit</a>'
                    .'</p>'
                    .'<p style="margin:0;">Warm regards,<br>{{business.name}}</p>'
                ),
            ],
            [
                'name' => 'Sample — Rebooking nudge',
                'category' => MarketingTriggerType::REBOOKING_NUDGE,
                'channel' => MarketingChannel::EMAIL,
                'subject' => 'Time for your next visit to {{business.name}}?',
                'body_text' => "Hi {{client.first_name}},\n\n"
                    ."Ready for your next visit to {{business.name}}? Rebook here: {{booking.link}}\n\n"
                    ."{{business.name}}",
                'body_html' => $this->emailBody(
                    'Ready for your next visit?',
                    '<p style="margin:0 0 16px;">Hi {{client.first_name}},</p>'
                    .'<p style="margin:0 0 16px;">Keeping your routine on track is easier when you book ahead. '
                    .'Pick a time that works for you at {{location.name}}.</p>'
                    .'<p style="margin:0 0 20px;">'
                    .'<a class="nm-cta" href="{{booking.link}}" style="display:inline-block;padding:10px 18px;background:#18181b;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Rebook now</a>'
                    .'</p>'
                    .'<p style="margin:0;">{{business.name}}</p>'
                ),
            ],
            [
                'name' => 'Sample — Membership reminder',
                'category' => 'membership_reminder',
                'channel' => MarketingChannel::EMAIL,
                'subject' => 'Your {{membership.plan_name}} membership at {{business.name}}',
                'body_text' => "Hi {{client.first_name}},\n\n"
                    ."A quick note about your {{membership.plan_name}} membership with {{business.name}}. "
                    ."Book with your benefits here: {{booking.link}}\n\n"
                    ."{{business.name}}",
                'body_html' => $this->emailBody(
                    'Membership update',
                    '<p style="margin:0 0 16px;">Hi {{client.first_name}},</p>'
                    .'<p style="margin:0 0 16px;">This is a note about your <strong>{{membership.plan_name}}</strong> membership '
                    .'with {{business.name}}. Use your benefits any time you book.</p>'
                    .'<p style="margin:0 0 20px;">'
                    .'<a class="nm-cta" href="{{booking.link}}" style="display:inline-block;padding:10px 18px;background:#18181b;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Book with membership</a>'
                    .'</p>'
                    .'<p style="margin:0;">{{business.name}}</p>'
                ),
            ],
            [
                'name' => 'Sample — Seasonal promo',
                'category' => 'broadcast',
                'channel' => MarketingChannel::EMAIL,
                'subject' => 'Something special from {{business.name}}',
                'body_text' => "Hi {{client.first_name}},\n\n"
                    ."We have something special waiting for you at {{business.name}}. "
                    ."Book now: {{booking.link}}\n\n"
                    ."{{business.name}}",
                'body_html' => $this->emailBody(
                    'A treat for you',
                    '<p style="margin:0 0 16px;">Hi {{client.first_name}},</p>'
                    .'<p style="margin:0 0 16px;">We put together a seasonal offer for our favourite clients. '
                    .'Edit this message with your offer details, dates, and terms.</p>'
                    .'<p style="margin:0 0 20px;">'
                    .'<a class="nm-cta" href="{{booking.link}}" style="display:inline-block;padding:10px 18px;background:#18181b;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Book now</a>'
                    .'</p>'
                    .'<p style="margin:0;color:#52525b;">{{business.name}} · {{location.name}}</p>'
                ),
            ],
            [
                'name' => 'Sample — Birthday greeting',
                'category' => MarketingWorkflowTrigger::BIRTHDAY,
                'channel' => MarketingChannel::EMAIL,
                'subject' => 'Happy birthday, {{client.first_name}}!',
                'body_text' => "Hi {{client.first_name}},\n\n"
                    ."Happy birthday from everyone at {{business.name}}! Book your treat: {{booking.link}}\n\n"
                    ."{{business.name}}",
                'body_html' => $this->emailBody(
                    'Happy birthday!',
                    '<p style="margin:0 0 16px;">Hi {{client.first_name}},</p>'
                    .'<p style="margin:0 0 16px;">Wishing you a wonderful birthday from the team at <strong>{{business.name}}</strong>. '
                    .'Treat yourself — we would love to celebrate with you.</p>'
                    .'<p style="margin:0 0 20px;">'
                    .'<a class="nm-cta" href="{{booking.link}}" style="display:inline-block;padding:10px 18px;background:#18181b;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Book a birthday treat</a>'
                    .'</p>'
                    .'<p style="margin:0;">{{business.name}}</p>'
                ),
            ],
            [
                'name' => 'Sample — New client welcome',
                'category' => MarketingWorkflowTrigger::CLIENT_CREATED,
                'channel' => MarketingChannel::EMAIL,
                'subject' => 'Welcome to {{business.name}}',
                'body_text' => "Hi {{client.first_name}},\n\n"
                    ."Welcome to {{business.name}}! We are glad you joined us. Book here: {{booking.link}}\n\n"
                    ."{{business.name}}",
                'body_html' => $this->emailBody(
                    'Welcome',
                    '<p style="margin:0 0 16px;">Hi {{client.first_name}},</p>'
                    .'<p style="margin:0 0 16px;">Welcome to <strong>{{business.name}}</strong> — we are glad you are here. '
                    .'Book online any time, and reply to this email if you have questions before your first visit.</p>'
                    .'<p style="margin:0 0 20px;">'
                    .'<a class="nm-cta" href="{{booking.link}}" style="display:inline-block;padding:10px 18px;background:#18181b;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Book your first visit</a>'
                    .'</p>'
                    .'<p style="margin:0;">See you soon,<br>{{business.name}}</p>'
                ),
            ],
            [
                'name' => 'Sample — Monthly book nudge',
                'category' => 'monthly_book_nudge',
                'channel' => MarketingChannel::EMAIL,
                'subject' => 'Book your next visit at {{business.name}}',
                'body_text' => "Hi {{client.first_name}},\n\n"
                    ."A new month is a great time to book your next visit to {{business.name}}. "
                    ."Pick a time here: {{booking.link}}\n\n"
                    ."{{business.name}}",
                'body_html' => $this->emailBody(
                    'Book this month',
                    '<p style="margin:0 0 16px;">Hi {{client.first_name}},</p>'
                    .'<p style="margin:0 0 16px;">A new month is a great time to schedule your next visit to '
                    .'<strong>{{business.name}}</strong>. We would love to see you.</p>'
                    .'<p style="margin:0 0 20px;">'
                    .'<a class="nm-cta" href="{{booking.link}}" style="display:inline-block;padding:10px 18px;background:#18181b;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Book now</a>'
                    .'</p>'
                    .'<p style="margin:0;">{{business.name}}</p>'
                ),
            ],
            [
                'name' => 'Sample — Review request (SMS)',
                'category' => MarketingTriggerType::REVIEW_REQUEST,
                'channel' => MarketingChannel::SMS,
                'subject' => null,
                'body_text' => 'Hi {{client.first_name}}, thanks for visiting {{business.name}}! '
                    .'We would love your feedback: {{review.link}}',
                'body_html' => null,
            ],
            [
                'name' => 'Sample — Rebooking nudge (SMS)',
                'category' => MarketingTriggerType::REBOOKING_NUDGE,
                'channel' => MarketingChannel::SMS,
                'subject' => null,
                'body_text' => 'Hi {{client.first_name}}, ready for your next visit to {{business.name}}? '
                    .'Rebook here: {{booking.link}}',
                'body_html' => null,
            ],
            [
                'name' => 'Sample — Booking reminder (Push)',
                'category' => MarketingTriggerType::BOOKING_REMINDER,
                'channel' => MarketingChannel::PUSH,
                'subject' => 'Reminder: {{appointment.service_summary}}',
                'body_text' => 'Hi {{client.first_name}}, your appointment at {{location.name}} is on {{appointment.start_at}}. Manage: {{booking.link}}',
                'body_html' => null,
            ],
            [
                'name' => 'Sample — Win-back (Push)',
                'category' => MarketingTriggerType::WIN_BACK,
                'channel' => MarketingChannel::PUSH,
                'subject' => 'We miss you at {{business.name}}',
                'body_text' => 'Hi {{client.first_name}}, it has been a while. Book your next visit: {{booking.link}}',
                'body_html' => null,
            ],
            [
                'name' => 'Sample — Offer (In-app)',
                'category' => 'broadcast',
                'channel' => MarketingChannel::IN_APP,
                'subject' => 'A note from {{business.name}}',
                'body_text' => 'Hi {{client.first_name}}, we have something for you at {{location.name}}. Book when ready: {{booking.link}}',
                'body_html' => null,
            ],
            [
                'name' => 'Sample — Membership (In-app)',
                'category' => 'membership_reminder',
                'channel' => MarketingChannel::IN_APP,
                'subject' => 'Your {{membership.plan_name}} membership',
                'body_text' => 'Hi {{client.first_name}}, use your {{membership.plan_name}} benefits any time. Book: {{booking.link}}',
                'body_html' => null,
            ],
            [
                'name' => 'Sample — New client welcome (In-app)',
                'category' => MarketingWorkflowTrigger::CLIENT_CREATED,
                'channel' => MarketingChannel::IN_APP,
                'subject' => 'Welcome to {{business.name}}',
                'body_text' => 'Hi {{client.first_name}}, welcome to {{business.name}}! Book your first visit: {{booking.link}}',
                'body_html' => null,
            ],
            [
                'name' => 'Sample — Win-back (In-app)',
                'category' => MarketingTriggerType::WIN_BACK,
                'channel' => MarketingChannel::IN_APP,
                'subject' => 'We miss you at {{business.name}}',
                'body_text' => 'Hi {{client.first_name}}, it has been a while. Book your next visit: {{booking.link}}',
                'body_html' => null,
            ],
            [
                'name' => 'Sample — Birthday greeting (In-app)',
                'category' => MarketingWorkflowTrigger::BIRTHDAY,
                'channel' => MarketingChannel::IN_APP,
                'subject' => 'Happy birthday, {{client.first_name}}!',
                'body_text' => 'Happy birthday from {{business.name}}! Treat yourself — book here: {{booking.link}}',
                'body_html' => null,
            ],
            [
                'name' => 'Sample — Monthly book nudge (In-app)',
                'category' => 'monthly_book_nudge',
                'channel' => MarketingChannel::IN_APP,
                'subject' => 'Book your next visit',
                'body_text' => 'Hi {{client.first_name}}, a new month is a great time to book at {{business.name}}. Book: {{booking.link}}',
                'body_html' => null,
            ],
        ];
    }

    /**
     * Inner email body only — branded chrome is applied by MarketingEmailLayoutService at render time.
     */
    private function emailBody(string $headline, string $innerHtml): string
    {
        return '<h1 style="margin:0 0 20px;font-size:24px;line-height:1.25;font-weight:700;color:#18181b;">'.$headline.'</h1>'
            .$innerHtml;
    }
}
