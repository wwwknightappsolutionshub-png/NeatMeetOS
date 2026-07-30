<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\SubscriptionPlan;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\TenantModuleOverride;
use App\Domains\Identity\Support\PlatformModuleCatalogue;
use App\Domains\Identity\Support\ProgressiveModuleAccess;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantEntitlementService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ProgressiveModuleAccessService $progressive,
    ) {}

    /**
     * @return array<string, bool>
     */
    public function resolveFeatures(?Tenant $tenant): array
    {
        if ($tenant === null) {
            return array_fill_keys(PlatformModuleCatalogue::keys(), false);
        }

        $plan = $tenant->subscriptionPlan
            ?? SubscriptionPlan::query()->find($tenant->subscription_plan_id);

        $base = $this->featuresForPlan($plan);

        $overrides = TenantModuleOverride::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('module_key');

        $overrideKeys = [];
        foreach (PlatformModuleCatalogue::keys() as $key) {
            if ($overrides->has($key)) {
                $base[$key] = (bool) $overrides->get($key)->enabled;
                $overrideKeys[] = $key;
            }
        }

        $features = $this->progressive->applyToFeatures($tenant, $base, $overrideKeys);

        return $this->applyAiHairstyleGate($tenant, $features, $overrides);
    }

    /**
     * AI Hairstyle is never plan-included. Effective only when:
     * platform force-on override + eligible business type + active module trial.
     *
     * @param  array<string, bool>  $features
     * @param  \Illuminate\Support\Collection<string, TenantModuleOverride>  $overrides
     * @return array<string, bool>
     */
    private function applyAiHairstyleGate(Tenant $tenant, array $features, $overrides): array
    {
        $overrideOn = $overrides->has('ai_hairstyle')
            && (bool) $overrides->get('ai_hairstyle')->enabled;

        $features['ai_hairstyle'] = $overrideOn
            && ProgressiveModuleAccess::isAiHairstyleEligible($tenant->business_type)
            && $this->isAiHairstyleTrialActive($tenant);

        return $features;
    }

    public function isAiHairstyleTrialActive(Tenant $tenant): bool
    {
        $endsAt = $tenant->ai_hairstyle_trial_ends_at;

        return $endsAt !== null && $endsAt->isFuture();
    }

    /**
     * Start or renew the 30-day AI Hairstyle trial when a platform admin force-enables.
     * Keeps an existing future end date so mid-trial re-saves do not reset the clock.
     */
    public function ensureAiHairstyleTrialWindow(Tenant $tenant): void
    {
        $endsAt = $tenant->ai_hairstyle_trial_ends_at;
        if ($endsAt !== null && $endsAt->isFuture()) {
            return;
        }

        $tenant->forceFill([
            'ai_hairstyle_trial_ends_at' => now()->addDays(ProgressiveModuleAccess::AI_HAIRSTYLE_TRIAL_DAYS),
        ])->save();
    }

    public function isEnabled(?Tenant $tenant, string $moduleKey): bool
    {
        if ($moduleKey === 'booking_board') {
            $features = $this->resolveFeatures($tenant);

            return (bool) ($features['booking_board'] ?? $features['booking'] ?? false);
        }

        if (! PlatformModuleCatalogue::isValid($moduleKey)) {
            return true;
        }

        $features = $this->resolveFeatures($tenant);

        return (bool) ($features[$moduleKey] ?? false);
    }

    /**
     * Plans (basic/pro/diamond) that currently include this module.
     *
     * @return list<array{slug: string, name: string}>
     */
    public function plansThatInclude(string $moduleKey): array
    {
        if (! PlatformModuleCatalogue::isValid($moduleKey)) {
            return [];
        }

        return SubscriptionPlan::query()
            ->whereIn('slug', ['basic', 'pro', 'diamond'])
            ->orderByRaw("CASE slug WHEN 'basic' THEN 1 WHEN 'pro' THEN 2 WHEN 'diamond' THEN 3 ELSE 9 END")
            ->get()
            ->filter(fn (SubscriptionPlan $plan) => (bool) ($this->featuresForPlan($plan)[$moduleKey] ?? false))
            ->map(fn (SubscriptionPlan $plan) => [
                'slug' => $plan->slug,
                'name' => $plan->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Payload for upgrade-required API / UI when a module is locked.
     *
     * @return array{
     *     code: string,
     *     module: string,
     *     module_label: string,
     *     available_on: list<array{slug: string, name: string}>,
     *     suggested_plan_slug: string|null,
     *     upgrade_href: string
     * }
     */
    public function upgradeRequiredPayload(string $moduleKey): array
    {
        $label = collect(PlatformModuleCatalogue::all())
            ->firstWhere('key', $moduleKey)['label'] ?? $moduleKey;

        $availableOn = $this->plansThatInclude($moduleKey);
        $suggested = $availableOn[0]['slug'] ?? 'pro';

        return [
            'code' => 'module_upgrade_required',
            'module' => $moduleKey,
            'module_label' => $label,
            'available_on' => $availableOn,
            'suggested_plan_slug' => $suggested,
            'upgrade_href' => '/admin/settings/subscription',
        ];
    }

    /**
     * Locked modules for the current tenant (for shell / marketing UI).
     *
     * @return list<array{
     *     module: string,
     *     module_label: string,
     *     available_on: list<array{slug: string, name: string}>,
     *     suggested_plan_slug: string|null,
     *     upgrade_href: string
     * }>
     */
    public function lockedModuleHints(?Tenant $tenant): array
    {
        $features = $this->resolveFeatures($tenant);
        $hints = [];

        foreach (PlatformModuleCatalogue::all() as $mod) {
            $key = $mod['key'];
            if ($features[$key] ?? false) {
                continue;
            }
            $payload = $this->upgradeRequiredPayload($key);
            unset($payload['code']);
            $hints[] = $payload;
        }

        // Synthetic board lock while service catalogue remains available.
        if (($features['booking'] ?? false) && ! ($features['booking_board'] ?? true)) {
            $hints[] = [
                'module' => 'booking_board',
                'module_label' => 'Booking board',
                'available_on' => $this->plansThatInclude('booking'),
                'suggested_plan_slug' => 'pro',
                'upgrade_href' => '/admin/settings/subscription',
            ];
        }

        return $hints;
    }

    /**
     * @return array<string, int|null>
     */
    public function resolveLimits(?Tenant $tenant): array
    {
        if ($tenant === null) {
            return [];
        }

        $plan = $tenant->subscriptionPlan
            ?? SubscriptionPlan::query()->find($tenant->subscription_plan_id);

        $limits = is_array($plan?->limits) ? $plan->limits : [];

        return [
            'max_locations' => isset($limits['max_locations']) ? (int) $limits['max_locations'] : null,
            'max_staff' => isset($limits['max_staff']) ? (int) $limits['max_staff'] : null,
            'max_workspaces' => isset($limits['max_workspaces']) ? (int) $limits['max_workspaces'] : null,
        ];
    }

    /**
     * @return array{
     *     catalogue: list<array{key: string, label: string, description: string, core: bool}>,
     *     plans: list<array<string, mixed>>
     * }
     */
    public function platformModulesIndex(): array
    {
        $plans = SubscriptionPlan::query()
            ->whereIn('slug', ['basic', 'pro', 'diamond'])
            ->orderByRaw("CASE slug WHEN 'basic' THEN 1 WHEN 'pro' THEN 2 WHEN 'diamond' THEN 3 ELSE 9 END")
            ->get()
            ->map(fn (SubscriptionPlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'features' => $this->featuresForPlan($plan),
                'limits' => $plan->limits ?? [],
                'display_price_cents' => $plan->display_price_cents,
                'is_active' => (bool) $plan->is_active,
            ])
            ->all();

        return [
            'catalogue' => PlatformModuleCatalogue::all(),
            'plans' => $plans,
        ];
    }

    /**
     * @param  array<string, bool>  $features
     * @param  array<string, int>|null  $limits
     */
    public function updatePlanModules(SubscriptionPlan $plan, array $features, ?array $limits = null): SubscriptionPlan
    {
        $normalized = $this->normalizeFeatureMap($features);
        $old = [
            'features' => $plan->features,
            'limits' => $plan->limits,
        ];

        $plan->features = $normalized;
        if ($limits !== null) {
            $plan->limits = [
                'max_locations' => (int) ($limits['max_locations'] ?? 1),
                'max_staff' => (int) ($limits['max_staff'] ?? 5),
                'max_workspaces' => (int) ($limits['max_workspaces'] ?? 10),
            ];
        }
        $plan->save();

        $this->audit->log('platform.plan.modules_updated', $plan, $old, [
            'features' => $plan->features,
            'limits' => $plan->limits,
            'slug' => $plan->slug,
        ]);

        return $plan->fresh();
    }

    /**
     * @return array{
     *     tenant_id: string,
     *     plan_slug: string|null,
     *     plan_features: array<string, bool>,
     *     overrides: array<string, bool>,
     *     effective: array<string, bool>,
     *     limits: array<string, int|null>,
     *     catalogue: list<array{key: string, label: string, description: string, core: bool}>,
     *     ai_hairstyle_eligible: bool,
     *     ai_hairstyle_trial_ends_at: string|null
     * }
     */
    public function tenantModules(Tenant $tenant): array
    {
        $plan = $tenant->subscriptionPlan
            ?? SubscriptionPlan::query()->find($tenant->subscription_plan_id);
        $planFeatures = $this->featuresForPlan($plan);

        $overrides = TenantModuleOverride::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->mapWithKeys(fn (TenantModuleOverride $row) => [$row->module_key => (bool) $row->enabled])
            ->all();

        return [
            'tenant_id' => $tenant->id,
            'plan_slug' => $plan?->slug,
            'plan_features' => $planFeatures,
            'overrides' => $overrides,
            'effective' => $this->resolveFeatures($tenant),
            'limits' => $this->resolveLimits($tenant),
            'catalogue' => PlatformModuleCatalogue::all(),
            'ai_hairstyle_eligible' => ProgressiveModuleAccess::isAiHairstyleEligible($tenant->business_type),
            'ai_hairstyle_trial_ends_at' => $tenant->ai_hairstyle_trial_ends_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, bool|null>  $overrides  null removes override (inherit plan)
     */
    public function syncTenantOverrides(Tenant $tenant, array $overrides): array
    {
        return DB::transaction(function () use ($tenant, $overrides) {
            $old = $this->tenantModules($tenant);

            foreach ($overrides as $key => $value) {
                if (! PlatformModuleCatalogue::isValid((string) $key)) {
                    throw ValidationException::withMessages([
                        'overrides' => ["Unknown module key: {$key}"],
                    ]);
                }

                if ($key === 'ai_hairstyle' && $value === true) {
                    if (! ProgressiveModuleAccess::isAiHairstyleEligible($tenant->business_type)) {
                        throw ValidationException::withMessages([
                            'overrides.ai_hairstyle' => [
                                'AI Hairstyle Preview can only be enabled for barbershop, barber, boutique, chain, or spa tenants.',
                            ],
                        ]);
                    }
                    $this->ensureAiHairstyleTrialWindow($tenant);
                    $tenant->refresh();
                }

                if ($value === null) {
                    TenantModuleOverride::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('module_key', $key)
                        ->delete();
                    continue;
                }

                TenantModuleOverride::withoutGlobalScopes()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'module_key' => $key,
                    ],
                    ['enabled' => (bool) $value],
                );
            }

            $fresh = $this->tenantModules($tenant->fresh());

            $this->audit->log('platform.tenant.modules_updated', $tenant, [
                'overrides' => $old['overrides'],
                'effective' => $old['effective'],
                'ai_hairstyle_trial_ends_at' => $old['ai_hairstyle_trial_ends_at'],
            ], [
                'overrides' => $fresh['overrides'],
                'effective' => $fresh['effective'],
                'ai_hairstyle_trial_ends_at' => $fresh['ai_hairstyle_trial_ends_at'],
            ]);

            return $fresh;
        });
    }

    /**
     * @return array<string, bool>
     */
    public function featuresForPlan(?SubscriptionPlan $plan): array
    {
        $defaults = PlatformModuleCatalogue::defaultsForPlanSlug($plan?->slug ?? 'basic');
        $stored = is_array($plan?->features) ? $plan->features : [];

        $merged = $defaults;
        foreach (PlatformModuleCatalogue::keys() as $key) {
            if (array_key_exists($key, $stored)) {
                $merged[$key] = (bool) $stored[$key];
            }
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $features
     * @return array<string, bool>
     */
    private function normalizeFeatureMap(array $features): array
    {
        $normalized = [];
        foreach (PlatformModuleCatalogue::keys() as $key) {
            $normalized[$key] = (bool) ($features[$key] ?? false);
        }

        return $normalized;
    }
}
