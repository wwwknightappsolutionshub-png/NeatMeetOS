<?php

namespace App\Domains\Notifications\Http\Controllers\Platform;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Notifications\Services\PlatformWhatsAppSettingsService;
use App\Domains\Notifications\Services\WhatsApp\PlatformSignupWhatsAppWelcomeService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlatformWhatsAppSettingsController extends Controller
{
    public function __construct(
        private readonly PlatformWhatsAppSettingsService $settings,
        private readonly PlatformSignupWhatsAppWelcomeService $signupWelcome,
    ) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success(['whatsapp' => $this->settings->get()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'provider' => ['sometimes', 'string', Rule::in(['genius', 'meta', 'twilio'])],
            'api_key' => ['sometimes', 'nullable', 'string', 'max:500'],
            'session_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'base_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'meta_phone_number_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_access_token' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'twilio_account_sid' => ['sometimes', 'nullable', 'string', 'max:255'],
            'twilio_auth_token' => ['sometimes', 'nullable', 'string', 'max:500'],
            'twilio_from' => ['sometimes', 'nullable', 'string', 'max:40'],
            'signup_welcome_enabled' => ['sometimes', 'boolean'],
            'signup_welcome_trial_body' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'signup_welcome_activation_body' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ]);

        try {
            $whatsapp = $this->settings->update($data);
        } catch (ValidationException $e) {
            return ApiResponse::error(
                collect($e->errors())->flatten()->first() ?: 'Validation failed',
                422,
                $e->errors(),
            );
        }

        return ApiResponse::success(['whatsapp' => $whatsapp], 'WhatsApp settings updated');
    }

    public function test(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $result = $this->settings->sendTestMessage($data['phone'], $data['message'] ?? null);
        } catch (ValidationException $e) {
            return ApiResponse::error(
                collect($e->errors())->flatten()->first() ?: 'Validation failed',
                422,
                $e->errors(),
            );
        }

        if (! ($result['sent'] ?? false)) {
            return ApiResponse::error(
                $result['error'] ?? 'WhatsApp test failed',
                422,
                ['result' => $result],
            );
        }

        return ApiResponse::success($result, 'Test WhatsApp sent');
    }

    public function queueStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'older_than_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
        ]);

        return ApiResponse::success([
            'queue' => $this->settings->queueStatus($data['older_than_hours'] ?? 1),
        ]);
    }

    public function purge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'include_failed_jobs' => ['sometimes', 'boolean'],
            'include_stale_messages' => ['sometimes', 'boolean'],
            'older_than_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
        ]);

        $result = $this->settings->purgeStale(
            includeFailedJobs: array_key_exists('include_failed_jobs', $data)
                ? (bool) $data['include_failed_jobs']
                : true,
            includeStaleMessages: array_key_exists('include_stale_messages', $data)
                ? (bool) $data['include_stale_messages']
                : true,
            olderThanHours: $data['older_than_hours'] ?? 1,
        );

        return ApiResponse::success($result, 'Stale WhatsApp messages purged');
    }

    public function uploadSignupWelcomeBanner(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ]);

        try {
            $welcome = $this->signupWelcome->storeBanner($request->file('image'));
        } catch (ValidationException $e) {
            return ApiResponse::error(
                collect($e->errors())->flatten()->first() ?: 'Validation failed',
                422,
                $e->errors(),
            );
        }

        return ApiResponse::success([
            'whatsapp' => $this->settings->get(),
            'signup_welcome' => $welcome,
        ], 'Signup welcome banner uploaded');
    }

    public function clearSignupWelcomeBanner(): JsonResponse
    {
        $welcome = $this->signupWelcome->clearBanner();

        return ApiResponse::success([
            'whatsapp' => $this->settings->get(),
            'signup_welcome' => $welcome,
        ], 'Signup welcome banner cleared');
    }
}
