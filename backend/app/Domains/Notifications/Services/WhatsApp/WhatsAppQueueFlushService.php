<?php

namespace App\Domains\Notifications\Services\WhatsApp;

use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationMessageStatus;
use App\Domains\Notifications\Models\NotificationMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inspect and purge stale WhatsApp queue jobs + failed/queued notification messages.
 */
class WhatsAppQueueFlushService
{
    /** @var list<string> */
    public const QUEUE_MARKERS = [
        'DispatchNotificationMessageJob',
        'SendTenantSignupWelcomeWhatsAppJob',
    ];

    /**
     * @return array{
     *     pending_jobs: int,
     *     reserved_jobs: int,
     *     failed_jobs: int,
     *     stale_messages: int,
     *     markers: list<string>
     * }
     */
    public function status(?int $olderThanHours = 1): array
    {
        $hours = max(0, $olderThanHours ?? 1);

        return [
            'pending_jobs' => $this->jobsCount(reserved: false),
            'reserved_jobs' => $this->jobsCount(reserved: true),
            'failed_jobs' => $this->failedJobsCount(),
            'stale_messages' => $this->staleMessagesQuery($hours)->count(),
            'markers' => self::QUEUE_MARKERS,
            'older_than_hours' => $hours,
        ];
    }

    /**
     * @return array{
     *     deleted_jobs: int,
     *     deleted_failed_jobs: int,
     *     cancelled_messages: int,
     *     before: array<string, mixed>
     * }
     */
    public function flush(
        bool $includeFailedJobs = true,
        bool $includeStaleMessages = true,
        ?int $olderThanHours = 1,
    ): array {
        $hours = max(0, $olderThanHours ?? 1);
        $before = $this->status($hours);

        $deletedJobs = 0;
        $deletedFailed = 0;
        if (Schema::hasTable('jobs')) {
            $deletedJobs = $this->matchingJobsQuery()->delete();
        }
        if ($includeFailedJobs && Schema::hasTable('failed_jobs')) {
            $deletedFailed = $this->matchingFailedJobsQuery()->delete();
        }

        $cancelledMessages = 0;
        if ($includeStaleMessages) {
            $cancelledMessages = $this->staleMessagesQuery($hours)->update([
                'status' => NotificationMessageStatus::CANCELLED,
                'cancelled_at' => now(),
                'failure_reason' => 'Purged by platform WhatsApp stale-message cleanup.',
                'updated_at' => now(),
            ]);
        }

        return [
            'deleted_jobs' => $deletedJobs,
            'deleted_failed_jobs' => $deletedFailed,
            'cancelled_messages' => $cancelledMessages,
            'before' => $before,
            'include_failed_jobs' => $includeFailedJobs,
            'include_stale_messages' => $includeStaleMessages,
            'older_than_hours' => $hours,
        ];
    }

    private function jobsCount(bool $reserved): int
    {
        if (! Schema::hasTable('jobs')) {
            return 0;
        }

        $query = $this->matchingJobsQuery();

        return $reserved
            ? (clone $query)->whereNotNull('reserved_at')->count()
            : (clone $query)->whereNull('reserved_at')->count();
    }

    private function failedJobsCount(): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return $this->matchingFailedJobsQuery()->count();
    }

    private function matchingJobsQuery()
    {
        $query = DB::table('jobs');
        $query->where(function ($outer) {
            foreach (self::QUEUE_MARKERS as $marker) {
                $outer->orWhere('payload', 'like', '%'.$marker.'%');
            }
        });

        return $query;
    }

    private function matchingFailedJobsQuery()
    {
        $query = DB::table('failed_jobs');
        $query->where(function ($outer) {
            foreach (self::QUEUE_MARKERS as $marker) {
                $outer->orWhere('payload', 'like', '%'.$marker.'%');
            }
        });

        return $query;
    }

    private function staleMessagesQuery(int $olderThanHours)
    {
        return NotificationMessage::withoutGlobalScopes()
            ->where('channel', NotificationChannel::WHATSAPP)
            ->whereIn('status', [
                NotificationMessageStatus::QUEUED,
                NotificationMessageStatus::PROCESSING,
                NotificationMessageStatus::FAILED,
            ])
            ->where('created_at', '<=', now()->subHours($olderThanHours));
    }
}
