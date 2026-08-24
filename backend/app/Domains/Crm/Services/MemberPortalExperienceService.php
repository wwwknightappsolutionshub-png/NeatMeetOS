<?php

namespace App\Domains\Crm\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\MemberPushSubscription;
use App\Domains\Memberships\Enums\ClientMembershipStatus;
use App\Domains\Memberships\Enums\ClientPackageSource;
use App\Domains\Memberships\Enums\ClientPackageStatus;
use App\Domains\Memberships\Enums\MembershipPlanStatus;
use App\Domains\Memberships\Models\ClientMembership;
use App\Domains\Memberships\Models\ClientPackage;
use App\Domains\Memberships\Models\MembershipPlan;
use App\Domains\Memberships\Models\PackageProduct;
use App\Domains\Memberships\Services\ClientMembershipService;
use App\Domains\Memberships\Services\ClientPackageService;
use App\Domains\Memberships\Services\LoyaltyLedgerService;
use App\Domains\Memberships\Services\PackageGiftService;
use App\Domains\Memberships\Services\WalletLedgerService;
use App\Domains\Payments\Enums\PaymentDirection;
use App\Domains\Payments\Enums\PaymentTransactionType;
use App\Domains\Payments\Services\PaymentTransactionService;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Member PWA experience: dashboard, history, offers, purchase, gifts, push subscriptions.
 */
class MemberPortalExperienceService
{
    public function __construct(
        private readonly MemberPortalAuthService $auth,
        private readonly ClientVisitService $visits,
        private readonly LoyaltyLedgerService $loyaltyLedger,
        private readonly WalletLedgerService $walletLedger,
        private readonly ClientMembershipService $memberships,
        private readonly ClientPackageService $packages,
        private readonly PackageGiftService $gifts,
        private readonly PaymentTransactionService $payments,
        private readonly AuditLogger $auditLogger,
        private readonly ClientReferralService $referrals,
    ) {}

