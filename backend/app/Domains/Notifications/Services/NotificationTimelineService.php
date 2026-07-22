<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Notifications\Models\NotificationMessage;
use Illuminate\Support\Carbon;

/**
 * Normalized operational communication timeline for a client.
 *
 * In 11A this reads exclusively from notifications_messages. It is intentionally
 * shaped so marketing messages (or other sources) can be merged in later without
 * changing the consumer contract.
 */
class NotificationTimelineService
{
    public function __construct(
        private readonly NotificationScopeValidator $scope,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function forClient(Client $client, array $filters = [], int $limit = 100): array
    {
        $this->scope->assertTenantModel($client);

        $query = NotificationMessage::query()
            ->where('client_id', $client->id)
            ->orderByDesc('created_at');

        if (! empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }
        if (! empty($filters['purpose'])) {
            $query->where('purpose', $filters['purpose']);
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        return $query->limit($limit)->get()->map(fn (NotificationMessage $m) => [
            'id' => $m->id,
            'source' => 'notification',
            'source_type' => $m->source_type,
            'purpose' => $m->purpose,
            'channel' => $m->channel,
            'direction' => $m->direction,
            'status' => $m->status,
            'subject' => $m->subject,
            'preview' => $m->body_text ? mb_substr($m->body_text, 0, 160) : null,
            'recipient_address' => $m->recipient_address,
            'occurred_at' => ($m->sent_at ?? $m->created_at)?->toIso8601String(),
            'created_at' => $m->created_at?->toIso8601String(),
        ])->all();
    }
}
