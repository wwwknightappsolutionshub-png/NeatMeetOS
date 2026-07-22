<?php

namespace App\Domains\Memberships\Http\Controllers\PublicMembership;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Memberships\Services\MembershipPublicLandingService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PublicMembershipLandingController extends Controller
{
    public function __construct(
        private readonly MembershipPublicLandingService $landing,
    ) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success($this->landing->landing());
    }
}
