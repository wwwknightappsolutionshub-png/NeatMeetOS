<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
        ];

        $healthy = collect($checks)->every(fn (array $check) => $check['status'] === 'ok');

        return ApiResponse::success([
            'status' => $healthy ? 'healthy' : 'degraded',
            'service' => 'neatmeet-os-api',
            'checks' => $checks,
        ], $healthy ? 'OK' : 'Degraded', $healthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'ok'];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Database unreachable'];
        }
    }
}
