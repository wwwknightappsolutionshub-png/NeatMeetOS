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
use App\Domains\Notifications\Services\NotificationMailTransport;
use App\Domains\Notifications\Services\PlatformWhatsAppSettingsService;
use App\Shared\Support\PhoneNormalizer;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Membership PWA login: email + WhatsApp phone + OTP; long-lived portal tokens.
 * OTP is preferred via WhatsApp, with email as an automatic / explicit fallback.
 */
class MemberPortalAuthService
{
    public const TOKEN_TTL_DAYS = 60;

    public const OTP_TTL_MINUTES = 10;

    public const OTP_MAX_ATTEMPTS = 5;

    public const OTP_CHANNEL_WHATSAPP = 'whatsapp';

    public const OTP_CHANNEL_EMAIL = 'email';

    public const OTP_CHANNEL_AUTO = 'auto';

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly LoyaltyLedgerService $loyaltyLedger,
        private readonly ClientVisitService $visits,
        private readonly MemberPushDispatchService $push,
        private readonly MarketingWelcomeAutomationService $welcomeAutomation,
        private readonly PlatformWhatsAppSettingsService $whatsapp,
        private readonly NotificationMailTransport $mail,
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
     * @return array{
     *     sent: bool,
     *     channel: string,
     *     expires_in_seconds: int,
     *     masked_phone: string,
     *     masked_email: string,
     *     otp?: string
     * }
     */
    public function requestOtp(string $email, string $phone, string $channel = self::OTP_CHANNEL_AUTO): array
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, [self::OTP_CHANNEL_AUTO, self::OTP_CHANNEL_WHATSAPP, self::OTP_CHANNEL_EMAIL], true)) {
            $channel = self::OTP_CHANNEL_AUTO;
        }

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

        $whatsappSent = false;
        $emailSent = false;

        if ($channel === self::OTP_CHANNEL_WHATSAPP || $channel === self::OTP_CHANNEL_AUTO) {
            $whatsappSent = $this->sendOtpViaWhatsApp(
                $client->id,
                $normalizedPhone,
                $salonName,
                $plain,
            );
        }

        if (
            $channel === self::OTP_CHANNEL_EMAIL
            || ($channel === self::OTP_CHANNEL_AUTO && ! $whatsappSent)
        ) {
            $emailSent = $this->sendOtpViaEmail($client->id, $normalizedEmail, $salonName, $plain);
        }

        $deliveredVia = $whatsappSent
            ? self::OTP_CHANNEL_WHATSAPP
            : ($emailSent ? self::OTP_CHANNEL_EMAIL : null);

        if ($deliveredVia === null) {
            if (app()->runningUnitTests()) {
                $deliveredVia = $channel === self::OTP_CHANNEL_EMAIL
                    ? self::OTP_CHANNEL_EMAIL
                    : self::OTP_CHANNEL_WHATSAPP;
            } else {
                throw ValidationException::withMessages([
                    'phone' => [
                        'Could not send a login code by WhatsApp or email right now. Please try again shortly, or tap “Email me the code instead”.',
                    ],
                ]);
            }
        }

        $payload = [
            'sent' => true,
            'channel' => $deliveredVia,
            'expires_in_seconds' => self::OTP_TTL_MINUTES * 60,
            'masked_phone' => $this->maskPhone($normalizedPhone),
            'masked_email' => $this->maskEmail($normalizedEmail),
        ];

        if (app()->runningUnitTests()) {
            $payload['otp'] = $plain;
        }

        return $payload;
    }

    private function sendOtpViaWhatsApp(string $clientId, string $phone, string $salonName, string $plain): bool
    {
        $message = "*{$salonName} membership login*\n\nYour one-time code is *{$plain}*. It expires in ".self::OTP_TTL_MINUTES." minutes.\n\nIf you did not request this, ignore this message.";

        try {
            $result = $this->whatsapp->sendOperational($phone, $message, [
                'tenant_id' => $this->requireTenantId(),
                'purpose' => 'member.portal_otp',
                'client_id' => $clientId,
            ]);
            $ok = (bool) ($result['ok'] ?? false);
            if (! $ok) {
                Log::info('Member portal OTP WhatsApp not sent', [
                    'client_id' => $clientId,
                    'error' => $result['error'] ?? null,
                ]);
            }

            return $ok;
        } catch (\Throwable $e) {
            Log::warning('Member portal OTP WhatsApp failed', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function sendOtpViaEmail(string $clientId, string $email, string $salonName, string $plain): bool
    {
        $subject = "{$salonName} membership login code";
        $bodyText = "Your one-time login code is {$plain}.\n\nIt expires in ".self::OTP_TTL_MINUTES." minutes.\n\nIf you did not request this, ignore this email.";
        $bodyHtml = '<p style="margin:0 0 12px;line-height:1.5;">Your one-time login code is <strong style="font-size:20px;letter-spacing:0.08em;">'
            .e($plain)
            .'</strong>.</p>'
            .'<p style="margin:0;color:#555;line-height:1.5;">It expires in '
            .self::OTP_TTL_MINUTES
            .' minutes. If you did not request this, ignore this email.</p>';

        $result = $this->mail->send($email, $subject, $bodyText, $bodyHtml);
        if (! ($result['ok'] ?? false)) {
            Log::info('Member portal OTP email not sent', [
                'client_id' => $clientId,
                'error' => $result['error'] ?? null,
            ]);

            return false;
        }

        return true;
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
                'otp' => ['Enter the 6-digit code we sent you.'],
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
                'otp' => ['Code expired or not found. Request a new login code.'],
            ]);
        }

        if ((int) $row->attempts >= self::OTP_MAX_ATTEMPTS) {
            $row->consumed_at = now();
            $row->save();
            throw ValidationException::withMessages([
                'otp' => ['Too many attempts. Request a new login code.'],
            ]);
        }

        if (! Hash::check($otp, $row->code_hash)) {
            $row->attempts = (int) $row->attempts + 1;
            $row->save();
            throw ValidationException::withMessages([
                'otp' => ['Incorrect code. Check your WhatsApp or email and try again.'],
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

    private function maskEmail(string $email): string
    {
        $email = strtolower(trim($email));
        $at = strpos($email, '@');
        if ($at === false) {
            return '***';
        }
        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);
        $localMask = strlen($local) <= 2
            ? str_repeat('*', strlen($local))
            : substr($local, 0, 1).str_repeat('*', max(1, strlen($local) - 2)).substr($local, -1);

        return $localMask.'@'.$domain;
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
