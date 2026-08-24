<?php

namespace App\Domains\Crm\Http\Controllers\Admin;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientThreadMessage;
use App\Domains\Crm\Services\ClientThreadService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientThreadController extends Controller
{
    public function __construct(
        private readonly ClientThreadService $threads,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(string $clientId): JsonResponse
    {
        $client = $this->findClient($clientId);
        $payload = $this->threads->listForMember($client);

        return ApiResponse::success($payload['items']);
    }

    public function store(Request $request, string $clientId): JsonResponse
    {
        $client = $this->findClient($clientId);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:255'],
            'channel' => ['nullable', Rule::in([
                ClientThreadMessage::CHANNEL_IN_APP,
                ClientThreadMessage::CHANNEL_WHATSAPP_MODE_A,
                ClientThreadMessage::CHANNEL_EMAIL,
            ])],
            'include_whatsapp_deeplink' => ['nullable', 'boolean'],
            'notify_member' => ['nullable', 'boolean'],
        ]);

        $tenant = $this->tenantContext->get();
        $deeplink = null;
        $channel = $data['channel'] ?? ClientThreadMessage::CHANNEL_IN_APP;

        if ((bool) ($data['include_whatsapp_deeplink'] ?? false) || $channel === ClientThreadMessage::CHANNEL_WHATSAPP_MODE_A) {
            $deeplink = $tenant
                ? $this->threads->buildWaMeLinkForTenant($tenant, $data['body'])
                : null;
            if ($deeplink) {
                $channel = ClientThreadMessage::CHANNEL_WHATSAPP_MODE_A;
            }
        }

        $notifyMember = (bool) ($data['notify_member'] ?? true);
        if ($channel !== ClientThreadMessage::CHANNEL_IN_APP) {
            $notifyMember = false;
        }

        $message = $this->threads->postOutbound($client, [
            'channel' => $channel,
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'],
            'whatsapp_deeplink' => $deeplink,
            'metadata' => [
                'source' => 'admin_thread',
                'whatsapp_deeplink' => $deeplink,
            ],
        ], $request->user()?->id, $notifyMember);

        $this->threads->markInboundReadByStaff($client);

        return ApiResponse::success(
            $this->threads->serialize($message),
            'Message posted',
            201,
        );
    }

    public function markRead(string $clientId): JsonResponse
    {
        $client = $this->findClient($clientId);
        $updated = $this->threads->markInboundReadByStaff($client);

        return ApiResponse::success(['updated' => $updated], 'Marked read');
    }

    private function findClient(string $clientId): Client
    {
        $client = Client::query()->findOrFail($clientId);
        if ($client->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['client' => ['Client not found.']]);
        }

        return $client;
    }
}
