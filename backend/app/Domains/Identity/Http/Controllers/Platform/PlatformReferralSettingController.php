<?php

namespace App\Domains\Identity\Http\Controllers\Platform;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Models\PlatformReferralSetting;
use App\Domains\Identity\Services\PlatformReferralSettingService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformReferralSettingController extends Controller
{
    public function __construct(
        private readonly PlatformReferralSettingService $settings,
    ) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success($this->serialize($this->settings->getOrCreate()));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'reward_type' => ['sometimes', 'string', Rule::in(PlatformReferralSetting::rewardTypes())],
            'reward_amount' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'qualification_goal' => ['sometimes', 'string', Rule::in(PlatformReferralSetting::qualificationGoals())],
            'qualification_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'share_headline' => ['nullable', 'string', 'max:200'],
            'share_body' => ['nullable', 'string', 'max:2000'],
        ]);

        $setting = $this->settings->update($data);

        return ApiResponse::success($this->serialize($setting), 'Referral settings updated');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PlatformReferralSetting $setting): array
    {
        return [
            'enabled' => (bool) $setting->enabled,
            'reward_type' => $setting->reward_type,
            'reward_amount' => (int) $setting->reward_amount,
            'qualification_goal' => $setting->qualification_goal,
            'qualification_days' => $setting->qualification_days,
            'share_headline' => $setting->share_headline,
            'share_body' => $setting->share_body,
            'reward_types' => PlatformReferralSetting::rewardTypes(),
            'qualification_goals' => PlatformReferralSetting::qualificationGoals(),
        ];
    }
}
