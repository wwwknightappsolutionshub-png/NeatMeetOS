<?php

namespace App\Domains\Analytics\Enums;

final class AnalyticsReportType
{
    public const OVERVIEW = 'overview';

    public const BOOKINGS = 'bookings';

    public const REVENUE = 'revenue';

    public const CLIENTS = 'clients';

    public const INVENTORY = 'inventory';

    public const COMMUNICATIONS = 'communications';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::OVERVIEW,
            self::BOOKINGS,
            self::REVENUE,
            self::CLIENTS,
            self::INVENTORY,
            self::COMMUNICATIONS,
        ];
    }

    /**
     * Report types that accept a provider_id filter.
     *
     * @return array<int, string>
     */
    public static function providerScoped(): array
    {
        return [self::OVERVIEW, self::BOOKINGS, self::REVENUE];
    }

    /**
     * Report types that accept a location_id filter.
     *
     * @return array<int, string>
     */
    public static function locationScoped(): array
    {
        return [self::OVERVIEW, self::BOOKINGS, self::REVENUE, self::CLIENTS, self::INVENTORY];
    }
}
