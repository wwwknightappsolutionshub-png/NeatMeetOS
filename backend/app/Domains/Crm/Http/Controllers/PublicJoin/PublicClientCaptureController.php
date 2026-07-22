<?php

namespace App\Domains\Crm\Http\Controllers\PublicJoin;

use App\Domains\Crm\Services\PublicClientCaptureService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicClientCaptureController extends Controller
{
    public function __construct(
        private readonly PublicClientCaptureService $captureService,
    ) {}

    public function bootstrap(Request $request): JsonResponse
    {
        $data = $request->validate([
            'location_id' => ['nullable', 'uuid'],
        ]);

        return ApiResponse::success(
            $this->captureService->bootstrap($data['location_id'] ?? null),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'whatsapp_number' => ['required', 'string', 'min:7', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'location_id' => ['nullable', 'uuid'],
            'special_event_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'special_event_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'special_event_label' => ['nullable', 'string', 'max:80'],
            'referral_code' => ['nullable', 'string', 'max:32'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
        ]);

        $result = $this->captureService->capture($data);

        return ApiResponse::success($result, $result['message'], $result['created'] ? 201 : 200);
    }
}
