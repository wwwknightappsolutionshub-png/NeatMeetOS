<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\PlatformNotification;
use App\Domains\Identity\Models\PlatformNotificationRead;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Collection;

class PlatformNotificationService
{
    public function __construct(
        private readonly AuthMailService $mail,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function notifyTenantSignup(Tenant $tenant, User $owner): PlatformNotification
    {
        $desired = data_get($tenant->settings, 'signup.desired_plan_slug')
            ?? $tenant->subscription()->withoutGlobalScopes()->value('desired_plan_slug')
            ?? 'basic';

        $notification = PlatformNotification::query()->create([
            'type' => PlatformNotification::TYPE_TENANT_SIGNUP,
            'title' => 'New salon signup',
            'body' => sprintf(
                '%s (%s) registered and is pending activation. Owner: %s · Desired plan: %s',
                $tenant->trading_name ?: $tenant->name,
                $tenant->slug,
                $owner->email,
                $desired,
            ),
            'data' => [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'tenant_name' => $tenant->trading_name ?: $tenant->name,
                'owner_email' => $owner->email,
                'owner_whatsapp' => $tenant->owner_whatsapp,
                'desired_plan_slug' => $desired,
                'status' => $tenant->status,
                'href' => '/platform/tenants',
            ],
        ]);

        $this->mail->sendPlatformTenantSignup($this->platformAdmins(), $tenant, $owner, (string) $desired);

        return $notification;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(User $user, int $limit = 40): array
    {
        $reads = PlatformNotificationRead::query()
            ->where('user_id', $user->id)
            ->pluck('read_at', 'platform_notification_id');

        return PlatformNotification::query()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (PlatformNotification $n) => $this->serialize($n, $reads->get($n->id)))
            ->all();
    }

    public function unreadCount(User $user): int
    {
        $readIds = PlatformNotificationRead::query()
            ->where('user_id', $user->id)
            ->pluck('platform_notification_id');

        return PlatformNotification::query()
            ->when($readIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $readIds))
            ->count();
    }

    public function markRead(User $user, string $notificationId): void
    {
        $notification = PlatformNotification::query()->findOrFail($notificationId);

        PlatformNotificationRead::query()->updateOrCreate(
            [
                'platform_notification_id' => $notification->id,
                'user_id' => $user->id,
            ],
            ['read_at' => now()],
        );
    }

    public function markAllRead(User $user): int
    {
        $unread = PlatformNotification::query()
            ->whereNotIn('id', PlatformNotificationRead::query()
                ->where('user_id', $user->id)
                ->select('platform_notification_id'))
            ->get(['id']);

        $count = 0;
        foreach ($unread as $notification) {
            PlatformNotificationRead::query()->updateOrCreate(
                [
                    'platform_notification_id' => $notification->id,
                    'user_id' => $user->id,
                ],
                ['read_at' => now()],
            );
            $count++;
        }

        return $count;
    }

    /**
     * @return Collection<int, User>
     */
    private function platformAdmins(): Collection
    {
        return User::query()
            ->where('is_platform_admin', true)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PlatformNotification $n, mixed $readAt): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'body' => $n->body,
            'data' => $n->data,
            'read_at' => $readAt ? (is_string($readAt) ? $readAt : $readAt->toIso8601String()) : null,
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }
}
