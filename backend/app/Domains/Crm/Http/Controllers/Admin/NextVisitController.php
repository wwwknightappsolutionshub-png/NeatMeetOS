<?php

namespace App\Domains\Crm\Http\Controllers\Admin;

use App\Domains\Booking\Http\Resources\AppointmentResource;
use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientThreadMessage;
use App\Domains\Crm\Services\ClientThreadService;
use App\Domains\Crm\Services\NextVisitSchedulingService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NextVisitController extends Controller
{
    public function __construct(
        private readonly NextVisitSchedulingService $scheduling,
        private readonly ClientThreadService $threads,
        private readonly TenantContext $tenantContext,
    ) {}

    public function upcoming(): JsonResponse
    {
        $items = $this->scheduling->listUpcomingForTenant();

        return ApiResponse::success(
            AppointmentResource::collection($items)->resolve(),
        );
    }

    /**
     * Nudge a client about booking / confirming their next visit (in-app + optional Mode A wa.me).
     */
    public function nudge(Request $request): JsonResponse
    {
        $this->scheduling->assertFeatureEnabled();

        $data = $request->validate([
            'client_id' => ['required', 'uuid'],
            'body' => ['required', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:255'],
            'include_whatsapp_deeplink' => ['nullable', 'boolean'],
        ]);

        $client = Client::query()->findOrFail($data['client_id']);
        if ($client->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['client_id' => ['Client not found.']]);
        }

        $tenant = $this->tenantContext->get();
        $includeWa = (bool) ($data['include_whatsapp_deeplink'] ?? true);
        $deeplink = null;
        if ($includeWa && $tenant) {
            $deeplink = $this->threads->buildWaMeLinkForTenant($tenant, $data['body']);
        }

        $message = $this->threads->postOutbound($client, [
            'channel' => $deeplink
                ? ClientThreadMessage::CHANNEL_WHATSAPP_MODE_A
                : ClientThreadMessage::CHANNEL_IN_APP,
            'subject' => $data['subject'] ?? 'Next visit nudge',
            'body' => $data['body'],
            'whatsapp_deeplink' => $deeplink,
            'metadata' => [
                'source' => 'next_visit_nudge',
                'whatsapp_deeplink' => $deeplink,
            ],
        ], $request->user()?->id);

        return ApiResponse::success(
            $this->threads->serialize($message),
            'Nudge sent',
            201,
        );
    }
}
