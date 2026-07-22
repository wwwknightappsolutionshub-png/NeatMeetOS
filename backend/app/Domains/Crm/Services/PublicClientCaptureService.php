<?php

namespace App\Domains\Crm\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientConsentRecord;
use App\Domains\Crm\Models\ClientTimelineEvent;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Memberships\Enums\LoyaltyEntryDirection;
use App\Domains\Memberships\Enums\LoyaltyEntryType;
use App\Domains\Memberships\Enums\MembershipPlanStatus;
use App\Domains\Memberships\Models\MembershipLoyaltySetting;
use App\Domains\Memberships\Models\MembershipPlan;
use App\Domains\Memberships\Models\PackageProduct;
use App\Domains\Memberships\Services\LoyaltyLedgerService;
use App\Domains\Memberships\Services\LoyaltyRedemptionSettingsService;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Public QR join form — short CRM capture (WhatsApp / phone required).
 * Extension of Module 2 Client CRM for walk-up / QR lead capture.
 */
class PublicClientCaptureService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ClientService $clients,
        private readonly ClientConsentService $consents,
        private readonly ClientTimelineService $timeline,
        private readonly \App\Domains\Notifications\Services\NotificationTriggerService $notifications,
        private readonly LoyaltyLedgerService $loyaltyLedger,
        private readonly LoyaltyRedemptionSettingsService $loyaltySettings,
        private readonly ClientReferralService $referrals,
    ) {}

    /**
     * @return array{
     *     tenant: array{name: string, slug: string, branding: array<string, mixed>},
     *     locations: list<array{id: string, name: string}>,
     *     offers: array{
     *         memberships: list<array<string, mixed>>,
     *         packages: list<array<string, mixed>>,
     *         loyalty: array{enabled: bool, headline: string, description: string, points_per_redemption_block: int, value_cents_per_block: int}|null
     *     }
     * }
     */
    public function bootstrap(?string $locationId = null): array
    {
        $tenant = Tenant::query()->findOrFail($this->requireTenantId());
        $locations = Location::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($locationId !== null) {
            $this->assertLocation($locationId);
        }

        return [
            'tenant' => [
                'name' => $tenant->trading_name ?: $tenant->name,
                'slug' => $tenant->slug,
                'branding' => $tenant->getBranding() ?? [],
            ],
            'locations' => $locations->map(fn (Location $l) => [
                'id' => $l->id,
                'name' => $l->name,
            ])->all(),
            'offers' => $this->getPublicOffers(),
        ];
    }

    /**
     * @return array{
     *     memberships: list<array<string, mixed>>,
     *     packages: list<array<string, mixed>>,
     *     loyalty: array{enabled: bool, headline: string, description: string, points_per_redemption_block: int, value_cents_per_block: int}|null
     * }
     */
    public function getPublicOffers(): array
    {
        $memberships = MembershipPlan::query()
            ->where('status', MembershipPlanStatus::ACTIVE)
            ->where('is_public', true)
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'description', 'billing_frequency', 'price_cents', 'joining_fee_cents', 'included_wallet_credit_cents', 'included_loyalty_points', 'included_entitlement_quantity']);

        $packages = PackageProduct::query()
            ->where('status', MembershipPlanStatus::ACTIVE)
            ->where('is_public', true)
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'description', 'price_cents', 'included_quantity', 'expiry_days']);

        $loyaltySetting = MembershipLoyaltySetting::query()->first();
        $loyalty = null;
        if ($loyaltySetting !== null && $loyaltySetting->is_loyalty_redemption_enabled) {
            $points = (int) $loyaltySetting->points_per_redemption_block;
            $valueCents = (int) $loyaltySetting->value_cents_per_block;
            $valueLabel = number_format($valueCents / 100, 2);
            $loyalty = [
                'enabled' => true,
                'headline' => 'Loyalty rewards',
                'description' => "Earn points on every visit. Redeem {$points} points for £{$valueLabel} off your bill. Ask us to enrol you after joining.",
                'points_per_redemption_block' => $points,
                'value_cents_per_block' => $valueCents,
            ];
        }

        return [
            'memberships' => $memberships->map(fn (MembershipPlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'billing_frequency' => $plan->billing_frequency,
                'price_cents' => $plan->price_cents,
                'joining_fee_cents' => $plan->joining_fee_cents,
                'included_wallet_credit_cents' => $plan->included_wallet_credit_cents,
                'included_loyalty_points' => $plan->included_loyalty_points,
                'included_entitlement_quantity' => $plan->included_entitlement_quantity,
            ])->all(),
            'packages' => $packages->map(fn (PackageProduct $pkg) => [
                'id' => $pkg->id,
                'name' => $pkg->name,
                'description' => $pkg->description,
                'price_cents' => $pkg->price_cents,
                'included_quantity' => $pkg->included_quantity,
                'expiry_days' => $pkg->expiry_days,
            ])->all(),
            'loyalty' => $loyalty,
        ];
    }

    /**
     * @param  array{
     *     first_name: string,
     *     last_name?: string|null,
     *     whatsapp_number: string,
     *     email?: string|null,
     *     location_id?: string|null,
     *     special_event_month?: int|null,
     *     special_event_day?: int|null,
     *     special_event_label?: string|null,
     *     referral_code?: string|null,
     *     date_of_birth?: string|null
     * }  $data
     * @return array{client_id: string, created: bool, message: string}
     */
    public function capture(array $data): array
    {
        $tenantId = $this->requireTenantId();
        $phone = $this->normalizePhone($data['whatsapp_number'] ?? '');

        if ($phone === '') {
            throw ValidationException::withMessages([
                'whatsapp_number' => ['WhatsApp number is required.'],
            ]);
        }

        $locationId = $data['location_id'] ?? null;
        if ($locationId) {
            $this->assertLocation($locationId);
        }

        $thankYou = $this->thankYouMessage();
        $referralCode = isset($data['referral_code']) ? trim((string) $data['referral_code']) : null;
        if ($referralCode === '') {
            $referralCode = null;
        }

        $result = DB::transaction(function () use ($data, $phone, $locationId, $tenantId, $thankYou) {
            $existing = $this->findByPhone($tenantId, $phone);

            if ($existing !== null) {
                $patch = [];
                if (empty($existing->first_name) && ! empty($data['first_name'])) {
                    $patch['first_name'] = $data['first_name'];
                }
                if (empty($existing->last_name) && ! empty($data['last_name'])) {
                    $patch['last_name'] = $data['last_name'];
                }
                if (empty($existing->email) && ! empty($data['email'])) {
                    $patch['email'] = strtolower(trim((string) $data['email']));
                }
                if (empty($existing->primary_location_id) && $locationId) {
                    $patch['primary_location_id'] = $locationId;
                }
                if (array_key_exists('special_event_month', $data)) {
                    $patch['special_event_month'] = $data['special_event_month'];
                }
                if (array_key_exists('special_event_day', $data)) {
                    $patch['special_event_day'] = $data['special_event_day'];
                }
                if (array_key_exists('special_event_label', $data)) {
                    $patch['special_event_label'] = $data['special_event_label'];
                }
                $dob = $this->resolveDateOfBirth($data);
                if ($dob !== null && empty($existing->date_of_birth)) {
                    $patch['date_of_birth'] = $dob;
                }
                $existing->phone = $phone;
                if ($patch !== []) {
                    $this->clients->update($existing, $patch);
                } else {
                    $existing->save();
                }

                $this->timeline->record(
                    $existing->fresh(),
                    ClientTimelineEvent::EVENT_CLIENT_UPDATED,
                    'Details refreshed via CRM join QR form',
                    'WhatsApp capture',
                );

                $this->recordPrivacyConsent($existing->fresh());

                return [
                    'client_id' => $existing->id,
                    'created' => false,
                    'message' => $thankYou,
                ];
            }

            $client = $this->clients->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => ! empty($data['email']) ? strtolower(trim((string) $data['email'])) : null,
                'phone' => $phone,
                'primary_location_id' => $locationId,
                'date_of_birth' => $this->resolveDateOfBirth($data),
                'special_event_month' => $data['special_event_month'] ?? null,
                'special_event_day' => $data['special_event_day'] ?? null,
                'special_event_label' => $data['special_event_label'] ?? null,
                'preferences' => [
                    'capture_source' => 'qr_join_form',
                    'whatsapp_preferred' => true,
                ],
            ]);

            $this->recordPrivacyConsent($client);

            return [
                'client_id' => $client->id,
                'created' => true,
                'message' => $thankYou,
            ];
        });

        if ($result['created']) {
            $client = Client::query()->find($result['client_id']);
            if ($client !== null) {
                $this->awardSignupLoyaltyPoints($client);
                try {
                    $this->referrals->convertOnJoin($client, $referralCode);
                } catch (\Throwable) {
                    // Referral attribution must not block CRM capture.
                }
                $offers = $this->getPublicOffers();
                $this->notifications->safe(
                    fn () => $this->notifications->sendCrmJoinWelcome($client, $offers)
                );
            }
        }

        return $result;
    }

    private function awardSignupLoyaltyPoints(Client $client): void
    {
        try {
            $points = (int) ($this->loyaltySettings->get()->crm_join_signup_points
                ?? MembershipLoyaltySetting::DEFAULT_CRM_JOIN_SIGNUP_POINTS);
            if ($points <= 0) {
                return;
            }

            $this->loyaltyLedger->postEntry([
                'client_id' => $client->id,
                'entry_type' => LoyaltyEntryType::CRM_JOIN_SIGNUP,
                'direction' => LoyaltyEntryDirection::CREDIT,
                'points' => $points,
                'source_type' => 'crm_join',
                'source_id' => $client->id,
                'notes' => 'CRM join signup reward',
            ]);

            $this->timeline->record(
                $client,
                ClientTimelineEvent::EVENT_CLIENT_UPDATED,
                'Signup loyalty points awarded',
                $points.' loyalty points',
                ['points' => $points, 'source' => 'crm_join_signup'],
            );
        } catch (\Throwable) {
            // Loyalty award must not block CRM capture.
        }
    }

    private function thankYouMessage(): string
    {
        $tenant = Tenant::query()->findOrFail($this->requireTenantId());
        $salonName = $tenant->trading_name ?: $tenant->name;

        return 'Thank you so much for joining "'.$salonName.'". We are excited about your decision. Check your email for more details about our membership reward and how to install and use our membership app.';
    }

    private function recordPrivacyConsent(Client $client): void
    {
        try {
            $this->consents->record($client, [
                'consent_type' => ClientConsentRecord::TYPE_PRIVACY_CONTACT,
                'granted' => true,
                'source' => ClientConsentRecord::SOURCE_ONLINE_FORM,
                'metadata' => ['via' => 'qr_join_form'],
            ]);
        } catch (\Throwable) {
            // Consent must not block capture.
        }

        if (trim((string) ($client->email ?? '')) === '') {
            return;
        }

        try {
            $this->consents->record($client, [
                'consent_type' => ClientConsentRecord::TYPE_MARKETING_EMAIL,
                'granted' => true,
                'source' => ClientConsentRecord::SOURCE_ONLINE_FORM,
                'metadata' => ['via' => 'qr_join_form'],
            ]);
        } catch (\Throwable) {
            // Consent must not block capture.
        }
    }

    /**
     * Prefer explicit date_of_birth; otherwise map birthday special-event month/day.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveDateOfBirth(array $data): ?string
    {
        if (! empty($data['date_of_birth'])) {
            return (string) $data['date_of_birth'];
        }

        $month = isset($data['special_event_month']) ? (int) $data['special_event_month'] : 0;
        $day = isset($data['special_event_day']) ? (int) $data['special_event_day'] : 0;
        $label = strtolower(trim((string) ($data['special_event_label'] ?? '')));

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        if ($label === '' || ! str_contains($label, 'birthday')) {
            return null;
        }

        // Year is unused by birthday month/day matching; use a stable leap-safe year.
        try {
            return \Illuminate\Support\Carbon::create(2000, $month, $day)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function findByPhone(string $tenantId, string $normalizedPhone): ?Client
    {
        $candidates = Client::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('phone')
            ->limit(200)
            ->get();

        foreach ($candidates as $client) {
            if ($this->normalizePhone((string) $client->phone) === $normalizedPhone) {
                return $client;
            }
        }

        return null;
    }

    private function normalizePhone(string $raw): string
    {
        $trimmed = trim($raw);
        // Keep leading + and digits only.
        $digits = preg_replace('/[^\d+]/', '', $trimmed) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = '+'.substr($digits, 2);
        }

        return $digits;
    }

    private function assertLocation(string $locationId): void
    {
        $ok = Location::query()
            ->where('id', $locationId)
            ->where('is_active', true)
            ->exists();

        if (! $ok) {
            throw ValidationException::withMessages([
                'location_id' => ['Location not found for this salon.'],
            ]);
        }
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
