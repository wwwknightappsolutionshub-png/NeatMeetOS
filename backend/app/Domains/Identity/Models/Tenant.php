<?php

namespace App\Domains\Identity\Models;

use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use HasUuid;

    public const BRANDING_DEFAULTS = [
        'brand_display_name' => null,
        'logo_url' => null,
        'primary_color' => '#18181b',
        'secondary_color' => '#fafafa',
        'receipt_display_name' => null,
        'support_email' => null,
        'support_phone' => null,
        'hero_emblem_mode' => 'none',
        'hero_emblem_url' => null,
        'hero_image_url' => null,
        'store_status' => 'auto',
        'social_facebook_url' => null,
        'social_instagram_url' => null,
        'social_tiktok_url' => null,
    ];

    public const HERO_EMBLEM_MODES = ['none', 'logo', 'custom'];

    public const STORE_STATUSES = ['auto', 'open', 'opening_soon', 'closing', 'closed'];

    protected $fillable = [
        'name',
        'trading_name',
        'slug',
        'status',
        'activated_at',
        'admin_last_seen_at',
        'suspended_at',
        'suspension_reason',
        'business_type',
        'timezone',
        'contact_email',
        'contact_phone',
        'owner_whatsapp',
        'subscription_plan_id',
        'ai_hairstyle_trial_ends_at',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'activated_at' => 'datetime',
            'admin_last_seen_at' => 'datetime',
            'suspended_at' => 'datetime',
            'ai_hairstyle_trial_ends_at' => 'datetime',
        ];
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function getBranding(): array
    {
        $settings = $this->settings ?? [];
        $branding = is_array($settings['branding'] ?? null) ? $settings['branding'] : [];

        return array_merge(self::BRANDING_DEFAULTS, $branding);
    }

    public function setBranding(array $branding): void
    {
        $settings = $this->settings ?? [];
        $settings['branding'] = array_merge($this->getBranding(), $branding);
        $this->settings = $settings;
    }
}
