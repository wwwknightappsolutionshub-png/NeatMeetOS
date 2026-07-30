<?php

namespace App\Domains\AiHairstyle\Http\Controllers\Platform;

use App\Domains\AiHairstyle\Services\PlatformAiHairstyleSettingService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlatformAiHairstyleSettingController extends Controller
{
    public function __construct(
        private readonly PlatformAiHairstyleSettingService $settings,
    ) {}

    public function show(): JsonResponse
    {
        $setting = $this->settings->getOrCreate();

        return ApiResponse::success($this->settings->toArray($setting));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', Rule::in(['stub', 'replicate'])],
        ]);

        try {
            $setting = $this->settings->update($data);
        } catch (ValidationException $e) {
            return ApiResponse::error(
                collect($e->errors())->flatten()->first() ?: 'Validation failed',
                422,
                $e->errors(),
            );
        }

        return ApiResponse::success($this->settings->toArray($setting), 'AI hairstyle provider updated');
    }
}
