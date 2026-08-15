<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Services\ClientService;
use App\Domains\Crm\Services\MemberPortalAuthService;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Services\TenantEntitlementService;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Services\NotificationPreferenceService;
use App\Domains\Staff\Models\StaffAbsence;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Domains\Staff\Models\StaffProfile;
use App\Shared\Tenancy\TenantContext;
use App\Shared\Support\PhoneNormalizer;
use App\Shared\Support\PublicStorageUrl;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Public online booking orchestration — reuses AppointmentBookingService + scheduling rules.
 */
class OnlineBookingService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BookingScopeValidator $scope,
        private readonly AppointmentBookingService $appointments,
        private readonly AppointmentSchedulingValidator $schedulingValidator,
        private readonly ClientService $clients,
        private readonly MemberPortalAuthService $memberPortal,
        private readonly TenantEntitlementService $entitlements,
        private readonly StaffSosAlertService $staffSos,
        private readonly NotificationPreferenceService $notificationPreferences,
    ) {}

    /**
     * @return array{
     *     tenant: array<string, mixed>,
     *     locations: Collection,
     *     services: Collection,
     *     providers: Collection,
     *     ai_hairstyle_landing: bool
     * }
     */
    public function catalog(?string $locationId = null): array
    {
        $tenant = $this->tenantContext->get();

        $locations = Location::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'timezone', 'address', 'contact_phone', 'opening_hours']);

        $services = BookableService::query()
            ->where('is_active', true)
            ->where('is_bookable_online', true)
            ->orderBy('name')
            ->get();

        $providersQuery = TeamMember::query()
            ->where('is_active', true)
            ->whereHas('staffProfile', fn ($q) => $q->where('is_bookable', true))
            ->with(['staffProfile', 'operatingLocations:id,name'])
            ->orderBy('display_name');

        if ($locationId !== null) {
            $this->scope->findLocation($locationId);
            $providersQuery->where(function ($q) use ($locationId) {
                $q->where('primary_location_id', $locationId)
                    ->orWhereHas('operatingLocations', fn ($lq) => $lq->where('locations.id', $locationId))
                    ->orWhereDoesntHave('operatingLocations');
            });
        }

        return [
            'tenant' => [
                'id' => $tenant?->id,
                'name' => $tenant?->name,
                'slug' => $tenant?->slug,
                'owner_whatsapp' => $tenant?->owner_whatsapp,
                'branding' => $this->publicBranding($tenant?->getBranding() ?? []),
            ],
            'locations' => $locations,
            'services' => $services,
            'providers' => $providersQuery->get(),
            'ai_hairstyle_landing' => $this->entitlements->isEnabled($tenant, 'ai_hairstyle'),
        ];
    }

    /**
     * @param  array<string, mixed>  $branding
     * @return array<string, mixed>
     */
    private function publicBranding(array $branding): array
    {
        foreach (['logo_url', 'hero_emblem_url', 'hero_image_url'] as $key) {
            if (isset($branding[$key]) && is_string($branding[$key])) {
                $branding[$key] = PublicStorageUrl::normalize($branding[$key]);
            }
        }

        return $branding;
    }

    /**
     * @return array<int, array{starts_at: string, ends_at: string, team_member_id: string, location_id: string}>
     */
    public function availableSlots(
        string $bookingServiceId,
        string $locationId,
        string $date,
        ?string $teamMemberId = null,
    ): array {
        $service = $this->scope->findBookableService($bookingServiceId);
        if (! $service->is_active || ! $service->is_bookable_online) {
            throw ValidationException::withMessages([
                'booking_service_id' => ['Service is not available for online booking.'],
            ]);
        }

        $this->scope->findLocation($locationId);
        $location = Location::query()->findOrFail($locationId);
        $day = Carbon::parse($date)->startOfDay();
        if ($day->lt(Carbon::today())) {
            throw ValidationException::withMessages([
                'date' => ['Cannot book slots in the past.'],
            ]);
        }

        $storeWindows = $location->openingWindowsForDay($day->dayOfWeekIso);
        if ($storeWindows === []) {
            return [];
        }

        $duration = max(1, (int) $service->duration_minutes);
        $providers = $this->resolveProvidersForSlotSearch($locationId, $teamMemberId);
        $slots = [];
        $dayStart = $day->copy()->startOfDay();
        $dayEnd = $day->copy()->endOfDay();

        foreach ($providers as $provider) {
            $profile = StaffProfile::query()->where('team_member_id', $provider->id)->first();
            if ($profile === null || ! $profile->is_bookable || ! $provider->is_active) {
                continue;
            }

            $rules = StaffAvailabilityRule::query()
                ->where('team_member_id', $provider->id)
                ->where('location_id', $locationId)
                ->where('day_of_week', $day->dayOfWeekIso)
                ->where('is_active', true)
                ->get();

            if ($rules->isEmpty()) {
                continue;
            }

            // Prefetch day conflicts once per provider (slot search only — book() still fully validates).
            $providerBusy = Appointment::query()
                ->where('team_member_id', $provider->id)
                ->whereNotIn('status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW])
                ->where(function ($q) {
                    $q->whereNull('walk_in_stage')
                        ->orWhere('walk_in_stage', '!=', Appointment::WALK_IN_WAITING);
                })
                ->where('starts_at', '<', $dayEnd)
                ->where('ends_at', '>', $dayStart)
                ->get(['starts_at', 'ends_at']);

            $workspaceIds = $rules->pluck('workspace_id')->filter()->unique()->values();
            $workspaceBusy = $workspaceIds->isEmpty()
                ? collect()
                : Appointment::query()
                    ->whereIn('workspace_id', $workspaceIds->all())
                    ->whereNotIn('status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW])
                    ->where(function ($q) {
                        $q->whereNull('walk_in_stage')
                            ->orWhere('walk_in_stage', '!=', Appointment::WALK_IN_WAITING);
                    })
                    ->where('starts_at', '<', $dayEnd)
                    ->where('ends_at', '>', $dayStart)
                    ->get(['workspace_id', 'starts_at', 'ends_at']);

            $absences = StaffAbsence::query()
                ->where('team_member_id', $provider->id)
                ->where('status', StaffAbsence::STATUS_ACTIVE)
                ->where('starts_at', '<', $dayEnd)
                ->where('ends_at', '>', $dayStart)
                ->get(['starts_at', 'ends_at']);

            foreach ($rules as $rule) {
                $cursor = Carbon::parse($day->toDateString().' '.$rule->start_time);
                $windowEnd = Carbon::parse($day->toDateString().' '.$rule->end_time);

                while ($cursor->copy()->addMinutes($duration)->lte($windowEnd)) {
                    $startsAt = $cursor->copy();
                    $endsAt = $cursor->copy()->addMinutes($duration);

                    if ($startsAt->gt(now())) {
                        $blocked = $providerBusy->contains(
                            fn ($appt) => $appt->starts_at < $endsAt && $appt->ends_at > $startsAt
                        ) || $absences->contains(
                            fn ($row) => $row->starts_at < $endsAt && $row->ends_at > $startsAt
                        );

                        if (! $blocked && $rule->workspace_id !== null) {
                            $blocked = $workspaceBusy->contains(
                                fn ($appt) => $appt->workspace_id === $rule->workspace_id
                                    && $appt->starts_at < $endsAt
                                    && $appt->ends_at > $startsAt
                            );
                        }

                        if (! $blocked) {
                            $slots[] = [
                                'starts_at' => $startsAt->toIso8601String(),
                                'ends_at' => $endsAt->toIso8601String(),
                                'team_member_id' => $provider->id,
                                'location_id' => $locationId,
                                'workspace_id' => $rule->workspace_id,
                                'provider_name' => $provider->display_name,
                            ];
                        }
                    }

                    $cursor->addMinutes($duration);
                }
            }
        }

        usort($slots, fn ($a, $b) => strcmp($a['starts_at'], $b['starts_at']));

        return array_slice($slots, 0, 48);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function book(array $payload): Appointment
    {
        $service = $this->scope->findBookableService($payload['booking_service_id']);
        if (! $service->is_active || ! $service->is_bookable_online) {
            throw ValidationException::withMessages([
                'booking_service_id' => ['Service is not available for online booking.'],
            ]);
        }

        $tier = $payload['pricing_tier'] ?? 'regular';
        if (! in_array($tier, ['regular', 'membership', 'loyalty'], true)) {
            throw ValidationException::withMessages([
                'pricing_tier' => ['Invalid pricing tier.'],
            ]);
        }

        $client = $this->resolveClient($payload);
        $priced = $this->resolveTierPrice($service, $tier, $payload['member_token'] ?? null, $client);
        $client = $priced['client'];

        if (filter_var($payload['whatsapp_opt_in'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && filled(trim((string) ($client->phone ?? $payload['phone'] ?? '')))
        ) {
            $this->notificationPreferences->update($client, [
                'allow_whatsapp' => true,
                'preferred_channel' => NotificationChannel::WHATSAPP,
            ]);
        }

        $appointment = $this->appointments->create([
            'client_id' => $client->id,
            'team_member_id' => $payload['team_member_id'],
            'location_id' => $payload['location_id'],
            'workspace_id' => $payload['workspace_id'] ?? null,
            'starts_at' => $payload['starts_at'],
            'status' => Appointment::STATUS_CONFIRMED,
            'booking_source' => Appointment::SOURCE_ONLINE,
            'client_notes' => $payload['client_notes'] ?? null,
            'services' => [
                [
                    'booking_service_id' => $service->id,
                    'sort_order' => 0,
                    'price_cents' => $priced['price_cents'],
                    'pricing_tier' => $priced['pricing_tier'],
                ],
            ],
        ]);

        try {
            $this->staffSos->raiseForNewOnlineBooking($appointment);
        } catch (\Throwable) {
            // SOS must not block a successful online booking.
        }

        return $appointment;
    }

    /**
     * @return array{price_cents: int|null, pricing_tier: string, client: Client}
     */
    private function resolveTierPrice(
        BookableService $service,
        string $tier,
        ?string $memberToken,
        Client $client,
    ): array {
        if ($tier === 'regular') {
            return [
                'price_cents' => $service->base_price_cents,
                'pricing_tier' => 'regular',
                'client' => $client,
            ];
        }

        $memberClient = $this->memberPortal->findClientByToken($memberToken);

        if ($memberClient === null) {
            throw ValidationException::withMessages([
                'pricing_tier' => ['Please log in to the membership app to use '.$tier.' pricing.'],
            ]);
        }

        if ($this->clientsMatchForMemberPricing($memberClient, $client)) {
            $client = $memberClient;
        } else {
            throw ValidationException::withMessages([
                'pricing_tier' => ['Booking details must match your membership login.'],
            ]);
        }

        $benefits = $this->memberPortal->benefitsFor($client);

        if ($tier === 'membership') {
            if (! $benefits['has_membership']) {
                throw ValidationException::withMessages([
                    'pricing_tier' => ['Membership pricing requires an active membership. Ask the salon to enrol you.'],
                ]);
            }
            if ($service->membership_price_cents === null) {
                throw ValidationException::withMessages([
                    'pricing_tier' => ['Membership pricing is not available for this service.'],
                ]);
            }

            return [
                'price_cents' => $service->membership_price_cents,
                'pricing_tier' => 'membership',
                'client' => $client,
            ];
        }

        if (! $benefits['loyalty_eligible']) {
            throw ValidationException::withMessages([
                'pricing_tier' => ['Loyalty pricing is not available for your account yet.'],
            ]);
        }
        if ($service->loyalty_price_cents === null) {
            throw ValidationException::withMessages([
                'pricing_tier' => ['Loyalty pricing is not available for this service.'],
            ]);
        }

        return [
            'price_cents' => $service->loyalty_price_cents,
            'pricing_tier' => 'loyalty',
            'client' => $client,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveClient(array $payload): Client
    {
        $phone = PhoneNormalizer::normalize($payload['phone'] ?? null);
        if (! PhoneNormalizer::isValid($phone)) {
            throw ValidationException::withMessages([
                'phone' => ['A valid phone number is required.'],
            ]);
        }

        $email = isset($payload['email']) && filled($payload['email'])
            ? strtolower(trim((string) $payload['email']))
            : null;
        $firstName = isset($payload['first_name']) ? trim((string) $payload['first_name']) : '';
        $lastName = isset($payload['last_name']) ? trim((string) $payload['last_name']) : '';

        $existing = Client::query()
            ->where('phone_normalized', $phone)
            ->first();

        if ($existing !== null) {
            $updates = [];
            if ($email !== null && empty($existing->email)) {
                $updates['email'] = $email;
            }
            if ($firstName !== '' && empty($existing->first_name)) {
                $updates['first_name'] = $firstName;
            }
            if ($lastName !== '' && empty($existing->last_name)) {
                $updates['last_name'] = $lastName;
            }
            if ($existing->phone !== $phone) {
                $updates['phone'] = $phone;
            }
            if ($updates !== []) {
                $existing = $this->clients->update($existing, $updates);
            }

            return $existing->fresh();
        }

        return $this->clients->create([
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'email' => $email,
            'phone' => $phone,
            'primary_location_id' => $payload['location_id'],
            'is_active' => true,
        ]);
    }

    private function clientsMatchForMemberPricing(Client $memberClient, Client $bookingClient): bool
    {
        if ($memberClient->id === $bookingClient->id) {
            return true;
        }

        $memberPhone = PhoneNormalizer::normalize($memberClient->phone);
        $bookingPhone = PhoneNormalizer::normalize($bookingClient->phone);
        if ($memberPhone !== '' && $memberPhone === $bookingPhone) {
            return true;
        }

        $memberEmail = strtolower(trim((string) ($memberClient->email ?? '')));
        $bookingEmail = strtolower(trim((string) ($bookingClient->email ?? '')));

        return $memberEmail !== '' && $memberEmail === $bookingEmail;
    }

    /**
     * @return Collection<int, TeamMember>
     */
    private function resolveProvidersForSlotSearch(string $locationId, ?string $teamMemberId): Collection
    {
        if ($teamMemberId !== null) {
            $provider = $this->scope->findTeamMember($teamMemberId);
            $profile = StaffProfile::query()->where('team_member_id', $provider->id)->first();
            if (! $provider->is_active || $profile === null || ! $profile->is_bookable) {
                throw ValidationException::withMessages([
                    'team_member_id' => ['Provider is not bookable online.'],
                ]);
            }

            return collect([$provider]);
        }

        return TeamMember::query()
            ->where('is_active', true)
            ->whereHas('staffProfile', fn ($q) => $q->where('is_bookable', true))
            ->where(function ($q) use ($locationId) {
                $q->where('primary_location_id', $locationId)
                    ->orWhereHas('operatingLocations', fn ($lq) => $lq->where('locations.id', $locationId))
                    ->orWhereDoesntHave('operatingLocations');
            })
            ->get();
    }
}
