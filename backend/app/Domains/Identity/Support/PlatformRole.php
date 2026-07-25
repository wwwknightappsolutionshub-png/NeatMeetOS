<?php

namespace App\Domains\Identity\Support;

final class PlatformRole
{
    public const OWNER = 'owner';

    public const MANAGER = 'manager';

    public const SUPPORT = 'support';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::OWNER, self::MANAGER, self::SUPPORT];
    }

    public static function isValid(?string $role): bool
    {
        return $role !== null && in_array($role, self::all(), true);
    }

    /**
     * Effective role for a platform user (legacy admins without a role are owners).
     */
    public static function effective(?bool $isPlatformAdmin, ?string $role): ?string
    {
        if (! $isPlatformAdmin) {
            return null;
        }

        return self::isValid($role) ? $role : self::OWNER;
    }

    public static function canManageStaff(?string $role): bool
    {
        return $role === self::OWNER;
    }

    public static function canWrite(?string $role): bool
    {
        return in_array($role, [self::OWNER, self::MANAGER], true);
    }

    public static function label(string $role): string
    {
        return match ($role) {
            self::OWNER => 'Owner',
            self::MANAGER => 'Manager',
            self::SUPPORT => 'Support',
            default => $role,
        };
    }

    public static function description(string $role): string
    {
        return match ($role) {
            self::OWNER => 'Full platform control, including staff and destructive actions.',
            self::MANAGER => 'Operate tenants, modules, and campaigns. Cannot manage platform staff.',
            self::SUPPORT => 'Read-only access to tenants, audit, and notifications.',
            default => '',
        };
    }
}
