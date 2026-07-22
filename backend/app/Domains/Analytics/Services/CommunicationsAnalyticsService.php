<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\DTOs\DateRange;
use App\Domains\Marketing\Enums\MarketingMessageStatus;
use App\Domains\Notifications\Enums\NotificationMessageStatus;
use Illuminate\Support\Facades\DB;

/**
 * Operational delivery analytics for Marketing and Notifications.
 *
 * Marketing message counts are anchored on marketing_messages.created_at.
 * Notification message counts are anchored on notifications_messages.created_at.
 */
class CommunicationsAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function report(string $tenantId, DateRange $range): array
    {
        return [
            'range' => $range->toArray(),
            'marketing' => $this->marketing($tenantId, $range),
            'notifications' => $this->notifications($tenantId, $range),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function marketing(string $tenantId, DateRange $range): array
    {
        $messages = DB::table('marketing_messages')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$range->from, $range->to]);

        $sent = (int) (clone $messages)->whereIn('status', [MarketingMessageStatus::SENT, MarketingMessageStatus::DELIVERED])->count();
        $failed = (int) (clone $messages)->where('status', MarketingMessageStatus::FAILED)->count();
        $suppressed = (int) (clone $messages)->where('status', MarketingMessageStatus::SUPPRESSED)->count();

        $executions = DB::table('marketing_workflow_executions')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$range->from, $range->to]);

        $executionCounts = (clone $executions)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $campaigns = (int) DB::table('marketing_campaigns')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$range->from, $range->to])
            ->count();

        return [
            'campaigns_count' => $campaigns,
            'messages_sent_count' => $sent,
            'messages_failed_count' => $failed,
            'messages_suppressed_count' => $suppressed,
            'workflow_executions_count' => (int) (clone $executions)->count(),
            'workflow_execution_status_breakdown' => $executionCounts->map(fn ($v, $k) => [
                'status' => $k,
                'total' => (int) $v,
            ])->values()->all(),
            'by_channel' => $this->marketingByChannel($tenantId, $range),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function notifications(string $tenantId, DateRange $range): array
    {
        $messages = DB::table('notifications_messages')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$range->from, $range->to]);

        $sent = (int) (clone $messages)->whereIn('status', NotificationMessageStatus::successful())->count();
        $failed = (int) (clone $messages)->where('status', NotificationMessageStatus::FAILED)->count();
        $suppressed = (int) (clone $messages)->where('status', NotificationMessageStatus::SUPPRESSED)->count();

        return [
            'messages_sent_count' => $sent,
            'messages_failed_count' => $failed,
            'messages_suppressed_count' => $suppressed,
            'by_channel' => $this->notificationsByChannel($tenantId, $range),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function marketingByChannel(string $tenantId, DateRange $range): array
    {
        return DB::table('marketing_messages')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$range->from, $range->to])
            ->selectRaw('channel, COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as sent", [MarketingMessageStatus::SENT, MarketingMessageStatus::DELIVERED])
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed", [MarketingMessageStatus::FAILED])
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as suppressed", [MarketingMessageStatus::SUPPRESSED])
            ->groupBy('channel')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'channel' => $row->channel,
                'total' => (int) $row->total,
                'sent' => (int) $row->sent,
                'failed' => (int) $row->failed,
                'suppressed' => (int) $row->suppressed,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function notificationsByChannel(string $tenantId, DateRange $range): array
    {
        $successful = NotificationMessageStatus::successful();

        return DB::table('notifications_messages')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$range->from, $range->to])
            ->selectRaw('channel, COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as sent', $successful)
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed", [NotificationMessageStatus::FAILED])
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as suppressed", [NotificationMessageStatus::SUPPRESSED])
            ->groupBy('channel')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'channel' => $row->channel,
                'total' => (int) $row->total,
                'sent' => (int) $row->sent,
                'failed' => (int) $row->failed,
                'suppressed' => (int) $row->suppressed,
            ])
            ->all();
    }
}
