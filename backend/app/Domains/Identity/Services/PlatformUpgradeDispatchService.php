<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\PlatformUpgradeSend;
use App\Domains\Identity\Models\PlatformUpgradeTemplate;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\TenantOwnerNotice;
use App\Domains\Identity\Models\TenantSubscription;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\PlatformUpgradeCatalogue;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Log;

class PlatformUpgradeDispatchService
{
    public function __construct(
        private readonly PlatformUpgradeCampaignService $campaigns,
        private readonly PlatformUpgradeMailService $mail,
        private readonly PlatformUpgradeDiscountService $discounts,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return array{sent: int, skipped: int}
     */
    public function dispatchDue(): array
    {
        $settings = $this->campaigns->settings();
        if (! $settings->is_enabled) {
            return ['sent' => 0, 'skipped' => 0];
        }

        $this->campaigns->ensureDefaultTemplates();

        $sent = 0;
        $skipped = 0;

        $tenants = Tenant::query()
            ->whereNotNull('activated_at')
            ->whereIn('status', ['active', 'trial'])
            ->with('subscriptionPlan')
            ->get();

        foreach ($tenants as $tenant) {
            $path = PlatformUpgradeCatalogue::pathForPlanSlug($tenant->subscriptionPlan?->slug);
            if ($path === null) {
                $skipped++;
                continue;
            }

            $owner = $this->resolveOwner($tenant);
            if ($owner === null) {
                $skipped++;
                continue;
            }

            $day = $tenant->activated_at
                ? (int) $tenant->activated_at->copy()->startOfDay()->diffInDays(now()->copy()->startOfDay())
                : -1;

            foreach ([3 => 'day_3', 7 => 'day_7', 21 => 'day_21'] as $offset => $step) {
                if ((int) $day !== $offset) {
                    continue;
                }
                $result = $this->dispatchStep($tenant, $owner, $path, $step, $settings->toArray());
                $sent += $result['sent'];
                $skipped += $result['skipped'];
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * Send the day-21 upgrade email (countdown + discount claim) for a usage trigger.
     * Uses the day_21 template content but records a distinct send step for idempotency.
     *
     * @param  array<string, mixed>  $meta
     * @return array{sent: int, skipped: int}
     */
    public function sendUsageDay21Offer(Tenant $tenant, string $trigger, array $meta = []): array
    {
        $settings = $this->campaigns->settings();
        if (! $settings->is_enabled || ! $settings->channel_email) {
            return ['sent' => 0, 'skipped' => 1];
        }

        $tenant->loadMissing('subscriptionPlan');
        $path = PlatformUpgradeCatalogue::pathForPlanSlug($tenant->subscriptionPlan?->slug);
        if ($path === null) {
            return ['sent' => 0, 'skipped' => 1];
        }

        $owner = $this->resolveOwner($tenant);
        if ($owner === null) {
            return ['sent' => 0, 'skipped' => 1];
        }

        if ($this->alreadySent($tenant->id, $path, $trigger, 'email')) {
            return ['sent' => 0, 'skipped' => 1];
        }

        $template = $this->campaigns->findActiveTemplate($path, 'day_21', 'email');
        if ($template === null) {
            return ['sent' => 0, 'skipped' => 1];
        }

        $this->tenantContext->set($tenant);
        $this->sendEmail(
            $tenant,
            $owner,
            $template,
            $path,
            'day_21',
            (int) $settings->discount_percent,
        );

        PlatformUpgradeSend::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'path' => $path,
                'step' => $trigger,
                'channel' => 'email',
            ],
            [
                'user_id' => $owner->id,
                'status' => 'sent',
                'recipient' => $owner->email,
                'payload' => array_merge([
                    'template_id' => $template->id,
                    'template_version' => $template->version,
                    'template_step' => 'day_21',
                ], $meta),
                'sent_at' => now(),
            ],
        );

        return ['sent' => 1, 'skipped' => 0];
    }

    /**
     * Force-send a step for testing / admin preview.
     *
     * @return array{sent: int, skipped: int, channels: list<string>}
     */
    public function dispatchForTenant(Tenant $tenant, string $step, bool $force = false): array
    {
        $settings = $this->campaigns->settings();
        $path = PlatformUpgradeCatalogue::pathForPlanSlug($tenant->subscriptionPlan?->slug);
        if ($path === null) {
            return ['sent' => 0, 'skipped' => 1, 'channels' => []];
        }
        $owner = $this->resolveOwner($tenant);
        if ($owner === null) {
            return ['sent' => 0, 'skipped' => 1, 'channels' => []];
        }

        return $this->dispatchStep($tenant, $owner, $path, $step, $settings->toArray(), $force);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{sent: int, skipped: int, channels: list<string>}
     */
    private function dispatchStep(
        Tenant $tenant,
        User $owner,
        string $path,
        string $step,
        array $settings,
        bool $force = false,
    ): array {
        // Day 3: WhatsApp when number present + in-app with image. Day 7/21: email.
        $channels = match ($step) {
            'day_3' => array_values(array_filter([
                ! empty($settings['channel_whatsapp']) && filled($tenant->owner_whatsapp) ? 'whatsapp' : null,
                ! empty($settings['channel_in_app']) ? 'in_app' : null,
            ])),
            'day_7', 'day_21' => ! empty($settings['channel_email']) ? ['email'] : [],
            default => [],
        };

        $sent = 0;
        $skipped = 0;
        $used = [];

        foreach ($channels as $channel) {
            if (! $force && $this->alreadySent($tenant->id, $path, $step, $channel)) {
                $skipped++;
                continue;
            }

            $template = $this->campaigns->findActiveTemplate($path, $step, $channel);
            if ($template === null) {
                $skipped++;
                continue;
            }

            $this->tenantContext->set($tenant);

            match ($channel) {
                'email' => $this->sendEmail($tenant, $owner, $template, $path, $step, (int) ($settings['discount_percent'] ?? 5)),
                'whatsapp' => $this->sendWhatsApp($tenant, $owner, $template, $path, $step),
                'in_app' => $this->sendInApp($tenant, $owner, $template, $path, $step),
                default => null,
            };

            $this->recordSend($tenant, $owner, $path, $step, $channel, $template);
            $sent++;
            $used[] = $channel;
        }

        return ['sent' => $sent, 'skipped' => $skipped, 'channels' => $used];
    }

    private function sendEmail(
        Tenant $tenant,
        User $owner,
        PlatformUpgradeTemplate $template,
        string $path,
        string $step,
        int $discountPercent,
    ): void {
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        if ($step === 'day_7') {
            $url = $frontend.'/admin/settings/subscription';
            $this->mail->sendDay7($owner, $tenant, $template, $path, $url);

            return;
        }

        $subscription = TenantSubscription::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->first();
        $trialEnds = $subscription?->trial_ends_at;
        $daysLeft = $trialEnds ? max(0, now()->startOfDay()->diffInDays($trialEnds->copy()->startOfDay(), false)) : 0;

        $issued = $this->discounts->issue($tenant, $owner, $path, $discountPercent, $trialEnds);
        $claimUrl = $frontend.'/upgrade-offer?token='.urlencode($issued['plain_token']);

        $this->mail->sendDay21(
            $owner,
            $tenant,
            $template,
            $path,
            $claimUrl,
            $discountPercent,
            $trialEnds,
            (int) $daysLeft,
        );
    }

    private function sendWhatsApp(
        Tenant $tenant,
        User $owner,
        PlatformUpgradeTemplate $template,
        string $path,
        string $step,
    ): void {
        $body = strtr((string) $template->body_text, [
            '{{salon_name}}' => $tenant->trading_name ?: $tenant->name,
            '{{owner_first_name}}' => trim(explode(' ', $owner->name)[0] ?? $owner->name),
        ]);

        // Provider-ready outbox: log until WhatsApp driver is configured.
        Log::info('platform.upgrade.whatsapp_queued', [
            'tenant_id' => $tenant->id,
            'to' => $tenant->owner_whatsapp,
            'path' => $path,
            'step' => $step,
            'body' => $body,
            'image' => $template->image_path,
        ]);
    }

    private function sendInApp(
        Tenant $tenant,
        User $owner,
        PlatformUpgradeTemplate $template,
        string $path,
        string $step,
    ): void {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $image = $template->image_path
            ? (str_starts_with((string) $template->image_path, 'http')
                ? $template->image_path
                : $frontend.$template->image_path)
            : null;

        $headline = strtr((string) ($template->headline ?? 'Upgrade available'), [
            '{{salon_name}}' => $tenant->trading_name ?: $tenant->name,
            '{{owner_first_name}}' => trim(explode(' ', $owner->name)[0] ?? $owner->name),
        ]);

        TenantOwnerNotice::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'type' => 'upgrade.'.$path.'.'.$step,
            'title' => $headline,
            'body' => (string) ($template->body_text ?? ''),
            'image_url' => $image,
            'href' => '/admin/settings/subscription',
            'data' => [
                'path' => $path,
                'step' => $step,
                'features' => $template->features,
                'use_cases' => $template->use_cases,
                'cta_label' => $template->cta_label,
            ],
        ]);
    }

    private function recordSend(
        Tenant $tenant,
        User $owner,
        string $path,
        string $step,
        string $channel,
        PlatformUpgradeTemplate $template,
    ): void {
        PlatformUpgradeSend::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'path' => $path,
                'step' => $step,
                'channel' => $channel,
            ],
            [
                'user_id' => $owner->id,
                'status' => 'sent',
                'recipient' => $channel === 'whatsapp'
                    ? $tenant->owner_whatsapp
                    : $owner->email,
                'payload' => [
                    'template_id' => $template->id,
                    'template_version' => $template->version,
                    'image_path' => $template->image_path,
                ],
                'sent_at' => now(),
            ],
        );
    }

    private function alreadySent(string $tenantId, string $path, string $step, string $channel): bool
    {
        return PlatformUpgradeSend::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('path', $path)
            ->where('step', $step)
            ->where('channel', $channel)
            ->exists();
    }

    private function resolveOwner(Tenant $tenant): ?User
    {
        $member = $tenant->teamMembers()
            ->withoutGlobalScopes()
            ->where('employment_type', \App\Domains\Identity\Models\TeamMember::EMPLOYMENT_OWNER)
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
