<?php

namespace App\Domains\Notifications\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Notifications\Http\Resources\NotificationAutomationSettingResource;
use App\Domains\Notifications\Services\NotificationAutomationSettingService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSettingController extends Controller
{
    public function __construct(
        private readonly NotificationAutomationSettingService $settingService,
    ) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success(new NotificationAutomationSettingResource($this->settingService->get()));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_reminders_enabled' => ['nullable', 'boolean'],
            'booking_confirmation_enabled' => ['nullable', 'boolean'],
            'cancellation_notifications_enabled' => ['nullable', 'boolean'],
            'payment_link_notifications_enabled' => ['nullable', 'boolean'],
            'payment_reminders_enabled' => ['nullable', 'boolean'],
            'membership_expiry_notifications_enabled' => ['nullable', 'boolean'],
            'membership_renewal_notifications_enabled' => ['nullable', 'boolean'],
            'default_booking_reminder_hours' => ['nullable', 'integer', 'min:0', 'max:2160'],
            'default_booking_reminder_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'default_payment_reminder_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'sender_name' => ['nullable', 'string', 'max:255'],
            'sender_email' => ['nullable', 'email', 'max:255'],
            'sender_sms_name' => ['nullable', 'string', 'max:20'],
            'metadata' => ['nullable', 'array'],
        ]);

        return ApiResponse::success(new NotificationAutomationSettingResource($this->settingService->update($data)), 'Settings updated');
    }
}
