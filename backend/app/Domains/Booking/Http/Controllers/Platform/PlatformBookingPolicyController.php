<?php

namespace App\Domains\Booking\Http\Controllers\Platform;

use App\Domains\Booking\Http\Resources\BookingPolicySettingResource;
use App\Domains\Booking\Services\BookingPolicyService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Models\Tenant;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformBookingPolicyController extends Controller
{
    public function __construct(
        private readonly BookingPolicyService $policy,
    ) {}

    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::query()->findOrFail($id);

        return ApiResponse::success(
            new BookingPolicySettingResource($this->policy->get($tenant)),
        );
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::query()->findOrFail($id);

        $validated = $request->validate([
            'min_advance_notice_minutes' => ['sometimes', 'integer', 'min:0', 'max:10080'],
            'free_change_window_minutes' => ['sometimes', 'integer', 'min:0', 'max:10080'],
            'late_cancel_fee_percent' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'free_window_reminder_lead_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'approval_reminder_interval_minutes' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'approval_reminder_max_count' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $settings = $this->policy->updateForTenant($tenant, $validated);

        return ApiResponse::success(
            new BookingPolicySettingResource($settings),
            'Booking policy updated',
        );
    }
}
