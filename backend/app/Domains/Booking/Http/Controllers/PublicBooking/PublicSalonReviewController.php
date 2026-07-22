<?php

namespace App\Domains\Booking\Http\Controllers\PublicBooking;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Booking\Services\SalonReviewService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicSalonReviewController extends Controller
{
    public function __construct(private readonly SalonReviewService $reviews) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success($this->reviews->listPublished());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'author_name' => ['required', 'string', 'min:2', 'max:120'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['required', 'string', 'min:8', 'max:1000'],
        ]);

        return ApiResponse::success($this->reviews->createPublic($data), 'Thanks for your review', 201);
    }
}
