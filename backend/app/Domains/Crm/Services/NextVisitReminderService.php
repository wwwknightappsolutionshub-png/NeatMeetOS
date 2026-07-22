<?php

namespace App\Domains\Crm\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientNotice;
use App\Domains\Crm\Models\ClientThreadMessage;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Services\TenantEntitlementService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class NextVisitReminderService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantEntitlementService $entitlements,
        private readonly ClientNoticeService $clientNotices,
        private readonly ClientThreadService $threads,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Dispatch 72h and 24h reminders for next_visit appointments within the lead windows.
     *
     * @return array{sent_72h: int, sent_24h: int}
     */
    public function dispatchForAllTenants(int $halfWindowMinutes = 15): array
    {
        $sent72 = 0;
        $sent24 = 0;

        $tenants = Tenant::query()->where('status', 'active')->get();

        foreach ($tenants as $tenant) {
            $this->tenantContext->set($tenant);

            try {
                if (! $this->entitlements->isEnabled($tenant, 'next_visit')) {
                    continue;
                }

                $result = $this->dispatchForCurrentTenant($halfWindowMinutes);
                $sent72 += $result['sent_72h'];
                $sent24 += $result['sent_24h'];
            } finally {
                $this->tenantContext->clear();
            }
        }

        return ['sent_72h' => $sent72, 'sent_24h' => $sent24];
    }

    /**
     * @return array{sent_72h: int, sent_24h: int}
     */
    public function dispatchForCurrentTenant(int $halfWindowMinutes = 15): array
    {
        $half = max(1, $halfWindowMinutes);

        return [
            'sent_72h' => $this->dispatchWindow(72 * 60, 'next_visit_reminded_72h_at', $half),
            'sent_24h' => $this->dispatchWindow(24 * 60, 'next_visit_reminded_24h_at', $half),
        ];
    }

    private function dispatchWindow(int $leadMinutes, string $flagColumn, int $halfWindowMinutes): int
    {
        $from = now()->addMinutes($leadMinutes - $halfWindowMinutes);
        $to = now()->addMinutes($leadMinutes + $halfWindowMinutes);
        $sent = 0;

        $appointments = Appointment::query()
            ->with(['client', 'location'])
            ->where('booking_source', Appointment::SOURCE_NEXT_VISIT)
            ->whereIn('status', [Appointment::STATUS_CONFIRMED, Appointment::STATUS_PENDING])
            ->whereNull($flagColumn)
            ->whereBetween('starts_at', [$from, $to])
            ->get();

        foreach ($appointments as $appointment) {
            if ($this->sendReminder($appointment, $leadMinutes, $flagColumn)) {
                $sent++;
            }
        }

        return $sent;
    }

    private function sendReminder(Appointment $appointment, int $leadMinutes, string $flagColumn): bool
    {
        $client = $appointment->client;
        if (! $client instanceof Client) {
            return false;
        }

        $tenant = $this->tenantContext->get();
        $timezone = $tenant?->timezone ?? config('app.timezone');
        $startsLabel = $appointment->starts_at instanceof Carbon
            ? $appointment->starts_at->copy()->timezone($timezone)->format('D j M Y H:i')
            : (string) $appointment->starts_at;

        $hours = (int) round($leadMinutes / 60);
        $title = $hours >= 48
            ? 'Reminder: next visit in 3 days'
            : 'Reminder: next visit tomorrow';
        $body = 'Your next visit is booked for '.$startsLabel.'.';

        $this->clientNotices->createForClient($client, [
            'type' => ClientNotice::TYPE_OPERATIONAL_IN_APP,
            'title' => $title,
            'body' => $body,
            'href' => '/member',
            'data' => [
                'appointment_id' => $appointment->id,
                'source' => 'next_visit_reminder',
                'lead_minutes' => $leadMinutes,
            ],
        ]);

        $waText = $title.' — '.$body;
        $deeplink = $tenant
            ? $this->threads->buildWaMeLinkForTenant($tenant, $waText)
            : null;

        $this->threads->postOutbound($client, [
            'channel' => $deeplink
                ? ClientThreadMessage::CHANNEL_WHATSAPP_MODE_A
                : ClientThreadMessage::CHANNEL_IN_APP,
            'subject' => $title,
            'body' => $body,
            'whatsapp_deeplink' => $deeplink,
            'metadata' => [
                'source' => 'next_visit_reminder',
                'appointment_id' => $appointment->id,
                'lead_minutes' => $leadMinutes,
                'mode' => 'whatsapp_mode_a',
                // Deeplink only — never sent via Cloud API.
                'whatsapp_deeplink' => $deeplink,
            ],
        ]);

        if (filled($client->email)) {
            try {
                $salon = $tenant?->trading_name ?: $tenant?->name ?: 'your salon';
                Mail::html(
                    '<p>Hi '.e($client->first_name ?: 'there').',</p>'
                    .'<p>'.e($body).'</p>'
                    .'<p>— '.e((string) $salon).'</p>',
                    function ($message) use ($client, $title) {
                        $message->to($client->email)->subject($title);
                    }
                );
            } catch (\Throwable) {
                // Email must not block reminder marking.
            }
        }

        $appointment->forceFill([
            $flagColumn => now(),
        ])->save();

        $this->auditLogger->log('next_visit.reminder_sent', $appointment, null, [
            'client_id' => $client->id,
            'lead_minutes' => $leadMinutes,
            'flag' => $flagColumn,
            'whatsapp_deeplink' => $deeplink,
        ]);

        return true;
    }
}
