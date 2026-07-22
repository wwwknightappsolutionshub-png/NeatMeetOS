<?php

namespace App\Domains\Identity\Http\Controllers\Platform;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Services\PlatformUpgradeCampaignService;
use App\Domains\Identity\Services\PlatformUpgradeDispatchService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformUpgradeCampaignController extends Controller
{
    public function __construct(
        private readonly PlatformUpgradeCampaignService $campaigns,
        private readonly PlatformUpgradeDispatchService $dispatch,
    ) {}

    public function settings(): JsonResponse
    {
        $settings = $this->campaigns->settings();

        return ApiResponse::success([
            'is_enabled' => (bool) $settings->is_enabled,
            'discount_percent' => (int) $settings->discount_percent,
            'channel_email' => (bool) $settings->channel_email,
            'channel_whatsapp' => (bool) $settings->channel_whatsapp,
            'channel_in_app' => (bool) $settings->channel_in_app,
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'is_enabled' => ['sometimes', 'boolean'],
            'discount_percent' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'channel_email' => ['sometimes', 'boolean'],
            'channel_whatsapp' => ['sometimes', 'boolean'],
            'channel_in_app' => ['sometimes', 'boolean'],
        ]);

        $settings = $this->campaigns->updateSettings($data);

        return ApiResponse::success([
            'is_enabled' => (bool) $settings->is_enabled,
            'discount_percent' => (int) $settings->discount_percent,
            'channel_email' => (bool) $settings->channel_email,
            'channel_whatsapp' => (bool) $settings->channel_whatsapp,
            'channel_in_app' => (bool) $settings->channel_in_app,
        ], 'Campaign settings updated');
    }

    public function templates(): JsonResponse
    {
        return ApiResponse::success($this->campaigns->listTemplates());
    }

    public function updateTemplate(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:200'],
            'headline' => ['nullable', 'string', 'max:200'],
            'body_html' => ['nullable', 'string'],
            'body_text' => ['nullable', 'string'],
            'cta_label' => ['nullable', 'string', 'max:80'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'features' => ['nullable', 'array'],
            'use_cases' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return ApiResponse::success(
            $this->campaigns->updateTemplate($id, $data),
            'Template updated',
        );
    }

    public function dispatchNow(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid'],
            'step' => ['required', 'string', 'in:day_3,day_7,day_21'],
            'force' => ['sometimes', 'boolean'],
        ]);

        $tenant = Tenant::query()->with('subscriptionPlan')->findOrFail($data['tenant_id']);
        $result = $this->dispatch->dispatchForTenant(
            $tenant,
            $data['step'],
            (bool) ($data['force'] ?? true),
        );

        return ApiResponse::success($result, 'Dispatch completed');
    }
}
