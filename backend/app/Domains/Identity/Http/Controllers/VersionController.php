<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class VersionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success([
            'name' => 'NeatMeet OS API',
            'version' => config('app.version', '0.1.0-bootstrap'),
            'api' => 'v1',
        ]);
    }
}
