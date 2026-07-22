<?php

namespace App\Domains\Crm\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientPortalToken;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Memberships\Enums\ClientMembershipStatus;
use App\Domains\Memberships\Models\ClientLoyaltyEntry;
use App\Domains\Memberships\Models\ClientMembership;
use App\Domains\Memberships\Models\MembershipLoyaltySetting;
use App\Domains\Memberships\Services\LoyaltyLedgerService;
use App\Domains\Crm\Services\MemberPushDispatchService;
use App\Domains\Marketing\Services\MarketingWelcomeAutomationService;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Lightweight membership PWA login for CRM clients (email + WhatsApp phone).
 */
class MemberPortalAuthService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly LoyaltyLedgerService $loyaltyLedger,
        private readonly ClientVisitService $visits,
        private readonly MemberPushDispatchService $push,
        private readonly MarketingWelcomeAutomationService $welcomeAutomation,
    ) {}

    /**
     * @return array{
     *     tenant: array{name: string, slug: string, branding: array<string, mixed>},
     *     join_path: string,
     *     book_path: string,
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
            'join_path' => '/join/'.$slug,
            'book_path' => '/book/'.$slug,
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
     * @return array{token: string, expires_at: string, client: array<string, mixed>, benefits: array{has_membership: bool, loyalty_eligible: bool}}
     */
    public function login(string $email, string $phone): array
    {
        $tenantId = $this->requireTenantId();
        $normalizedEmail = strtolower(trim($email));
        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedEmail === '' || $normalizedPhone === '') {
            throw ValidationException::withMessages([
                'email' => ['Email and WhatsApp number are required.'],
            ]);
        }

        $client = Client::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->where('is_active', true)
            ->first();

        if ($client === null || $this->normalizePhone((string) $client->phone) !== $normalizedPhone) {
            throw ValidationException::withMessages([
                'email' => ['No membership account found. Please sign up via the CRM form first.'],
                'not_registered' => ['true'],
            ]);
        }

        $plain = Str::random(48);
        $token = ClientPortalToken::query()->create([
            'tenant_id' => $tenantId,
            'client_id' => $client->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays(30),
        ]);

        try {
            $this->welcomeAutomation->sendWelcomeInAppOnce($client);
        } catch (\Throwable) {
            // Welcome in-app must not block member login.
        }

        return [
            'token' => $plain,
            'expires_at' => $token->expires_at?->toIso8601String() ?? now()->addDays(30)->toIso8601String(),
            'client' => $this->clientPayload($client),
            'benefits' => $this->benefitsFor($client),
        ];
    }

    /**
     * @return array{
     *     client: array<string, mixed>,
     *     benefits: array{has_membership: bool, loyalty_eligible: bool},
     *     checked_in_today: bool,
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

        return [
            'client' => $this->clientPayload($client),
            'benefits' => $this->benefitsFor($client),
            'checked_in_today' => $this->visits->hasCheckedInToday($client),
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

        // CRM clients can use loyalty pricing once the salon has loyalty enabled
        // and they are on the client list (or already have points).
        $loyaltyEligible = $loyaltyEnabled && ($hasLoyaltyActivity || $client->is_active);

        return [
            'has_membership' => $hasMembership,
            'loyalty_eligible' => $loyaltyEligible,
        ];
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
     * @return array{id: string, first_name: string|null, last_name: string|null, email: string|null, phone: string|null}
     */
    private function clientPayload(Client $client): array
    {
        return [
            'id' => $client->id,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'email' => $client->email,
            'phone' => $client->phone,
        ];
    }

    private function normalizePhone(string $raw): string
    {
        $trimmed = trim($raw);
        $digits = preg_replace('/[^\d+]/', '', $trimmed) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = '+'.substr($digits, 2);
        }

        return $digits;
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
