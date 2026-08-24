<?php

namespace App\Domains\Crm\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientPortalOtp;
use App\Domains\Crm\Models\ClientPortalToken;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Memberships\Enums\ClientMembershipStatus;
use App\Domains\Memberships\Models\ClientLoyaltyEntry;
use App\Domains\Memberships\Models\ClientMembership;
use App\Domains\Memberships\Models\MembershipLoyaltySetting;
use App\Domains\Memberships\Services\LoyaltyLedgerService;
use App\Domains\Marketing\Services\MarketingWelcomeAutomationService;
use App\Domains\Notifications\Services\PlatformWhatsAppSettingsService;
use App\Shared\Support\PhoneNormalizer;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Membership PWA login: email + WhatsApp phone + OTP; long-lived portal tokens.
 */
class MemberPortalAuthService
{
    public const TOKEN_TTL_DAYS = 60;

    public const OTP_TTL_MINUTES = 10;

    public const OTP_MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly LoyaltyLedgerService $loyaltyLedger,
        private readonly ClientVisitService $visits,
        private readonly MemberPushDispatchService $push,
        private readonly MarketingWelcomeAutomationService $welcomeAutomation,
        private readonly PlatformWhatsAppSettingsService $whatsapp,
    ) {}

    /**
     * @return array{
     *     tenant: array{name: string, slug: string, branding: array<string, mixed>},
     *     join_path: string,
     *     book_path: string,
     *     terms_url: string,
     *     locations: list<array{id: string, name: string, latitude: float|null, longitude: float|null, geofence_radius_meters: int}>
     * }
     */
    public function bootstrap(): array
    {
        $tenant = Tenant::query()->findOrFail($this->requireTenantId());
        $slug = $tenant->slug;

        $locations = Location::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'latitude', 'longitude', 'geofence_radius_meters']);

        return [
            'tenant' => [
                'name' => $tenant->trading_name ?: $tenant->name,
                'slug' => $slug,
                'branding' => $tenant->getBranding() ?? [],
            ],
            'join_path' => '/member/'.$slug,
            'book_path' => '/book/'.$slug,
            'terms_url' => rtrim((string) config('app.frontend_url'), '/').'/terms',
            'vapid_public_key' => $this->push->publicKey(),
            'push_enabled' => $this->push->isConfigured(),
            'locations' => $locations->map(fn (Location $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'latitude' => $l->latitude !== null ? (float) $l->latitude : null,
                'longitude' => $l->longitude !== null ? (float) $l->longitude : null,
                'geofence_radius_meters' => (int) ($l->geofence_radius_meters ?? 150),
            ])->all(),
        ];
    }

    /**
     * @return array{sent: bool, expires_in_seconds: int, masked_phone: string, otp?: string}
     */
    public function requestOtp(string $email, string $phone): array
    {
        $client = $this->findActiveClientOrFail($email, $phone);
        $normalizedEmail = strtolower(trim($email));
        $normalizedPhone = PhoneNormalizer::normalize($phone);
        $plain = (string) random_int(100000, 999999);

        ClientPortalOtp::query()
            ->where('client_id', $client->id)
            ->whereNull('consumed_at')
            ->delete();

        ClientPortalOtp::query()->create([
            'tenant_id' => $this->requireTenantId(),
            'client_id' => $client->id,
            'email' => $normalizedEmail,
            'phone_normalized' => $normalizedPhone,
            'code_hash' => Hash::make($plain),
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'attempts' => 0,
        ]);

        $salon = Tenant::query()->findOrFail($this->requireTenantId());
        $salonName = $salon->trading_name ?: $salon->name;
        $message = "*{$salonName} membership login*\n\nYour one-time code is *{$plain}*. It expires in ".self::OTP_TTL_MINUTES." minutes.\n\nIf you did not request this, ignore this message.";

        $sent = false;
        try {
            $result = $this->whatsapp->sendOperational($normalizedPhone, $message, [
                'tenant_id' => $this->requireTenantId(),
                'purpose' => 'member.portal_otp',
                'client_id' => $client->id,
            ]);
            $sent = (bool) ($result['ok'] ?? false);
            if (! $sent) {
                Log::info('Member portal OTP WhatsApp not sent', [
                    'client_id' => $client->id,
                    'error' => $result['error'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Member portal OTP WhatsApp failed', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);
        }

        if (! $sent && ! app()->runningUnitTests()) {
            throw ValidationException::withMessages([
                'phone' => ['Could not send a WhatsApp OTP right now. Please try again shortly.'],
            ]);
        }

        $payload = [
            'sent' => $sent || app()->runningUnitTests(),
            'expires_in_seconds' => self::OTP_TTL_MINUTES * 60,
            'masked_phone' => $this->maskPhone($normalizedPhone),
        ];

        if (app()->runningUnitTests()) {
            $payload['otp'] = $plain;
        }

        return $payload;
    }

    /**
     * @return array{token: string, expires_at: string, client: array<string, mixed>, benefits: array{has_membership: bool, loyalty_eligible: bool}}
     */
    public function login(string $email, string $phone, string $otp): array
    {
        $client = $this->findActiveClientOrFail($email, $phone);
        $normalizedEmail = strtolower(trim($email));
        $normalizedPhone = PhoneNormalizer::normalize($phone);
        $otp = trim($otp);

        if (! preg_match('/^\d{6}$/', $otp)) {
            throw ValidationException::withMessages([
                'otp' => ['Enter the 6-digit code sent to WhatsApp.'],
            ]);
        }

        $row = ClientPortalOtp::query()
            ->where('client_id', $client->id)
            ->where('email', $normalizedEmail)
            ->where('phone_normalized', $normalizedPhone)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->first();

        if ($row === null) {
            throw ValidationException::withMessages([
                'otp' => ['Code expired or not found. Request a new WhatsApp OTP.'],
            ]);
        }

        if ((int) $row->attempts >= self::OTP_MAX_ATTEMPTS) {
            $row->consumed_at = now();
            $row->save();
            throw ValidationException::withMessages([
                'otp' => ['Too many attempts. Request a new WhatsApp OTP.'],
            ]);
        }

        if (! Hash::check($otp, $row->code_hash)) {
            $row->attempts = (int) $row->attempts + 1;
            $row->save();
            throw ValidationException::withMessages([
                'otp' => ['Incorrect code. Check WhatsApp and try again.'],
            ]);
        }

        $row->consumed_at = now();
        $row->save();

        $this->maybeBackfillEmail($client, $normalizedEmail);
        $client = $client->fresh() ?? $client;

        $plain = Str::random(48);
        $token = ClientPortalToken::query()->create([
            'tenant_id' => $this->requireTenantId(),
            'client_id' => $client->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays(self::TOKEN_TTL_DAYS),
        ]);

        try {
            $this->welcomeAutomation->sendWelcomeInAppOnce($client);
        } catch (\Throwable) {
            // Welcome in-app must not block member login.
        }

        return [
            'token' => $plain,
            'expires_at' => $token->expires_at?->toIso8601String()
                ?? now()->addDays(self::TOKEN_TTL_DAYS)->toIso8601String(),
            'client' => $this->clientPayload($client),
            'benefits' => $this->benefitsFor($client),
        ];
    }

    /**
     * @return array{
     *     client: array<string, mixed>,
     *     benefits: array{has_membership: bool, loyalty_eligible: bool},
     *     checked_in_today: bool,
     *     open_visit: array<string, mixed>|null,
     *     last_visited_at: string|null,
     *     loyalty_points_balance: int
     * }
     */
    public function me(string $plainToken): array
    {
        $row = $this->resolveToken($plainToken);
        $client = Client::query()->findOrFail($row->client_id);
        $row->last_used_at = now();
        $row->save();

        $open = $this->visits->openVisitForClient($client);

        return [
            'client' => $this->clientPayload($client),
            'benefits' => $this->benefitsFor($client),
            'checked_in_today' => $this->visits->hasCheckedInToday($client),
            'open_visit' => $open ? $this->visits->serializeVisit($open) : null,
            'last_visited_at' => $client->last_visited_at?->toIso8601String(),
            'loyalty_points_balance' => $this->loyaltyLedger->balanceForClient($client->id),
        ];
    }

    public function logout(string $plainToken): void
    {
        $hash = hash('sha256', $plainToken);
        ClientPortalToken::query()
            ->where('token_hash', $hash)
            ->delete();
    }

    public function findClientByToken(?string $plainToken): ?Client
    {
        if ($plainToken === null || trim($plainToken) === '') {
            return null;
        }

        try {
            $row = $this->resolveToken($plainToken);
        } catch (ValidationException) {
            return null;
        }

        return Client::query()->find($row->client_id);
    }

    /**
     * @return array{has_membership: bool, loyalty_eligible: bool}
     */
    public function benefitsFor(Client $client): array
    {
        $hasMembership = ClientMembership::query()
            ->where('client_id', $client->id)
            ->whereIn('status', [ClientMembershipStatus::ACTIVE, ClientMembershipStatus::TRIALING])
            ->exists();

        $loyaltyEnabled = MembershipLoyaltySetting::query()
            ->where('is_loyalty_redemption_enabled', true)
            ->exists();

        $hasLoyaltyActivity = ClientLoyaltyEntry::query()
            ->where('client_id', $client->id)
            ->exists();

        $loyaltyEligible = $loyaltyEnabled && ($hasLoyaltyActivity || $client->is_active);

        return [
            'has_membership' => $hasMembership,
            'loyalty_eligible' => $loyaltyEligible,
        ];
    }

    private function findActiveClientOrFail(string $email, string $phone): Client
    {
        $tenantId = $this->requireTenantId();
        $normalizedEmail = strtolower(trim($email));
        $normalizedPhone = PhoneNormalizer::normalize($phone);

        if ($normalizedEmail === '' || ! PhoneNormalizer::isValid($normalizedPhone)) {
            throw ValidationException::withMessages([
                'email' => ['Email and WhatsApp number are required.'],
            ]);
        }

        $byEmail = Client::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->where('is_active', true)
            ->first();

        if ($byEmail !== null && PhoneNormalizer::normalize((string) $byEmail->phone) === $normalizedPhone) {
            return $byEmail;
        }

        // Legacy CRM rows may have WhatsApp but no email — allow OTP and backfill email on verify.
        $byPhone = Client::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($q) use ($normalizedPhone) {
                $q->where('phone_normalized', $normalizedPhone)
                    ->orWhere('phone', $normalizedPhone);
            })
            ->first();

        if ($byPhone === null) {
            // Fallback scan when phone_normalized was never backfilled.
            $candidates = Client::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->whereNotNull('phone')
                ->limit(200)
                ->get();
            foreach ($candidates as $candidate) {
                if (PhoneNormalizer::normalize((string) $candidate->phone) === $normalizedPhone) {
                    $byPhone = $candidate;
                    break;
                }
            }
        }

        if ($byPhone !== null) {
            $existingEmail = strtolower(trim((string) ($byPhone->email ?? '')));
            if ($existingEmail === '' || $existingEmail === $normalizedEmail) {
                return $byPhone;
            }

            throw ValidationException::withMessages([
                'email' => ['This WhatsApp number is registered with a different email. Use that email, or ask the salon to update your details.'],
            ]);
        }

        throw ValidationException::withMessages([
            'email' => ['No membership account found. Please join our membership family first.'],
            'not_registered' => ['true'],
        ]);
    }

    private function maybeBackfillEmail(Client $client, string $normalizedEmail): void
    {
        if (trim((string) ($client->email ?? '')) !== '') {
            return;
        }

        $client->email = $normalizedEmail;
        $client->save();
    }

    private function resolveToken(string $plainToken): ClientPortalToken
    {
        $hash = hash('sha256', trim($plainToken));
        $row = ClientPortalToken::query()
            ->where('token_hash', $hash)
            ->where('expires_at', '>', now())
            ->first();

        if ($row === null) {
            throw ValidationException::withMessages([
                'token' => ['Session expired. Please log in again.'],
            ]);
        }

        return $row;
    }

    /**
     * @return array{id: string, first_name: string|null, last_name: string|null, display_name: string|null, email: string|null, phone: string|null}
     */
    private function clientPayload(Client $client): array
    {
        return [
            'id' => $client->id,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'display_name' => $client->display_name,
            'email' => $client->email,
            'phone' => $client->phone,
        ];
    }

    private function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return substr($phone, 0, 3).str_repeat('*', max(0, $len - 5)).substr($phone, -2);
    }

    private function requireTenantId(): string
    {
        $id = $this->tenantContext->id();
        if ($id === null) {
            throw ValidationException::withMessages([
                'tenant' => ['Tenant context is required.'],
            ]);
        }

        return $id;
    }
}
