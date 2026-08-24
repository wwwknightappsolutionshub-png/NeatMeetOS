<?php

namespace App\Domains\Crm\Http\Controllers\Admin;

use App\Domains\Crm\Services\ClientThreadService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminMessagesController extends Controller
{
    public function __construct(
        private readonly ClientThreadService $threads,
    ) {}

    public function conversations(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filter' => ['nullable', Rule::in(['open', 'all'])],
        ]);

        $filter = $data['filter'] ?? 'open';

        return ApiResponse::success($this->threads->listConversationSummaries($filter));
    }
}
