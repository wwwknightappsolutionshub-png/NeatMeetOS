<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationMessageStatus;
use App\Domains\Notifications\Models\NotificationMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NotificationReportingService
{
    public function __construct(
        private readonly NotificationScopeValidator $scope,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $tenantId = $this->scope->tenantId();
        $from = $from ?? now()->subDays(30);
        $to = $to ?? now();

        $byStatus = NotificationMessage::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $byChannel = NotificationMessage::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->select('channel', DB::raw('COUNT(*) as total'))
            ->groupBy('channel')
            ->pluck('total', 'channel')
            ->all();

        $total = array_sum($byStatus);
        $successful = 0;
        foreach (NotificationMessageStatus::successful() as $status) {
            $successful += (int) ($byStatus[$status] ?? 0);
        }

        return [
            'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
            'total' => $total,
            'successful' => $successful,
            'failed' => (int) ($byStatus[NotificationMessageStatus::FAILED] ?? 0),
            'suppressed' => (int) ($byStatus[NotificationMessageStatus::SUPPRESSED] ?? 0),
            'by_status' => $this->normalise($byStatus, NotificationMessageStatus::all()),
            'by_channel' => $this->normalise($byChannel, NotificationChannel::all()),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function failures(?Carbon $from = null, ?Carbon $to = null): array
    {
        $tenantId = $this->scope->tenantId();
        $from = $from ?? now()->subDays(30);
        $to = $to ?? now();

        return NotificationMessage::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [NotificationMessageStatus::FAILED, NotificationMessageStatus::SUPPRESSED])
            ->whereBetween('created_at', [$from, $to])
            ->with('client:id,first_name,last_name,display_name')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (NotificationMessage $m) => [
                'message_id' => $m->id,
                'client_id' => $m->client_id,
                'client_name' => $m->client?->resolvedDisplayName(),
                'channel' => $m->channel,
                'purpose' => $m->purpose,
                'status' => $m->status,
                'recipient_address' => $m->recipient_address,
                'failure_reason' => $m->failure_reason,
                'failed_at' => $m->failed_at?->toIso8601String(),
                'created_at' => $m->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function byPurpose(?Carbon $from = null, ?Carbon $to = null): array
    {
        $tenantId = $this->scope->tenantId();
        $from = $from ?? now()->subDays(30);
        $to = $to ?? now();

        return NotificationMessage::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->select('purpose', 'status', DB::raw('COUNT(*) as total'))
            ->groupBy('purpose', 'status')
            ->get()
            ->groupBy('purpose')
            ->map(fn ($rows, $purpose) => [
                'purpose' => $purpose,
                'total' => (int) $rows->sum('total'),
                'by_status' => $rows->pluck('total', 'status')->map(fn ($v) => (int) $v)->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<int, string>  $keys
     * @return array<string, int>
     */
    private function normalise(array $counts, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = (int) ($counts[$key] ?? 0);
        }

        return $result;
    }
}
