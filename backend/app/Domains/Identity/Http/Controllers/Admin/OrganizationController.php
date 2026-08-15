<?php

namespace App\Domains\Identity\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Http\Resources\TenantResource;
use App\Domains\Identity\Services\OrganizationService;
use App\Shared\Support\ApiResponse;
use App\Shared\Support\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function __construct(private readonly OrganizationService $organizationService) {}

    public function show(): JsonResponse
    {
        $tenant = $this->organizationService->getCurrent();

        return ApiResponse::success(new TenantResource($tenant));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'trading_name' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'currency' => ['sometimes', 'string', 'size:3', Rule::in(Currency::SUPPORTED)],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        if (isset($data['currency'])) {
            $data['currency'] = Currency::normalize($data['currency']);
        }

        $tenant = $this->organizationService->update($data);

        return ApiResponse::success(new TenantResource($tenant), 'Organization updated');
    }
}
