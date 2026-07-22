<?php

namespace App\Domains\Booking\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Booking\Services\SalonReviewService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalonReviewController extends Controller
{
    public function __construct(private readonly SalonReviewService $reviews) {}

    public function index(Request $request): JsonResponse
    {
        $published = $request->has('published')
            ? filter_var($request->query('published'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;

        return ApiResponse::success($this->reviews->listAdmin($published));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'author_name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'body' => ['sometimes', 'string', 'min:8', 'max:1000'],
            'is_published' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ]);

        $review = $this->reviews->find($id);

        return ApiResponse::success($this->reviews->update($review, $data), 'Review updated');
    }

    public function destroy(string $id): JsonResponse
    {
        $review = $this->reviews->find($id);
        $this->reviews->delete($review);

        return ApiResponse::success(null, 'Review deleted');
    }
}
