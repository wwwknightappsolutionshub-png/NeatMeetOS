<?php

namespace App\Domains\Identity\Services;

use App\Domains\Crm\Services\MemberPushDispatchService;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\TenantOwnerNotice;
use App\Domains\Identity\Models\TenantOwnerPushSubscription;
use App\Domains\Identity\Models\User;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Platform owner → tenant owner broadcasts (in-app + email + Web Push when subscribed).
 */
class PlatformTenantBroadcastService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly MemberPushDispatchService $push,
    ) {}

    /**
     * @param  array{title: string, body: string, href?: string|null, tenant_id?: string|null, send_email?: bool, send_push?: bool}  $payload
     * @return array{tenants: int, notices: int, emails: int, pushes: int}
     */
    public function broadcast(array $payload): array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $body = trim((string) ($payload['body'] ?? ''));
        $href = $payload['href'] ?? '/admin/dashboard';
        $sendEmail = (bool) ($payload['send_email'] ?? true);
        $sendPush = (bool) ($payload['send_push'] ?? true);

        $query = Tenant::query()->where('status', 'active')->orderBy('name');
        if (! empty($payload['tenant_id'])) {
            $query->where('id', $payload['tenant_id']);
        }

        $tenants = $query->get();
        $notices = 0;
        $emails = 0;
        $pushes = 0;

        foreach ($tenants as $tenant) {
            $owner = $this->resolveOwner($tenant);
            if ($owner === null) {
                continue;
            }

            TenantOwnerNotice::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $owner->id,
                'type' => 'platform.broadcast',
                'title' => $title,
                'body' => $body,
                'href' => is_string($href) ? $href : '/admin/dashboard',
                'data' => [
                    'source' => 'platform_broadcast',
                    'sent_at' => now()->toIso8601String(),
                ],
            ]);
            $notices++;

            if ($sendEmail && filled($owner->email)) {
                try {
                    Mail::raw($body, function ($message) use ($owner, $title) {
                        $message->to($owner->email)->subject($title);
                    });
                    $emails++;
                } catch (\Throwable $e) {
                    Log::warning('Platform broadcast email failed', [
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($sendPush) {
                $subs = TenantOwnerPushSubscription::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('user_id', $owner->id)
                    ->get()
                    ->all();

                $result = $this->push->sendToSubscriptions($subs, [
                    'title' => $title,
                    'body' => $body,
                    'url' => is_string($href) ? $href : '/admin/dashboard',
                    'data' => ['source' => 'platform_broadcast'],
                ], 'owner_push.dispatched');
                $pushes += (int) ($result['sent'] ?? 0);
            }
        }

        $this->auditLogger->log('platform.tenant_broadcast', null, null, [
            'title' => $title,
            'tenant_id' => $payload['tenant_id'] ?? null,
            'tenants' => $tenants->count(),
            'notices' => $notices,
            'emails' => $emails,
            'pushes' => $pushes,
        ]);

        return [
            'tenants' => $tenants->count(),
            'notices' => $notices,
            'emails' => $emails,
            'pushes' => $pushes,
        ];
    }

    private function resolveOwner(Tenant $tenant): ?User
    {
        $member = TeamMember::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('employment_type', TeamMember::EMPLOYMENT_OWNER)
            ->where('is_active', true)
            ->orderBy('created_at')
            ->first();

        if ($member?->user_id) {
            return User::query()->find($member->user_id);
        }

        if ($tenant->contact_email) {
            return User::query()->where('email', $tenant->contact_email)->first();
        }

        return null;
    }
}
