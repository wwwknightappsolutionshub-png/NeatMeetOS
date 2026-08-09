<?php

namespace App\Domains\Booking\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Signals that the tenant booking day board should refresh for a given date.
 */
class BookingBoardUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $date,
        public readonly ?string $locationId = null,
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.'.$this->tenantId.'.booking-board'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'BookingBoardUpdated';
    }

    /**
     * @return array{date: string, location_id: string|null}
     */
    public function broadcastWith(): array
    {
        return [
            'date' => $this->date,
            'location_id' => $this->locationId,
        ];
    }
}
