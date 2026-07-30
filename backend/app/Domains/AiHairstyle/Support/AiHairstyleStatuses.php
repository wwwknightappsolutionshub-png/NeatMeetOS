<?php

namespace App\Domains\AiHairstyle\Support;

/**
 * Canonical statuses and allowed transitions for AI Hairstyle sessions/previews.
 */
final class AiHairstyleStatuses
{
    public const SESSION_DRAFT = 'draft';

    public const SESSION_GENERATING = 'generating';

    public const SESSION_READY = 'ready';

    public const SESSION_FAILED = 'failed';

    public const SESSION_SELECTED = 'selected';

    public const SESSION_SUBMITTED = 'submitted';

    public const SESSION_ACCEPTED = 'accepted';

    public const SESSION_CANCELLED = 'cancelled';

    public const SESSION_EXPIRED = 'expired';

    public const PREVIEW_PENDING = 'pending';

    public const PREVIEW_READY = 'ready';

    public const PREVIEW_FAILED = 'failed';

    /**
     * @return list<string>
     */
    public static function sessionStatuses(): array
    {
        return [
            self::SESSION_DRAFT,
            self::SESSION_GENERATING,
            self::SESSION_READY,
            self::SESSION_FAILED,
            self::SESSION_SELECTED,
            self::SESSION_SUBMITTED,
            self::SESSION_ACCEPTED,
            self::SESSION_CANCELLED,
            self::SESSION_EXPIRED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function previewStatuses(): array
    {
        return [
            self::PREVIEW_PENDING,
            self::PREVIEW_READY,
            self::PREVIEW_FAILED,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function sessionTransitions(): array
    {
        return [
            self::SESSION_DRAFT => [
                self::SESSION_GENERATING,
                self::SESSION_CANCELLED,
                self::SESSION_EXPIRED,
            ],
            self::SESSION_GENERATING => [
                self::SESSION_READY,
                self::SESSION_FAILED,
                self::SESSION_CANCELLED,
                self::SESSION_EXPIRED,
            ],
            self::SESSION_FAILED => [
                self::SESSION_GENERATING,
                self::SESSION_CANCELLED,
                self::SESSION_EXPIRED,
            ],
            self::SESSION_READY => [
                self::SESSION_SELECTED,
                self::SESSION_GENERATING,
                self::SESSION_CANCELLED,
                self::SESSION_EXPIRED,
            ],
            self::SESSION_SELECTED => [
                self::SESSION_SUBMITTED,
                self::SESSION_READY,
                self::SESSION_GENERATING,
                self::SESSION_CANCELLED,
                self::SESSION_EXPIRED,
            ],
            self::SESSION_SUBMITTED => [
                self::SESSION_ACCEPTED,
                self::SESSION_CANCELLED,
            ],
            self::SESSION_ACCEPTED => [],
            self::SESSION_CANCELLED => [],
            self::SESSION_EXPIRED => [],
        ];
    }

    public static function canTransitionSession(string $from, string $to): bool
    {
        $allowed = self::sessionTransitions()[$from] ?? [];

        return in_array($to, $allowed, true);
    }
}
