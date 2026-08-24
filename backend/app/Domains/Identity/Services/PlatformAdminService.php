<?php

namespace App\Domains\Identity\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\TenantOwnerPushSubscription;
use App\Domains\Identity\Models\TenantSubscription;
use App\Domains\Identity\Models\User;
use App\Domains\Payments\Enums\PaymentTransactionStatus;
use App\Domains\Payments\Models\PaymentTransaction;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cross-tenant platform admin operations (read models + support tooling).
 */
class PlatformAdminService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly TenantPresenceService $presence,
    ) {}

    /**
     * @return array{
     *     tenants_total: int,
     *     tenants_active: int,
     *     tenants_trial: int,
     *     tenants_suspended: int,
     *     users_total: int,
     *     team_members_total: int,
     *     clients_total: int,
     *     appointments_last_7d: int,
     *     payments_collected_last_7d_cents: int
     * }
     */
    public function overview(): array
    {
        $since = now()->subDays(7);

        $statusCounts = Tenant::query()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $paymentsCents = 0;
        if (class_exists(PaymentTransaction::class)) {
            $paymentsCents = (int) PaymentTransaction::withoutGlobalScopes()
                ->where('created_at', '>=', $since)
                ->where('status', PaymentTransactionStatus::SUCCEEDED)
                ->sum('amount_cents');
        }

        $appointments = 0;
        if (class_exists(Appointment::class)) {
            $appointments = (int) Appointment::withoutGlobalScopes()
                ->where('starts_at', '>=', $since)
                ->count();
        }

        $clients = 0;
        if (class_exists(Client::class)) {
            $clients = (int) Client::withoutGlobalScopes()->count();
        }

        return [
            'tenants_total' => (int) Tenant::query()->count(),
            'tenants_active' => (int) ($statusCounts['active'] ?? 0),
            'tenants_trial' => (int) ($statusCounts['trial'] ?? 0),
            'tenants_suspended' => (int) (($statusCounts['suspended'] ?? 0) + ($statusCounts['inactive'] ?? 0)),
            'users_total' => (int) User::query()->count(),
            'team_members_total' => (int) TeamMember::withoutGlobalScopes()->count(),
            'clients_total' => $clients,
            'appointments_last_7d' => $appointments,
            'payments_collected_last_7d_cents' => $paymentsCents,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listTenants(?string $search = null, ?string $status = null): array
    {
        $query = Tenant::query()
            ->with(['subscriptionPlan:id,name,slug'])
            ->orderBy('name');

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('trading_name', 'like', $term)
                    ->orWhere('slug', 'like', $term)
                    ->orWhere('contact_email', 'like', $term);
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        return $query->limit(200)->get()->map(function (Tenant $tenant) {
            $staffCount = TeamMember::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->count();

            $subscription = $tenant->subscription()->withoutGlobalScopes()->first();
            $presence = $this->presence->presencePayload($tenant);
            $pwaCount = TenantOwnerPushSubscription::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->count();
            $owner = $this->resolveOwnerUser($tenant);

            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'trading_name' => $tenant->trading_name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'suspended_at' => $tenant->suspended_at?->toIso8601String(),
                'suspension_reason' => $tenant->suspension_reason,
                'business_type' => $tenant->business_type,
                'timezone' => $tenant->timezone,
                'contact_email' => $tenant->contact_email,
                'owner_email' => $owner?->email ?? $tenant->contact_email,
                'owner_whatsapp' => $tenant->owner_whatsapp,
                'plan_name' => $tenant->subscriptionPlan?->name,
                'plan_slug' => $tenant->subscriptionPlan?->slug,
                'desired_plan_slug' => $subscription?->desired_plan_slug,
                'tier_unlocked' => (bool) ($subscription?->tier_unlocked ?? false),
                'subscription_status' => $subscription?->status,
                'trial_ends_at' => $subscription?->trial_ends_at?->toIso8601String(),
                'staff_count' => $staffCount,
                'created_at' => $tenant->created_at?->toIso8601String(),
                'presence' => $presence['presence'],
                'online' => $presence['online'],
                'admin_last_seen_at' => $presence['admin_last_seen_at'],
                'pwa_subscribers' => $pwaCount,
            ];
        })->all();
    }

    /**
     * Change the salon owner's login email and keep tenant contact_email in sync.
     *
     * @return array{tenant_id: string, owner_email: string, contact_email: string, owner_user_id: string}
     */
    public function updateTenantOwnerEmail(Tenant $tenant, string $email): array
    {
        $email = strtolower(trim($email));

        return DB::transaction(function () use ($tenant, $email) {
            $owner = $this->resolveOwnerUser($tenant);
            if ($owner === null) {
                throw ValidationException::withMessages([
                    'email' => ['No owner user is linked to this tenant.'],
                ]);
            }

            $taken = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where('id', '!=', $owner->id)
                ->exists();
            if ($taken) {
                throw ValidationException::withMessages([
                    'email' => ['That email is already used by another account.'],
                ]);
            }

            $old = [
                'owner_email' => $owner->email,
                'contact_email' => $tenant->contact_email,
            ];

            if (strcasecmp((string) $owner->email, $email) !== 0) {
                $owner->email = $email;
                $owner->email_verified_at = null;
                $meta = is_array($owner->signup_meta) ? $owner->signup_meta : [];
                if (isset($meta['answers']) && is_array($meta['answers'])) {
                    $meta['answers']['owner_email'] = $email;
                    $owner->signup_meta = $meta;
                }
                $owner->save();
            }

            $tenant->contact_email = $email;
            $tenant->save();

            $this->auditLogger->log('platform.tenant.owner_email_updated', $tenant, $old, [
                'owner_email' => $email,
                'contact_email' => $email,
                'owner_user_id' => $owner->id,
            ]);

            return [
                'tenant_id' => $tenant->id,
                'owner_email' => $email,
                'contact_email' => $email,
                'owner_user_id' => (string) $owner->id,
            ];
        });
    }

    private function resolveOwnerUser(Tenant $tenant): ?User
    {
        $member = TeamMember::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('employment_type', TeamMember::EMPLOYMENT_OWNER)
            ->where('is_active', true)
            ->whereNotNull('user_id')
            ->orderBy('created_at')
            ->first();

        if ($member?->user_id) {
            return User::query()->find($member->user_id);
        }

        if (filled($tenant->contact_email)) {
            return User::query()->whereRaw('LOWER(email) = ?', [strtolower((string) $tenant->contact_email)])->first();
        }

        return null;
    }

    public function suspendTenant(Tenant $tenant, ?string $reason = null): Tenant
    {
        return DB::transaction(function () use ($tenant, $reason) {
            $old = $tenant->only(['status', 'suspended_at', 'suspension_reason']);

            $tenant->status = 'suspended';
            $tenant->suspended_at = now();
            $tenant->suspension_reason = $reason ?? 'Suspended by platform admin.';
            $tenant->save();

            $subscription = TenantSubscription::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->first();
            if ($subscription !== null && $subscription->status !== TenantSubscription::STATUS_CANCELED) {
                $subscription->status = TenantSubscription::STATUS_SUSPENDED;
                $subscription->save();
            }

            $this->auditLogger->log('platform.tenant.suspended', $tenant, $old, [
                'status' => $tenant->status,
                'suspension_reason' => $tenant->suspension_reason,
            ]);

            return $tenant->fresh();
        });
    }

    public function unsuspendTenant(Tenant $tenant): Tenant
    {
        return DB::transaction(function () use ($tenant) {
            $old = $tenant->only(['status', 'suspended_at', 'suspension_reason']);

            $tenant->status = 'active';
            $tenant->suspended_at = null;
            $tenant->suspension_reason = null;
            $tenant->save();

            $subscription = TenantSubscription::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->first();
            if ($subscription !== null && $subscription->status === TenantSubscription::STATUS_SUSPENDED) {
                $subscription->status = TenantSubscription::STATUS_ACTIVE;
                $subscription->save();
            }

            $this->auditLogger->log('platform.tenant.unsuspended', $tenant, $old, [
                'status' => $tenant->status,
            ]);

            return $tenant->fresh();
        });
    }

    /**
     * Issue a short-lived Sanctum token for a tenant owner/admin user (support impersonation).
     *
     * @return array{token: string, expires_at: string, user: array{id: string, name: string, email: string}, tenant: array{id: string, name: string, slug: string}, impersonated_by: string}
     */
    public function impersonateTenant(Tenant $tenant, User $actor, ?string $userId = null): array
    {
        if ($tenant->status === 'suspended') {
            throw ValidationException::withMessages([
                'tenant' => ['Cannot impersonate a suspended tenant.'],
            ]);
        }

        $memberQuery = TeamMember::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereNotNull('user_id')
            ->orderByRaw("CASE WHEN employment_type = ? THEN 0 ELSE 1 END", [TeamMember::EMPLOYMENT_OWNER]);

        if ($userId !== null) {
            $memberQuery->where('user_id', $userId);
        }

        $member = $memberQuery->first();
        if ($member === null) {
            throw ValidationException::withMessages([
                'tenant' => ['No active user is available to impersonate for this tenant.'],
            ]);
        }

        $user = User::query()->findOrFail($member->user_id);

        $token = $user->createToken(
            'platform-impersonation',
            ['*'],
            now()->addHours(2),
        );

        $this->auditLogger->log('platform.tenant.impersonated', $tenant, null, [
            'impersonated_user_id' => $user->id,
            'impersonated_team_member_id' => $member->id,
            'impersonated_by' => $actor->id,
            'token_name' => 'platform-impersonation',
        ], $actor);

        return [
            'token' => $token->plainTextToken,
            'expires_at' => now()->addHours(2)->toIso8601String(),
            'user' => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'impersonated_by' => (string) $actor->id,
        ];
    }
}
