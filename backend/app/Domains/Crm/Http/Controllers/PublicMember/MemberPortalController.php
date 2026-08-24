<?php

namespace App\Domains\Crm\Http\Controllers\PublicMember;

use App\Domains\Crm\Services\ClientNoticeService;
use App\Domains\Crm\Services\ClientReferralService;
use App\Domains\Crm\Services\ClientVisitService;
use App\Domains\Crm\Services\MemberPortalAuthService;
use App\Domains\Crm\Services\MemberPortalExperienceService;
use App\Domains\Crm\Services\NextVisitSchedulingService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MemberPortalController extends Controller
{
    public function __construct(
        private readonly MemberPortalAuthService $portal,
        private readonly ClientVisitService $visits,
        private readonly MemberPortalExperienceService $experience,
        private readonly \App\Domains\Memberships\Services\LoyaltyLedgerService $loyaltyLedger,
        private readonly ClientNoticeService $notices,
        private readonly ClientReferralService $referrals,
        private readonly TenantContext $tenantContext,
        private readonly NextVisitSchedulingService $nextVisit,
    ) {}

    public function bootstrap(): JsonResponse
    {
        return ApiResponse::success($this->portal->bootstrap());
    }

    public function requestOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
        ]);

        $result = $this->portal->requestOtp($data['email'], $data['phone']);

        return ApiResponse::success($result, 'OTP sent to WhatsApp');
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'otp' => ['required', 'string', 'max:12'],
        ]);

        $result = $this->portal->login($data['email'], $data['phone'], $data['otp']);

        return ApiResponse::success($result, 'Logged in');
    }

    public function me(Request $request): JsonResponse
    {
        $token = $this->bearerToken($request);
        $result = $this->portal->me($token);

        return ApiResponse::success($result);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $client = $this->experience->requireClient($this->bearerToken($request));

        return ApiResponse::success($this->experience->dashboard($client));
    }

    public function visits(Request $request): JsonResponse
    {
        $client = $this->experience->requireClient($this->bearerToken($request));

        return ApiResponse::success($this->experience->visits($client));
    }

    public function loyalty(Request $request): JsonResponse
    {
        $client = $this->experience->requireClient($this->bearerToken($request));

        return ApiResponse::success($this->experience->loyalty($client));
    }

    public function offers(Request $request): JsonResponse
    {
        $this->experience->requireClient($this->bearerToken($request));

        return ApiResponse::success($this->experience->publicOffers());
    }

    public function purchase(Request $request): JsonResponse
    {
        $client = $this->experience->requireClient($this->bearerToken($request));
        $data = $request->validate([
            'offer_type' => ['required', 'string', Rule::in(['plan', 'package'])],
            'offer_id' => ['required', 'uuid'],
        ]);

        $result = $this->experience->purchase($client, $data);

        return ApiResponse::success($result, 'Purchase completed', 201);
    }

    public function gifts(Request $request): JsonResponse
    {
        $client = $this->experience->requireClient($this->bearerToken($request));

        return ApiResponse::success($this->experience->gifts($client));
    }

    public function createGift(Request $request): JsonResponse
    {
        $client = $this->experience->requireClient($this->bearerToken($request));
        $data = $request->validate([
            'client_package_id' => ['required', 'uuid'],
            'quantity' => ['nullable', 'numeric', 'min:0.001'],
            'recipient_name' => ['nullable', 'string', 'max:120'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::success($this->experience->createGift($client, $data), 'Gift code created', 201);
    }

    public function claimGift(Request $request): JsonResponse
    {
        $client = $this->experience->requireClient($this->bearerToken($request));
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        return ApiResponse::success($this->experience->claimGift($client, $data['code']), 'Gift claimed');
    }

    public function subscribePush(Request $request): JsonResponse
    {
        $client = $this->experience->requireClient($this->bearerToken($request));
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
            'keys' => ['nullable', 'array'],
            'keys.p256dh' => ['nullable', 'string', 'max:255'],
            'keys.auth' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->experience->savePushSubscription(
            $client,
            $data,
            $request->userAgent(),
        );

        return ApiResponse::success($result, 'Push subscription saved', 201);
    }

    public function unsubscribePush(Request $request): JsonResponse
    {
        $client = $this->experience->requireClient($this->bearerToken($request));
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
        ]);

        $this->experience->removePushSubscription($client, $data['endpoint']);

        return ApiResponse::success(null, 'Push subscription removed');
    }

    public function checkIn(Request $request): JsonResponse
    {
        $token = $this->bearerToken($request);
        $client = $this->portal->findClientByToken($token);
        if ($client === null) {
            throw ValidationException::withMessages([
                'token' => ['Session expired. Please log in again.'],
            ]);
        }

        $data = $request->validate([
            'location_id' => ['nullable', 'uuid'],
        ]);

        $result = $this->visits->checkInFromMember($client, $data['location_id'] ?? null);
        $balance = $this->loyaltyLedger->balanceForClient($client->id);
        $visit = $result['visit'];
        $tenant = $this->tenantContext->get();
        $promptNextVisit = $this->nextVisit->shouldPromptNextVisit($visit, $tenant);

        return ApiResponse::success([
            'visit' => $this->visits->serializeVisit($visit),
            'prompt_next_visit' => $promptNextVisit,
            'points' => $result['points'],
            'already_checked_in_today' => $result['already_checked_in_today'],
            'checked_in_today' => true,
            'open_visit' => $visit->checked_out_at === null ? $this->visits->serializeVisit($visit) : null,
            'last_visited_at' => $client->fresh()->last_visited_at?->toIso8601String(),
            'loyalty_points_balance' => $balance,
        ], $result['already_checked_in_today'] ? 'Already checked in' : 'Checked in');
    }

    public function checkOut(Request $request): JsonResponse
    {
        $token = $this->bearerToken($request);
        $client = $this->portal->findClientByToken($token);
        if ($client === null) {
            throw ValidationException::withMessages([
                'token' => ['Session expired. Please log in again.'],
            ]);
        }

        $result = $this->visits->checkOutFromMember($client);
        $visit = $result['visit'];

        return ApiResponse::success([
            'visit' => $this->visits->serializeVisit($visit),
            'open_visit' => null,
            'checked_in_today' => $this->visits->hasCheckedInToday($client),
            'last_visited_at' => $client->fresh()->last_visited_at?->toIso8601String(),
        ], 'Checked out');
    }

    public function visitStatus(Request $request): JsonResponse
    {
        $token = $this->bearerToken($request);
        $client = $this->portal->findClientByToken($token);
        if ($client === null) {
            throw ValidationException::withMessages([
                'token' => ['Session expired. Please log in again.'],
            ]);
        }

        $open = $this->visits->openVisitForClient($client);

        return ApiResponse::success([
            'checked_in_today' => $this->visits->hasCheckedInToday($client),
            'open_visit' => $open ? $this->visits->serializeVisit($open) : null,
            'last_visited_at' => $client->last_visited_at?->toIso8601String(),
        ]);
    }

    public function notices(Request $request): JsonResponse
    {
        $client = $this->experience->requireClient($this->bearerToken($request));

        return ApiResponse::success($this->notices->listForClient($client));
    }

    public function referral(Request $request): JsonResponse
    {
        $client = $this->experience->requireClient($this->bearerToken($request));

        return ApiResponse::success($this->referrals->getSharePayload($client));
    }

    public function sendReferralEmailInvites(Request $request): JsonResponse
    {
        $client = $this->experience->requireClient($this->bearerToken($request));
        $data = $request->validate([
            'emails' => ['required', 'array', 'min:1', 'max:20'],
            'emails.*' => ['required', 'email', 'max:255'],
        ]);

        $result = $this->referrals->sendEmailInvites($client, $data['emails']);

        return ApiResponse::success($result, 'Referral invites processed');
    }

    public function markNoticeRead(Request $request, string $id): JsonResponse
    {
        $client = $this->experience->requireClient($this->bearerToken($request));
        $notice = $this->notices->markRead($client, $id);

        return ApiResponse::success($this->notices->serialize($notice), 'Marked read');
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $this->bearerToken($request);
        $this->portal->logout($token);

        return ApiResponse::success(null, 'Logged out');
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
