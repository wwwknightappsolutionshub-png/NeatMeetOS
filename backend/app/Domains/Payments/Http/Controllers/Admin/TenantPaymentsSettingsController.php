<?php

namespace App\Domains\Payments\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Payments\Services\TenantPaymentsSettingsService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantPaymentsSettingsController extends Controller
{
    public function __construct(
        private readonly TenantPaymentsSettingsService $settings,
    ) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success($this->settings->get());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_sort_code' => ['nullable', 'string', 'max:32'],
            'bank_account_number' => ['nullable', 'string', 'max:64'],
            'bank_iban' => ['nullable', 'string', 'max:64'],
            'bank_reference_hint' => ['nullable', 'string', 'max:255'],
        ]);

        return ApiResponse::success($this->settings->update($data), 'Payment settings updated');
    }
}
