<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Services\PlatformUpgradeDiscountService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpgradeOfferController extends Controller
{
    public function __construct(private readonly PlatformUpgradeDiscountService $discounts) {}

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20'],
        ]);

        return ApiResponse::success($this->discounts->previewByToken($data['token']));
    }

    public function claim(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20'],
        ]);

        return ApiResponse::success(
            $this->discounts->claimByToken($data['token'], $request->user()),
            'Discount claimed',
        );
    }
}
