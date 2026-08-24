<?php

namespace App\Domains\Crm\Http\Controllers\PublicMember;

use App\Domains\Crm\Services\ClientNoticeService;
use App\Domains\Crm\Services\ClientThreadService;
use App\Domains\Crm\Services\MemberPortalExperienceService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Combined member Messages: notices + in-app chat thread.
 */
class MemberMessagesController extends Controller
{
    public function __construct(
        private readonly MemberPortalExperienceService $experience,
        private readonly ClientNoticeService $notices,
        private readonly ClientThreadService $threads,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $client = $this->experience->requireClient($this->bearerToken($request));
        $noticePayload = $this->notices->listForClient($client);
        $threadPayload = $this->threads->listForMember($client);

        return ApiResponse::success([
            'notices' => $noticePayload['items'],
            'unread_notices' => $noticePayload['unread_count'],
            'thread' => $threadPayload['items'],
            'unread_thread' => $threadPayload['unread_count'],
            'unread_total' => (int) $noticePayload['unread_count'] + (int) $threadPayload['unread_count'],
        ]);
    }

    public function threads(Request $request): JsonResponse
    {
        $client = $this->experience->requireClient($this->bearerToken($request));
        $payload = $this->threads->listForMember($client);

        return ApiResponse::success($payload['items']);
    }

    public function store(Request $request): JsonResponse
    {
        $client = $this->experience->requireClient($this->bearerToken($request));
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = $this->threads->postInbound($client, $data['body']);

        return ApiResponse::success($this->threads->serialize($message), 'Message sent', 201);
    }

    public function markThreadRead(Request $request): JsonResponse
    {
        $client = $this->experience->requireClient($this->bearerToken($request));
        $updated = $this->threads->markOutboundReadForClient($client);

        return ApiResponse::success(['updated' => $updated], 'Marked read');
    }

    private function bearerToken(Request $request): string
    {
        $header = (string) $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return trim($m[1]);
        }

        return (string) $request->input('token', '');
    }
}
