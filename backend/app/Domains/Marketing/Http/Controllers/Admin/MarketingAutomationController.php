<?php

namespace App\Domains\Marketing\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Marketing\Services\MarketingAutomationSettingService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingAutomationController extends Controller
{
    public function __construct(
        private readonly MarketingAutomationSettingService $settingService,
    ) {}

    public function showSettings(): JsonResponse
    {
        return ApiResponse::success($this->settingService->get());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_reminder_hours_before' => ['sometimes', 'integer', 'min:0', 'max:2160'],
            'review_request_delay_hours' => ['sometimes', 'integer', 'min:0', 'max:2160'],
            'rebooking_window_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'win_back_inactivity_days' => ['sometimes', 'integer', 'min:0', 'max:3650'],
            'review_request_enabled' => ['sometimes', 'boolean'],
            'auto_pause_on_consent_withdrawal' => ['sometimes', 'boolean'],
        ]);

        return ApiResponse::success($this->settingService->update($data), 'Automation settings updated');
    }
}
