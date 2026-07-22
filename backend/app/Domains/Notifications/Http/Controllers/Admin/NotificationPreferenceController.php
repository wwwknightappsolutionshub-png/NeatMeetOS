<?php

namespace App\Domains\Notifications\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Http\Resources\NotificationPreferenceResource;
use App\Domains\Notifications\Services\NotificationPreferenceService;
use App\Domains\Notifications\Services\NotificationScopeValidator;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationPreferenceController extends Controller
{
    public function __construct(
        private readonly NotificationPreferenceService $preferenceService,
        private readonly NotificationScopeValidator $scope,
    ) {}

    public function show(string $clientId): JsonResponse
    {
        $client = $this->scope->findClient($clientId);
        $preference = $this->preferenceService->getOrCreateForClient($client);

        return ApiResponse::success(new NotificationPreferenceResource($preference));
    }

    public function update(Request $request, string $clientId): JsonResponse
    {
        $data = $request->validate([
            'allow_email' => ['nullable', 'boolean'],
            'allow_sms' => ['nullable', 'boolean'],
            'allow_whatsapp' => ['nullable', 'boolean'],
            'allow_push' => ['nullable', 'boolean'],
            'booking_notifications' => ['nullable', 'boolean'],
            'payment_notifications' => ['nullable', 'boolean'],
            'membership_notifications' => ['nullable', 'boolean'],
            'general_notifications' => ['nullable', 'boolean'],
            'preferred_channel' => ['nullable', Rule::in(NotificationChannel::all())],
        ]);

        $client = $this->scope->findClient($clientId);
        $preference = $this->preferenceService->update($client, $data);

        return ApiResponse::success(new NotificationPreferenceResource($preference), 'Preferences updated');
    }

    public function syncFromConsent(string $clientId): JsonResponse
    {
        $client = $this->scope->findClient($clientId);
        $preference = $this->preferenceService->syncFromConsent($client);

        return ApiResponse::success(new NotificationPreferenceResource($preference), 'Preferences synced from consent');
    }
}