    public function requireClient(?string $token): Client
    {
        $client = $this->auth->findClientByToken($token);
        if ($client === null) {
            throw ValidationException::withMessages([
                'token' => ['Session expired. Please log in again.'],
            ]);
        }

        return $client;
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Client $client): array
    {
        $benefits = $this->auth->benefitsFor($client);

        return [
            'client' => [
                'id' => $client->id,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'display_name' => $client->display_name,
                'email' => $client->email,
                'phone' => $client->phone,
            ],
            'benefits' => $benefits,
            'checked_in_today' => $this->visits->hasCheckedInToday($client),
            'open_visit' => ($open = $this->visits->openVisitForClient($client))
                ? $this->visits->serializeVisit($open)
                : null,
            'last_visited_at' => $client->last_visited_at?->toIso8601String(),
            'loyalty_points_balance' => $this->loyaltyLedger->balanceForClient($client->id),
            'wallet_balance_cents' => $this->walletLedger->balanceForClient($client->id),
            'memberships' => $this->membershipSummary($client),
            'packages' => $this->packageSummary($client),
            'upcoming_appointments' => $this->upcomingAppointments($client),
            'offers' => $this->publicOffers(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function visits(Client $client): array
    {
        return $this->visits->listForClient($client->id)->map(fn ($visit) => [
            'id' => $visit->id,
            'checked_in_at' => $visit->checked_in_at?->toIso8601String(),
            'checked_out_at' => $visit->checked_out_at?->toIso8601String(),
            'source' => $visit->source,
            'loyalty_points_awarded' => (int) $visit->loyalty_points_awarded,
            'location' => $visit->location ? [
                'id' => $visit->location->id,
                'name' => $visit->location->name,
            ] : null,
        ])->all();
    }

    /**
     * @return array{balance: int, entries: list<array<string, mixed>>}
     */
    public function loyalty(Client $client): array
    {
        $entries = $this->loyaltyLedger->list(['client_id' => $client->id]);

        return [
            'balance' => $this->loyaltyLedger->balanceForClient($client->id),
            'entries' => $entries->map(fn ($entry) => [
                'id' => $entry->id,
                'entry_type' => $entry->entry_type,
                'direction' => $entry->direction,
                'points' => (int) $entry->points,
                'effective_at' => $entry->effective_at?->toIso8601String(),
                'notes' => $entry->notes,
            ])->all(),
        ];
    }

    /**
     * @return array{plans: list<array<string, mixed>>, packages: list<array<string, mixed>>}
     */
    public function publicOffers(): array
    {
        $plans = MembershipPlan::query()
            ->where('status', MembershipPlanStatus::ACTIVE)
            ->where('is_public', true)
            ->orderBy('name')
            ->get();

        $packages = PackageProduct::query()
            ->where('status', MembershipPlanStatus::ACTIVE)
            ->where('is_public', true)
            ->orderBy('name')
            ->get();

        return [
            'plans' => $plans->map(fn (MembershipPlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'price_cents' => (int) $plan->price_cents,
                'billing_frequency' => $plan->billing_frequency,
                'joining_fee_cents' => (int) $plan->joining_fee_cents,
                'included_wallet_credit_cents' => (int) ($plan->included_wallet_credit_cents ?? 0),
                'included_loyalty_points' => (int) ($plan->included_loyalty_points ?? 0),
                'best_for' => 'Ongoing membership with recurring benefits',
            ])->all(),
            'packages' => $packages->map(fn (PackageProduct $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'price_cents' => (int) $product->price_cents,
                'included_quantity' => (float) $product->included_quantity,
                'expiry_days' => $product->expiry_days,
                'best_for' => 'Prepaid visits — use until gone',
            ])->all(),
        ];
    }

    /**
     * Create a pending membership/package purchase and immediately settle via payment-link simulation,
     * then fulfill entitlements (same simulation-first pattern as Marketing/Payments).
     *
     * @param  array{offer_type: string, offer_id: string}  $data
     * @return array<string, mixed>
     */
    public function purchase(Client $client, array $data): array
    {
        $offerType = $data['offer_type'];
        $offerId = $data['offer_id'];

        if ($offerType === 'plan') {
            $plan = MembershipPlan::query()->findOrFail($offerId);
            if ($plan->status !== MembershipPlanStatus::ACTIVE || ! $plan->is_public) {
                throw ValidationException::withMessages(['offer_id' => ['This membership plan is not available for purchase.']]);
            }
            $amount = (int) $plan->price_cents + (int) $plan->joining_fee_cents;
            if ($amount <= 0) {
                throw ValidationException::withMessages(['offer_id' => ['This plan has no payable amount configured.']]);
            }

            return DB::transaction(function () use ($client, $plan, $amount) {
                $tx = $this->payments->createPaymentLink([
                    'client_id' => $client->id,
                    'transaction_type' => PaymentTransactionType::MEMBERSHIP,
                    'direction' => PaymentDirection::INBOUND,
                    'amount_cents' => $amount,
                    'currency' => 'GBP',
                    'payment_method_label' => 'Member app purchase',
                    'idempotency_key' => 'member_plan_'.$client->id.'_'.$plan->id.'_'.Str::lower(Str::random(8)),
                    'metadata' => [
                        'source' => 'member_app',
                        'offer_type' => 'plan',
                        'membership_plan_id' => $plan->id,
                    ],
                ]);

                $tx = $this->payments->markSucceeded($tx);

                $membership = $this->memberships->assign([
                    'client_id' => $client->id,
                    'membership_plan_id' => $plan->id,
                    'source' => 'online_purchase',
                    'notes' => 'Purchased via member app (payment '.$tx->id.')',
                ]);

                $this->auditLogger->log('member_purchase.plan', $membership, null, [
                    'payment_transaction_id' => $tx->id,
                    'amount_cents' => $amount,
                ]);

                $this->referrals->awardReferredBonusOnPurchase($client->fresh());

                return [
                    'payment_transaction_id' => $tx->id,
                    'status' => $tx->status,
                    'amount_cents' => $amount,
                    'offer_type' => 'plan',
                    'membership' => [
                        'id' => $membership->id,
                        'plan_name' => $plan->name,
                        'status' => $membership->status,
                    ],
                ];
            });
        }

        if ($offerType === 'package') {
            $product = PackageProduct::query()->findOrFail($offerId);
            if ($product->status !== MembershipPlanStatus::ACTIVE || ! $product->is_public) {
                throw ValidationException::withMessages(['offer_id' => ['This package is not available for purchase.']]);
            }
            $amount = (int) $product->price_cents;
            if ($amount <= 0) {
                throw ValidationException::withMessages(['offer_id' => ['This package has no payable amount configured.']]);
            }

            return DB::transaction(function () use ($client, $product, $amount) {
                $tx = $this->payments->createPaymentLink([
                    'client_id' => $client->id,
                    'transaction_type' => PaymentTransactionType::MEMBERSHIP,
                    'direction' => PaymentDirection::INBOUND,
                    'amount_cents' => $amount,
                    'currency' => 'GBP',
                    'payment_method_label' => 'Member app package purchase',
                    'idempotency_key' => 'member_pkg_'.$client->id.'_'.$product->id.'_'.Str::lower(Str::random(8)),
                    'metadata' => [
                        'source' => 'member_app',
                        'offer_type' => 'package',
                        'package_product_id' => $product->id,
                    ],
                ]);

                $tx = $this->payments->markSucceeded($tx);

                $package = $this->packages->assign([
                    'client_id' => $client->id,
                    'package_product_id' => $product->id,
                    'source' => ClientPackageSource::ONLINE_PURCHASE,
                    'notes' => 'Purchased via member app (payment '.$tx->id.')',
                ]);

                $this->auditLogger->log('member_purchase.package', $package, null, [
                    'payment_transaction_id' => $tx->id,
                    'amount_cents' => $amount,
                ]);

                $this->referrals->awardReferredBonusOnPurchase($client->fresh());

                return [
                    'payment_transaction_id' => $tx->id,
                    'status' => $tx->status,
                    'amount_cents' => $amount,
                    'offer_type' => 'package',
                    'package' => [
                        'id' => $package->id,
                        'name' => $product->name,
                        'quantity_remaining' => (float) $package->quantity_remaining,
                    ],
                ];
            });
        }

        throw ValidationException::withMessages([
            'offer_type' => ['Offer type must be plan or package.'],
        ]);
    }

    /**
     * @param  array{client_package_id: string, quantity?: float|int, recipient_name?: string, recipient_email?: string}  $data
     * @return array<string, mixed>
     */
    public function createGift(Client $client, array $data): array
    {
        $gift = $this->gifts->createFromOwnedPackage($client, $data);

        return $this->giftPayload($gift);
    }

    /**
     * @return array<string, mixed>
     */
    public function claimGift(Client $client, string $code): array
    {
        $gift = $this->gifts->claim($client, $code);

        return $this->giftPayload($gift);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function gifts(Client $client): array
    {
        return array_map(fn ($gift) => $this->giftPayload($gift), $this->gifts->listForClient($client));
    }

    /**
     * @param  array{endpoint: string, keys?: array{p256dh?: string, auth?: string}}  $data
     * @return array<string, mixed>
     */
    public function savePushSubscription(Client $client, array $data, ?string $userAgent = null): array
    {
        $endpoint = trim($data['endpoint'] ?? '');
        if ($endpoint === '') {
            throw ValidationException::withMessages(['endpoint' => ['Push endpoint is required.']]);
        }

        $hash = hash('sha256', $endpoint);
        $keys = $data['keys'] ?? [];

        $row = MemberPushSubscription::query()->updateOrCreate(
            [
                'tenant_id' => $client->tenant_id,
                'endpoint_hash' => $hash,
            ],
            [
                'client_id' => $client->id,
                'endpoint' => $endpoint,
                'p256dh' => $keys['p256dh'] ?? null,
                'auth' => $keys['auth'] ?? null,
                'user_agent' => $userAgent,
                'last_seen_at' => now(),
            ],
        );

        $this->auditLogger->log('member_push.subscribed', $row, null, [
            'client_id' => $client->id,
        ]);

        return [
            'id' => $row->id,
            'endpoint' => $row->endpoint,
            'subscribed' => true,
        ];
    }

    public function removePushSubscription(Client $client, string $endpoint): void
    {
        $hash = hash('sha256', trim($endpoint));
        $row = MemberPushSubscription::query()
            ->where('client_id', $client->id)
            ->where('endpoint_hash', $hash)
            ->first();

        if ($row !== null) {
            $this->auditLogger->log('member_push.unsubscribed', $row, $row->only(['endpoint_hash']), null);
            $row->delete();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function membershipSummary(Client $client): array
    {
        return ClientMembership::query()
            ->with('membershipPlan')
            ->where('client_id', $client->id)
            ->whereIn('status', [ClientMembershipStatus::ACTIVE, ClientMembershipStatus::TRIALING])
            ->orderByDesc('started_at')
            ->get()
            ->map(fn (ClientMembership $m) => [
                'id' => $m->id,
                'status' => $m->status,
                'plan_name' => $m->membershipPlan?->name,
                'current_period_ends_at' => $m->current_period_ends_at?->toIso8601String(),
                'next_billing_date' => $m->next_billing_date,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function packageSummary(Client $client): array
    {
        return ClientPackage::query()
            ->with('packageProduct')
            ->where('client_id', $client->id)
            ->where('status', ClientPackageStatus::ACTIVE)
            ->where('quantity_remaining', '>', 0)
            ->orderByDesc('purchased_at')
            ->get()
            ->map(fn (ClientPackage $p) => [
                'id' => $p->id,
                'name' => $p->packageProduct?->name,
                'quantity_remaining' => (float) $p->quantity_remaining,
                'quantity_total' => (float) $p->quantity_total,
                'expires_at' => $p->expires_at?->toIso8601String(),
                'source' => $p->source,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function upcomingAppointments(Client $client): array
    {
        return Appointment::query()
            ->with(['location', 'teamMember', 'serviceLines.bookableService'])
            ->where('client_id', $client->id)
            ->where('starts_at', '>=', now()->subHour())
            ->whereIn('status', [
                Appointment::STATUS_PENDING,
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_CHECKED_IN,
            ])
            ->orderBy('starts_at')
            ->limit(10)
            ->get()
            ->map(function (Appointment $a) {
                $serviceNames = $a->serviceLines
                    ?->map(fn ($line) => $line->bookableService?->name ?? $line->service_name ?? 'Service')
                    ->filter()
                    ->values()
                    ->all() ?? [];

                return [
                    'id' => $a->id,
                    'starts_at' => $a->starts_at?->toIso8601String(),
                    'ends_at' => $a->ends_at?->toIso8601String(),
                    'status' => $a->status,
                    'booking_reference' => $a->booking_reference,
                    'location_name' => $a->location?->name,
                    'provider_name' => $a->teamMember
                        ? trim(($a->teamMember->first_name ?? '').' '.($a->teamMember->last_name ?? ''))
                        : null,
                    'services' => $serviceNames,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function giftPayload(mixed $gift): array
    {
        return [
            'id' => $gift->id,
            'code' => $gift->code,
            'status' => $gift->status,
            'quantity' => (float) $gift->quantity,
            'package_name' => $gift->packageProduct?->name,
            'recipient_name' => $gift->recipient_name,
            'recipient_email' => $gift->recipient_email,
            'expires_at' => $gift->expires_at?->toIso8601String(),
            'claimed_at' => $gift->claimed_at?->toIso8601String(),
            'from_client_id' => $gift->from_client_id,
            'claimed_by_client_id' => $gift->claimed_by_client_id,
        ];
    }
}
