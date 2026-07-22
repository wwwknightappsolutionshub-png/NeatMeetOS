<?php

namespace App\Domains\Analytics\DTOs;

use Illuminate\Support\Carbon;

/**
 * Immutable resolved reporting window used by every analytics service.
 *
 * `from` is always the start of a day and `to` the end of a day so that
 * whereBetween filters are inclusive of the boundary dates.
 */
final class DateRange
{
    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
    ) {}

    /**
     * @return array{from: string, to: string, days: int}
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from->toIso8601String(),
            'to' => $this->to->toIso8601String(),
            'days' => $this->from->copy()->startOfDay()->diffInDays($this->to->copy()->startOfDay()) + 1,
        ];
    }
}
