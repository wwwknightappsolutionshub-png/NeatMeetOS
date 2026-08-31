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
use App\Shared\Support\PhoneNormalizer;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Public QR join form — membership family capture (WhatsApp + email required).
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
     *     terms_url: string,
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
            'terms_url' => rtrim((string) config('app.frontend_url'), '/').'/terms',
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
     * @param  array<string, mixed>  $data
     * @return array{client_id: string, created: bool, message: string, member_path: string}
     */
    public function capture(array $data): array
    {
        $tenantId = $this->requireTenantId();
        $phone = PhoneNormalizer::normalize($data['whatsapp_number'] ?? '');

        if (! PhoneNormalizer::isValid($phone)) {
            throw ValidationException::withMessages([
                'whatsapp_number' => ['A valid WhatsApp number is required.'],
            ]);
        }

        if (! filter_var($data['accept_terms'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw ValidationException::withMessages([
                'accept_terms' => ['You must agree to the Terms & Conditions.'],
            ]);
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => ['A valid email address is required.'],
            ]);
        }

        $preferredName = trim((string) ($data['preferred_name'] ?? $data['first_name'] ?? ''));
        if ($preferredName === '') {
            throw ValidationException::withMessages([
                'preferred_name' => ['Preferred name / nickname is required.'],
            ]);
        }

        $nextVisitDate = $this->resolveInterestedNextVisitDate($data);
        $special = $this->resolveSpecialDate($data);

        $locationId = $data['location_id'] ?? null;
        if ($locationId) {
            $this->assertLocation($locationId);
        }

        $thankYou = $this->thankYouMessage();
        $referralCode = isset($data['referral_code']) ? trim((string) $data['referral_code']) : null;
        if ($referralCode === '') {
            $referralCode = null;
        }

        $tenant = Tenant::query()->findOrFail($tenantId);
        $memberPath = '/member/'.$tenant->slug;

        $existing = $this->findByPhone($tenantId, $phone);
        $existingHadMembership = $existing !== null && $existing->membership_joined_at !== null;

        $result = DB::transaction(function () use (
            $data,
            $phone,
            $email,
            $preferredName,
            $nextVisitDate,
            $special,
            $locationId,
            $tenantId,
            $thankYou,
            $memberPath,
            $existing,
        ) {
            $joinedAt = now();

            if ($existing !== null) {
                $patch = [
                    'display_name' => $preferredName,
                    'email' => $email,
                ];
                if (empty($existing->first_name)) {
                    $patch['first_name'] = $preferredName;
                }
                if (empty($existing->last_name) && ! empty($data['last_name'])) {
                    $patch['last_name'] = trim((string) $data['last_name']);
                }
                if (empty($existing->primary_location_id) && $locationId) {
                    $patch['primary_location_id'] = $locationId;
                }
                if ($existing->membership_joined_at === null) {
                    $patch['membership_joined_at'] = $joinedAt;
                }
                $existingInterested = $existing->interested_next_visit_date
                    ? $existing->interested_next_visit_date->toDateString()
                    : null;
                if ($existingInterested !== $nextVisitDate) {
                    $patch['interested_next_visit_date'] = $nextVisitDate;
                }
                if ($special['month'] !== null) {
                    $patch['special_event_month'] = $special['month'];
                    $patch['special_event_day'] = $special['day'];
                    $patch['special_event_label'] = $special['label'];
                }
                $dob = $this->resolveDateOfBirth($data, $special);
                if ($dob !== null && empty($existing->date_of_birth)) {
                    $patch['date_of_birth'] = $dob;
                }

                $existing->phone = $phone;
                $this->clients->update($existing, $patch);

                $fresh = $existing->fresh();
                $this->timeline->record(
                    $fresh,
                    ClientTimelineEvent::EVENT_CLIENT_UPDATED,
                    'Details refreshed via membership join form',
                    'WhatsApp capture',
                );
                $this->recordJoinConsents($fresh);

                return [
                    'client_id' => $existing->id,
                    'created' => false,
                    'message' => $thankYou,
                    'member_path' => $memberPath,
                ];
            }

            $client = $this->clients->create([
                'display_name' => $preferredName,
                'first_name' => $preferredName,
                'last_name' => ! empty($data['last_name']) ? trim((string) $data['last_name']) : null,
                'email' => $email,
                'phone' => $phone,
                'primary_location_id' => $locationId,
                'date_of_birth' => $this->resolveDateOfBirth($data, $special),
                'special_event_month' => $special['month'],
                'special_event_day' => $special['day'],
                'special_event_label' => $special['label'],
                'membership_joined_at' => $joinedAt,
                'interested_next_visit_date' => $nextVisitDate,
                'preferences' => [
                    'capture_source' => 'membership_join_form',
                    'whatsapp_preferred' => true,
                ],
            ]);

            $this->recordJoinConsents($client);

            return [
                'client_id' => $client->id,
                'created' => true,
                'message' => $thankYou,
                'member_path' => $memberPath,
            ];
        });

        if ($result['created'] || ! $existingHadMembership) {
            $client = Client::query()->find($result['client_id']);
            if ($client !== null) {
                if ($result['created']) {
                    $this->awardSignupLoyaltyPoints($client);
                    try {
                        $this->referrals->convertOnJoin($client, $referralCode);
                    } catch (\Throwable) {
                        // Referral attribution must not block CRM capture.
                    }
                }
                $offers = $this->getPublicOffers();
                $this->notifications->safe(
                    fn () => $this->notifications->sendCrmJoinWelcome($client, $offers)
                );
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveInterestedNextVisitDate(array $data): string
    {
        $raw = (string) ($data['next_visit_date'] ?? '');
        if ($raw === '') {
            throw ValidationException::withMessages([
                'next_visit_date' => ['When next would you visit is required.'],
            ]);
        }

        try {
            $date = Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'next_visit_date' => ['When next would you visit is invalid.'],
            ]);
        }

        if ($date->lt(Carbon::today())) {
            throw ValidationException::withMessages([
                'next_visit_date' => ['When next would you visit cannot be in the past.'],
            ]);
        }

        return $date->toDateString();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{month: int|null, day: int|null, label: string|null}
     */
    private function resolveSpecialDate(array $data): array
    {
        $label = trim((string) ($data['special_event_label'] ?? 'Special date'));
        if ($label === '') {
            $label = 'Special date';
        }

        if (! empty($data['special_date'])) {
            try {
                $parsed = Carbon::parse((string) $data['special_date']);

                return [
                    'month' => (int) $parsed->month,
                    'day' => (int) $parsed->day,
                    'label' => $label,
                ];
            } catch (\Throwable) {
                throw ValidationException::withMessages([
                    'special_date' => ['Special date is invalid.'],
                ]);
            }
        }

        $month = isset($data['special_event_month']) ? (int) $data['special_event_month'] : 0;
        $day = isset($data['special_event_day']) ? (int) $data['special_event_day'] : 0;
        if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
            return [
                'month' => $month,
                'day' => $day,
                'label' => trim((string) ($data['special_event_label'] ?? '')) ?: $label,
            ];
        }

        return ['month' => null, 'day' => null, 'label' => null];
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

        return 'Thank you so much for joining "'.$salonName.'". We are excited about your decision. Open the membership app and log in with your email, WhatsApp number, and the OTP we send you.';
    }

    private function recordJoinConsents(Client $client): void
    {
        try {
            $this->consents->record($client, [
                'consent_type' => ClientConsentRecord::TYPE_TERMS_OF_SERVICE,
                'granted' => true,
                'source' => ClientConsentRecord::SOURCE_ONLINE_FORM,
                'metadata' => ['via' => 'membership_join_form', 'terms_url' => rtrim((string) config('app.frontend_url'), '/').'/terms'],
            ]);
        } catch (\Throwable) {
            // Consent must not block capture.
        }

        try {
            $this->consents->record($client, [
                'consent_type' => ClientConsentRecord::TYPE_PRIVACY_CONTACT,
                'granted' => true,
                'source' => ClientConsentRecord::SOURCE_ONLINE_FORM,
                'metadata' => ['via' => 'membership_join_form'],
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
                'metadata' => ['via' => 'membership_join_form'],
            ]);
        } catch (\Throwable) {
            // Consent must not block capture.
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{month: int|null, day: int|null, label: string|null}  $special
     */
    private function resolveDateOfBirth(array $data, array $special): ?string
    {
        if (! empty($data['date_of_birth'])) {
            return (string) $data['date_of_birth'];
        }

        $month = (int) ($special['month'] ?? 0);
        $day = (int) ($special['day'] ?? 0);
        $label = strtolower(trim((string) ($special['label'] ?? '')));

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        if ($label === '' || ! str_contains($label, 'birthday')) {
            return null;
        }

        try {
            return Carbon::create(2000, $month, $day)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function findByPhone(string $tenantId, string $normalizedPhone): ?Client
    {
        $exact = Client::query()
            ->where('tenant_id', $tenantId)
            ->where('phone_normalized', $normalizedPhone)
            ->first();

        if ($exact !== null) {
            return $exact;
        }

        $candidates = Client::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('phone')
            ->whereNull('phone_normalized')
            ->limit(200)
            ->get();

        foreach ($candidates as $client) {
            if (PhoneNormalizer::normalize((string) $client->phone) === $normalizedPhone) {
                return $client;
            }
        }

        return null;
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
