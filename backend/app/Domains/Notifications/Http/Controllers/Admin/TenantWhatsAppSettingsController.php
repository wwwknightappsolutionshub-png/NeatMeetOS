<?php

namespace App\Domains\Notifications\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Notifications\Services\TenantWhatsAppSettingsService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TenantWhatsAppSettingsController extends Controller
{
    public function __construct(
        private readonly TenantWhatsAppSettingsService $settings,
    ) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success(['whatsapp' => $this->settings->get()]);
    }

    public function initSession(): JsonResponse
    {
        return ApiResponse::success(
            ['whatsapp' => $this->settings->initHostedSession()],
            'WhatsApp scan session ready',
        );
    }

    public function activateSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone_number' => ['required', 'string', 'max:40'],
        ]);

        try {
            $whatsapp = $this->settings->activateHostedSession($data['phone_number']);
        } catch (ValidationException $e) {
            return ApiResponse::error(
                collect($e->errors())->flatten()->first() ?: 'Validation failed',
                422,
                $e->errors(),
            );
        }

        return ApiResponse::success(['whatsapp' => $whatsapp], 'Tenant WhatsApp session activated');
    }

    public function refreshSession(): JsonResponse
    {
        try {
            $whatsapp = $this->settings->refreshHostedSession();
        } catch (ValidationException $e) {
            return ApiResponse::error(
                collect($e->errors())->flatten()->first() ?: 'Validation failed',
                422,
                $e->errors(),
            );
        }

        return ApiResponse::success(['whatsapp' => $whatsapp], 'WhatsApp session refreshed');
    }

    public function disconnectSession(): JsonResponse
    {
        return ApiResponse::success(
            ['whatsapp' => $this->settings->disconnectHostedSession()],
            'Disconnected — platform WhatsApp will be used as fallback',
        );
    }
}
