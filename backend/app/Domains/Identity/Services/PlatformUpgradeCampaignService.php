<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\PlatformUpgradeCampaignSetting;
use App\Domains\Identity\Models\PlatformUpgradeTemplate;
use App\Domains\Identity\Support\PlatformUpgradeCatalogue;
use App\Shared\Audit\AuditLogger;

class PlatformUpgradeCampaignService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function settings(): PlatformUpgradeCampaignSetting
    {
        $row = PlatformUpgradeCampaignSetting::query()->first();
        if ($row !== null) {
            return $row;
        }

        return PlatformUpgradeCampaignSetting::query()->create([
            'is_enabled' => true,
            'discount_percent' => 5,
            'channel_email' => true,
            'channel_whatsapp' => true,
            'channel_in_app' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSettings(array $data): PlatformUpgradeCampaignSetting
    {
        $settings = $this->settings();
        $old = $settings->toArray();
        $settings->fill([
            'is_enabled' => (bool) ($data['is_enabled'] ?? $settings->is_enabled),
            'discount_percent' => (int) ($data['discount_percent'] ?? $settings->discount_percent),
            'channel_email' => (bool) ($data['channel_email'] ?? $settings->channel_email),
            'channel_whatsapp' => (bool) ($data['channel_whatsapp'] ?? $settings->channel_whatsapp),
            'channel_in_app' => (bool) ($data['channel_in_app'] ?? $settings->channel_in_app),
        ])->save();

        $this->audit->log('platform.upgrade_campaign.settings_updated', $settings, $old, $settings->toArray());

        return $settings->fresh();
    }

    /**
     * @return list<PlatformUpgradeTemplate>
     */
    public function ensureDefaultTemplates(): array
    {
        $created = [];
        foreach (['basic_to_pro', 'pro_to_diamond'] as $path) {
            foreach (['day_3', 'day_7', 'day_21'] as $step) {
                $channels = match ($step) {
                    'day_3' => ['whatsapp', 'in_app'],
                    default => ['email'],
                };
                foreach ($channels as $channel) {
                    $created[] = $this->ensureTemplate($path, $step, $channel);
                }
            }
        }

        return $created;
    }

    public function ensureTemplate(string $path, string $step, string $channel): PlatformUpgradeTemplate
    {
        $existing = PlatformUpgradeTemplate::query()
            ->where('path', $path)
            ->where('step', $step)
            ->where('channel', $channel)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $defaults = $this->defaultContent($path, $step, $channel);

        return PlatformUpgradeTemplate::query()->create($defaults);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listTemplates(): array
    {
        $this->ensureDefaultTemplates();
        $this->settings();

        return PlatformUpgradeTemplate::query()
            ->orderBy('path')
            ->orderByRaw("CASE step WHEN 'day_3' THEN 1 WHEN 'day_7' THEN 2 WHEN 'day_21' THEN 3 ELSE 9 END")
            ->orderBy('channel')
            ->get()
            ->map(fn (PlatformUpgradeTemplate $t) => $this->serializeTemplate($t))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateTemplate(string $id, array $data): array
    {
        $template = PlatformUpgradeTemplate::query()->findOrFail($id);
        $old = $template->toArray();

        $template->fill([
            'subject' => $data['subject'] ?? $template->subject,
            'headline' => $data['headline'] ?? $template->headline,
            'body_html' => $data['body_html'] ?? $template->body_html,
            'body_text' => $data['body_text'] ?? $template->body_text,
            'cta_label' => $data['cta_label'] ?? $template->cta_label,
            'image_path' => array_key_exists('image_path', $data) ? $data['image_path'] : $template->image_path,
            'features' => $data['features'] ?? $template->features,
            'use_cases' => $data['use_cases'] ?? $template->use_cases,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $template->is_active,
            'version' => (int) $template->version + 1,
        ])->save();

        $this->audit->log('platform.upgrade_campaign.template_updated', $template, $old, $template->toArray());

        return $this->serializeTemplate($template->fresh());
    }

    public function findActiveTemplate(string $path, string $step, string $channel): ?PlatformUpgradeTemplate
    {
        $this->ensureTemplate($path, $step, $channel);

        return PlatformUpgradeTemplate::query()
            ->where('path', $path)
            ->where('step', $step)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeTemplate(PlatformUpgradeTemplate $t): array
    {
        return [
            'id' => $t->id,
            'path' => $t->path,
            'step' => $t->step,
            'channel' => $t->channel,
            'subject' => $t->subject,
            'headline' => $t->headline,
            'body_html' => $t->body_html,
            'body_text' => $t->body_text,
            'cta_label' => $t->cta_label,
            'image_path' => $t->image_path,
            'features' => $t->features ?? [],
            'use_cases' => $t->use_cases ?? [],
            'is_active' => (bool) $t->is_active,
            'version' => (int) $t->version,
            'updated_at' => $t->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultContent(string $path, string $step, string $channel): array
    {
        $target = PlatformUpgradeCatalogue::targetPlanName($path);
        $unlocks = PlatformUpgradeCatalogue::unlocksForPath($path);
        $features = array_map(fn ($u) => ['key' => $u['key'], 'label' => $u['label']], $unlocks);
        $useCases = array_map(fn ($u) => ['key' => $u['key'], 'label' => $u['label'], 'text' => $u['use_case']], $unlocks);

        $image = $path === 'pro_to_diamond'
            ? '/campaigns/upgrade-day3-pro-to-diamond.png'
            : '/campaigns/upgrade-day3-basic-to-pro.png';

        if ($step === 'day_3' && $channel === 'whatsapp') {
            return [
                'path' => $path,
                'step' => $step,
                'channel' => $channel,
                'subject' => null,
                'headline' => "Ready for {$target}?",
                'body_html' => null,
                'body_text' => "{{salon_name}}: unlock {$target} — ".implode(', ', array_column($features, 'label')).'. Reply or open NeatMeet to upgrade.',
                'cta_label' => "See {$target}",
                'image_path' => $image,
                'features' => $features,
                'use_cases' => $useCases,
                'is_active' => true,
                'version' => 1,
            ];
        }

        if ($step === 'day_3' && $channel === 'in_app') {
            return [
                'path' => $path,
                'step' => $step,
                'channel' => $channel,
                'subject' => null,
                'headline' => "Unlock {$target} for {{salon_name}}",
                'body_html' => null,
                'body_text' => 'Your trial is underway. Here is what the next tier unlocks for your floor.',
                'cta_label' => 'View subscription',
                'image_path' => $image,
                'features' => $features,
                'use_cases' => $useCases,
                'is_active' => true,
                'version' => 1,
            ];
        }

        if ($step === 'day_7') {
            $list = '';
            foreach ($useCases as $uc) {
                $list .= '<li style="margin:0 0 10px;"><strong>'.e($uc['label']).'</strong> — '.e($uc['text']).'</li>';
            }

            return [
                'path' => $path,
                'step' => $step,
                'channel' => 'email',
                'subject' => "{{salon_name}}: why salons move to {$target}",
                'headline' => "{{owner_first_name}}, {$target} is built for how you work",
                'body_html' => '<p style="margin:0 0 16px;line-height:1.55;">A week in, you have felt the rhythm of NeatMeet. Upgrading to <strong>'.$target.'</strong> unlocks the tools growing salons use every day:</p><ul style="margin:0 0 16px;padding-left:18px;line-height:1.5;">'.$list.'</ul>',
                'body_text' => "Upgrade to {$target} to unlock ".implode(', ', array_column($features, 'label')).'.',
                'cta_label' => "Upgrade to {$target}",
                'image_path' => $image,
                'features' => $features,
                'use_cases' => $useCases,
                'is_active' => true,
                'version' => 1,
            ];
        }

        // day_21 email
        return [
            'path' => $path,
            'step' => $step,
            'channel' => 'email',
            'subject' => 'Upgrade within this time — claim 5% off '.$target,
            'headline' => 'Upgrade Within this time',
            'body_html' => '<p style="margin:0 0 16px;line-height:1.55;">{{owner_first_name}}, your {{salon_name}} trial ends on <strong>{{trial_ends_at}}</strong>. Lock in <strong>{{discount_percent}}% off</strong> '.$target.' before the window closes.</p>',
            'body_text' => 'Upgrade within this time. Claim {{discount_percent}}% off '.$target.' before {{trial_ends_at}}.',
            'cta_label' => 'Claim 5% Discount',
            'image_path' => $image,
            'features' => $features,
            'use_cases' => $useCases,
            'is_active' => true,
            'version' => 1,
        ];
    }
}
