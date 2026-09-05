<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\DTOs\DateRange;
use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\ReservationPaymentDocument;
use App\Domains\Memberships\Enums\ClientMembershipStatus;
use App\Domains\Payments\Enums\PaymentTransactionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Business Performance Intelligence — action-oriented KPIs.
 *
 * Formulas are canonical in docs/BUSINESS_PERFORMANCE_INTELLIGENCE_METRICS.md.
 * Read-only; reuses RevenueAnalyticsService for payment/POS math.
 */
class BusinessPerformanceIntelligenceService
{
    public const TYPICAL_CYCLE_DAYS = 35;

    public const DUE_SOON_WINDOW_DAYS = 7;

    public const OVERDUE_GRACE_DAYS = 7;

    public const MEMBERSHIP_RENEWAL_DAYS = 14;

    public const PACKAGE_EXPIRY_DAYS = 14;

    public const FAILED_PAYMENT_LOOKBACK_DAYS = 30;

    public const PENDING_DEPOSIT_LOOKBACK_DAYS = 7;

    public function __construct(
        private readonly RevenueAnalyticsService $revenueAnalytics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(string $tenantId, ?string $locationId = null, ?string $providerId = null): array
    {
        $now = Carbon::now();
        $today = new DateRange($now->copy()->startOfDay(), $now->copy()->endOfDay());
        $week = new DateRange($now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay(), $now->copy()->endOfDay());
        $month = new DateRange($now->copy()->startOfMonth()->startOfDay(), $now->copy()->endOfDay());

        $servedToday = $this->servedBreakdown($tenantId, $today, $locationId, $providerId);
        $servedWeek = $this->servedBreakdown($tenantId, $week, $locationId, $providerId);
        $servedMonth = $this->servedBreakdown($tenantId, $month, $locationId, $providerId);

        $newReturning = $this->newVsReturning($tenantId, $month, $locationId, $providerId);
        $payments = $this->revenueAnalytics->paymentsMetrics($tenantId, $month, $locationId, $providerId);
        $pos = $this->revenueAnalytics->posMetrics($tenantId, $month, $locationId, $providerId);
        $totalRevenue = (int) $payments['net_collected_cents'] + (int) $pos['gross_sales_cents'];
        $customersMonth = $servedMonth['identified_count'] + $servedMonth['anonymous_count'];
        $averageSpend = $customersMonth > 0 ? intdiv($totalRevenue, $customersMonth) : 0;

        $walkIns = $this->countBySource($tenantId, $month, Appointment::SOURCE_WALK_IN, $locationId, $providerId);
        $online = $this->countBySource($tenantId, $month, Appointment::SOURCE_ONLINE, $locationId, $providerId);

        $identifiedMonth = $servedMonth['identified_count'];
        $anonymousMonth = $servedMonth['anonymous_count'];
        $visibilityDenom = max(1, $identifiedMonth + $anonymousMonth);
        $visibilityRate = round($identifiedMonth / $visibilityDenom, 4);
        $returningRate = round($newReturning['returning'] / max(1, $identifiedMonth), 4);
        $firstTimeRate = round($newReturning['new'] / max(1, $identifiedMonth), 4);

        $avgIdentifiedSpend = $identifiedMonth > 0 ? intdiv($totalRevenue, $identifiedMonth) : $averageSpend;
        $opportunity = $this->repeatOpportunity($tenantId, $avgIdentifiedSpend, $locationId, $providerId);
        $joinersWithoutVisit = $this->crmJoinersWithoutVisit($tenantId, $locationId);

        $failedPayments = $this->failedPaymentsCount($tenantId, $locationId, $providerId);
        $pendingDeposits = $this->pendingDepositsCount($tenantId, $locationId, $providerId);
        $pendingDocs = $this->pendingPaymentDocumentsCount($tenantId);
        $renewals = $this->membershipsNeedingRenewal($tenantId);
        $expiringPackages = $this->expiringPackagesCount($tenantId);

        $businessPerformance = [
            'customers_served_today' => $servedToday['identified_count'] + $servedToday['anonymous_count'],
            'customers_served_week' => $servedWeek['identified_count'] + $servedWeek['anonymous_count'],
            'customers_served_month' => $customersMonth,
            'total_revenue_cents' => $totalRevenue,
            'average_spend_cents' => $averageSpend,
            'new_customers_month' => $newReturning['new'],
            'returning_customers_month' => $newReturning['returning'],
            'walk_ins_month' => $walkIns,
            'online_bookings_month' => $online,
        ];

        $customerIntelligence = [
            'identified_served_month' => $identifiedMonth,
            'anonymous_served_month' => $anonymousMonth,
            'visibility_rate' => $visibilityRate,
            'returning_rate' => $returningRate,
            'first_time_rate' => $firstTimeRate,
            'unidentified_gap_count' => $anonymousMonth,
        ];

        $repeatRevenue = [
            'typical_cycle_days' => self::TYPICAL_CYCLE_DAYS,
            'avg_identified_spend_cents' => $avgIdentifiedSpend,
            'clients_due_soon' => $opportunity['due_soon'],
            'clients_overdue' => $opportunity['overdue'],
            'estimated_opportunity_cents' => $opportunity['estimated_opportunity_cents'],
            'crm_joiners_without_visit' => $joinersWithoutVisit,
        ];

        $actionCenter = [
            $this->task(
                'capture_anonymous_contacts',
                'Capture anonymous contacts',
                $anonymousMonth,
                '/admin/settings/crm-join-qr',
                'Promote the join QR so desk visits become identifiable customers.',
            ),
            $this->task(
                'send_rebook_reminders',
                'Send rebook reminders',
                $opportunity['due_soon'] + $opportunity['overdue'],
                '/admin/marketing',
                'Use Marketing win-back / rebook automations for due and overdue clients.',
            ),
            $this->task(
                'nudge_crm_joiners',
                'Nudge CRM joiners',
                $joinersWithoutVisit,
                '/admin/next-visit',
                'CRM joiners who have not visited yet — prompt a next visit.',
            ),
            $this->task(
                'renew_memberships',
                'Renew memberships',
                $renewals,
                '/admin/memberships/subscriptions',
                'Memberships ending soon or past due.',
            ),
            $this->task(
                'review_failed_payments',
                'Review failed payments',
                $failedPayments,
                '/admin/payments/failed',
                'Failed payment transactions from the last 30 days.',
            ),
            $this->task(
                'review_pending_deposits',
                'Review pending deposits',
                $pendingDeposits,
                '/admin/bookings',
                'Appointments still waiting on deposits.',
            ),
            $this->task(
                'review_payment_documents',
                'Review payment documents',
                $pendingDocs,
                '/admin/payments/documents',
                'Reservation payment proofs awaiting confirmation.',
            ),
            $this->task(
                'expire_packages',
                'Packages expiring soon',
                $expiringPackages,
                '/admin/memberships/client-packages',
                'Client packages with remaining sessions expiring within 14 days.',
            ),
        ];

        return [
            'generated_at' => $now->toIso8601String(),
            'windows' => [
                'today' => $today->toArray(),
                'week' => $week->toArray(),
                'month' => $month->toArray(),
            ],
            'business_performance' => $businessPerformance,
            'customer_intelligence' => $customerIntelligence,
            'repeat_revenue_opportunity' => $repeatRevenue,
            'action_center' => $actionCenter,
            'business_insights' => $this->insights(
                $customerIntelligence,
                $repeatRevenue,
                $businessPerformance,
                $failedPayments,
            ),
            'metric_definitions_doc' => 'docs/BUSINESS_PERFORMANCE_INTELLIGENCE_METRICS.md',
        ];
    }

    /**
     * @return array{identified_count: int, anonymous_count: int}
     */
    private function servedBreakdown(
        string $tenantId,
        DateRange $range,
        ?string $locationId,
        ?string $providerId,
    ): array {
        $base = $this->servedAppointmentQuery($tenantId, $range, $locationId, $providerId);

        $identified = (int) (clone $base)
            ->whereNotNull('appointments.client_id')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('clients')
                    ->whereColumn('clients.id', 'appointments.client_id')
                    ->where(function ($inner) {
                        $inner->where(function ($e) {
                            $e->whereNotNull('clients.email')->where('clients.email', '!=', '');
                        })->orWhere(function ($p) {
                            $p->where(function ($n) {
                                $n->whereNotNull('clients.phone_normalized')
                                    ->where('clients.phone_normalized', '!=', '');
                            })->orWhere(function ($r) {
                                $r->whereNotNull('clients.phone')->where('clients.phone', '!=', '');
                            });
                        });
                    });
            })
            ->distinct()
            ->count('appointments.client_id');

        $anonymous = (int) (clone $base)
            ->where(function ($q) {
                $q->whereNull('appointments.client_id')
                    ->orWhereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('clients')
                            ->whereColumn('clients.id', 'appointments.client_id')
                            ->where(function ($inner) {
                                $inner->where(function ($e) {
                                    $e->whereNull('clients.email')->orWhere('clients.email', '=', '');
                                })->where(function ($p) {
                                    $p->where(function ($n) {
                                        $n->whereNull('clients.phone_normalized')
                                            ->orWhere('clients.phone_normalized', '=', '');
                                    })->where(function ($r) {
                                        $r->whereNull('clients.phone')->orWhere('clients.phone', '=', '');
                                    });
                                });
                            });
                    });
            })
            ->count();

        return [
            'identified_count' => $identified,
            'anonymous_count' => $anonymous,
        ];
    }

    /**
     * @return array{new: int, returning: int}
     */
    private function newVsReturning(
        string $tenantId,
        DateRange $month,
        ?string $locationId,
        ?string $providerId,
    ): array {
        $identifiedIds = $this->servedAppointmentQuery($tenantId, $month, $locationId, $providerId)
            ->whereNotNull('appointments.client_id')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('clients')
                    ->whereColumn('clients.id', 'appointments.client_id')
                    ->where(function ($inner) {
                        $inner->where(function ($e) {
                            $e->whereNotNull('clients.email')->where('clients.email', '!=', '');
                        })->orWhere(function ($p) {
                            $p->where(function ($n) {
                                $n->whereNotNull('clients.phone_normalized')
                                    ->where('clients.phone_normalized', '!=', '');
                            })->orWhere(function ($r) {
                                $r->whereNotNull('clients.phone')->where('clients.phone', '!=', '');
                            });
                        });
                    });
            })
            ->distinct()
            ->pluck('appointments.client_id')
            ->filter()
            ->values();

        if ($identifiedIds->isEmpty()) {
            return ['new' => 0, 'returning' => 0];
        }

        $firstServed = DB::table('appointments')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [Appointment::STATUS_CHECKED_IN, Appointment::STATUS_COMPLETED])
            ->whereIn('client_id', $identifiedIds)
            ->groupBy('client_id')
            ->selectRaw('client_id, MIN(starts_at) as first_served_at')
            ->get()
            ->keyBy('client_id');

        $new = 0;
        $returning = 0;
        foreach ($identifiedIds as $clientId) {
            $first = $firstServed->get($clientId)?->first_served_at;
            if ($first === null) {
                continue;
            }
            $firstAt = Carbon::parse($first);
            if ($firstAt->greaterThanOrEqualTo($month->from) && $firstAt->lessThanOrEqualTo($month->to)) {
                $new++;
            } else {
                $returning++;
            }
        }

        return ['new' => $new, 'returning' => $returning];
    }

    /**
     * @return array{due_soon: int, overdue: int, estimated_opportunity_cents: int}
     */
    private function repeatOpportunity(
        string $tenantId,
        int $avgIdentifiedSpend,
        ?string $locationId,
        ?string $providerId,
    ): array {
        $now = Carbon::now();
        $cycle = self::TYPICAL_CYCLE_DAYS;
        $dueSoonStart = $now->copy()->subDays($cycle)->startOfDay();
        $dueSoonEnd = $now->copy()->subDays($cycle - self::DUE_SOON_WINDOW_DAYS)->endOfDay();
        $overdueBefore = $now->copy()->subDays($cycle + self::OVERDUE_GRACE_DAYS)->endOfDay();

        $lastVisits = DB::table('appointments')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [Appointment::STATUS_CHECKED_IN, Appointment::STATUS_COMPLETED])
            ->whereNotNull('client_id')
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->when($providerId, fn ($q) => $q->where('team_member_id', $providerId))
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('clients')
                    ->whereColumn('clients.id', 'appointments.client_id')
                    ->where('clients.is_active', true)
                    ->where(function ($inner) {
                        $inner->where(function ($e) {
                            $e->whereNotNull('clients.email')->where('clients.email', '!=', '');
                        })->orWhere(function ($p) {
                            $p->where(function ($n) {
                                $n->whereNotNull('clients.phone_normalized')
                                    ->where('clients.phone_normalized', '!=', '');
                            })->orWhere(function ($r) {
                                $r->whereNotNull('clients.phone')->where('clients.phone', '!=', '');
                            });
                        });
                    });
            })
            ->groupBy('client_id')
            ->selectRaw('client_id, MAX(starts_at) as last_served_at')
            ->get();

        $futureBooked = DB::table('appointments')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED])
            ->where('starts_at', '>=', $now)
            ->whereNotNull('client_id')
            ->pluck('client_id')
            ->unique()
            ->flip();

        $dueSoon = 0;
        $overdue = 0;
        foreach ($lastVisits as $row) {
            if ($futureBooked->has($row->client_id)) {
                continue;
            }
            $last = Carbon::parse($row->last_served_at);
            if ($last->greaterThanOrEqualTo($dueSoonStart) && $last->lessThanOrEqualTo($dueSoonEnd)) {
                $dueSoon++;
            } elseif ($last->lessThanOrEqualTo($overdueBefore)) {
                $overdue++;
            }
        }

        return [
            'due_soon' => $dueSoon,
            'overdue' => $overdue,
            'estimated_opportunity_cents' => ($dueSoon + $overdue) * max(0, $avgIdentifiedSpend),
        ];
    }

    private function crmJoinersWithoutVisit(string $tenantId, ?string $locationId): int
    {
        return (int) DB::table('clients')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('membership_joined_at')
            ->where('is_active', true)
            ->when($locationId, fn ($q) => $q->where('primary_location_id', $locationId))
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('appointments')
                    ->whereColumn('appointments.client_id', 'clients.id')
                    ->whereIn('appointments.status', [
                        Appointment::STATUS_CHECKED_IN,
                        Appointment::STATUS_COMPLETED,
                    ]);
            })
            ->count();
    }

    private function countBySource(
        string $tenantId,
        DateRange $range,
        string $source,
        ?string $locationId,
        ?string $providerId,
    ): int {
        return (int) DB::table('appointments')
            ->where('tenant_id', $tenantId)
            ->where('booking_source', $source)
            ->whereBetween('starts_at', [$range->from, $range->to])
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->when($providerId, fn ($q) => $q->where('team_member_id', $providerId))
            ->when($source === Appointment::SOURCE_WALK_IN, function ($q) {
                $q->where(function ($inner) {
                    $inner->whereNull('walk_in_stage')
                        ->orWhere('walk_in_stage', '!=', Appointment::WALK_IN_WAITING);
                });
            })
            ->count();
    }

    private function failedPaymentsCount(string $tenantId, ?string $locationId, ?string $providerId): int
    {
        $from = Carbon::now()->subDays(self::FAILED_PAYMENT_LOOKBACK_DAYS)->startOfDay();

        return (int) DB::table('payment_transactions')
            ->where('tenant_id', $tenantId)
            ->where('status', PaymentTransactionStatus::FAILED)
            ->where('created_at', '>=', $from)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->when($providerId, fn ($q) => $q->where('team_member_id', $providerId))
            ->count();
    }

    private function pendingDepositsCount(string $tenantId, ?string $locationId, ?string $providerId): int
    {
        $from = Carbon::now()->subDays(self::PENDING_DEPOSIT_LOOKBACK_DAYS)->startOfDay();

        return (int) DB::table('appointments')
            ->where('tenant_id', $tenantId)
            ->where('deposit_status', Appointment::DEPOSIT_PENDING)
            ->where('starts_at', '>=', $from)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->when($providerId, fn ($q) => $q->where('team_member_id', $providerId))
            ->count();
    }

    private function pendingPaymentDocumentsCount(string $tenantId): int
    {
        return (int) DB::table('booking_reservation_payment_documents')
            ->where('tenant_id', $tenantId)
            ->where('status', ReservationPaymentDocument::STATUS_PENDING_REVIEW)
            ->count();
    }

    private function membershipsNeedingRenewal(string $tenantId): int
    {
        $horizon = Carbon::now()->addDays(self::MEMBERSHIP_RENEWAL_DAYS)->endOfDay();

        return (int) DB::table('client_memberships')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($horizon) {
                $q->where('status', ClientMembershipStatus::PAST_DUE)
                    ->orWhere(function ($inner) use ($horizon) {
                        $inner->whereIn('status', [
                            ClientMembershipStatus::ACTIVE,
                            ClientMembershipStatus::TRIALING,
                        ])->whereNotNull('current_period_ends_at')
                            ->where('current_period_ends_at', '<=', $horizon);
                    });
            })
            ->count();
    }

    private function expiringPackagesCount(string $tenantId): int
    {
        $horizon = Carbon::now()->addDays(self::PACKAGE_EXPIRY_DAYS)->endOfDay();

        return (int) DB::table('client_packages')
            ->where('tenant_id', $tenantId)
            ->where('quantity_remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $horizon)
            ->where('expires_at', '>=', Carbon::now())
            ->count();
    }

    private function servedAppointmentQuery(
        string $tenantId,
        DateRange $range,
        ?string $locationId,
        ?string $providerId,
    ) {
        return DB::table('appointments')
            ->where('appointments.tenant_id', $tenantId)
            ->whereIn('appointments.status', [
                Appointment::STATUS_CHECKED_IN,
                Appointment::STATUS_COMPLETED,
            ])
            ->whereBetween('appointments.starts_at', [$range->from, $range->to])
            ->when($locationId, fn ($q) => $q->where('appointments.location_id', $locationId))
            ->when($providerId, fn ($q) => $q->where('appointments.team_member_id', $providerId));
    }

    /**
     * @return array{key: string, label: string, count: int, href: string, why: string}
     */
    private function task(string $key, string $label, int $count, string $href, string $why): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'href' => $href,
            'why' => $why,
        ];
    }

    /**
     * @param  array<string, mixed>  $customerIntelligence
     * @param  array<string, mixed>  $repeatRevenue
     * @param  array<string, mixed>  $businessPerformance
     * @return array<int, array<string, mixed>>
     */
    private function insights(
        array $customerIntelligence,
        array $repeatRevenue,
        array $businessPerformance,
        int $failedPayments,
    ): array {
        $insights = [];
        $visibilityPct = (int) round(((float) $customerIntelligence['visibility_rate']) * 100);
        $returningPct = (int) round(((float) $customerIntelligence['returning_rate']) * 100);
        $anonymous = (int) $customerIntelligence['anonymous_served_month'];
        $opportunity = (int) $repeatRevenue['estimated_opportunity_cents'];
        $joiners = (int) $repeatRevenue['crm_joiners_without_visit'];
        $walkIns = (int) $businessPerformance['walk_ins_month'];
        $online = (int) $businessPerformance['online_bookings_month'];

        if ((float) $customerIntelligence['visibility_rate'] < 0.70) {
            $insights[] = [
                'code' => 'visibility_low',
                'severity' => 'warning',
                'message' => "Only {$visibilityPct}% of customers served this month are identifiable. Capture email/WhatsApp at the desk or promote the join QR.",
                'action_href' => '/admin/settings/crm-join-qr',
            ];
        } elseif ((float) $customerIntelligence['visibility_rate'] >= 0.85) {
            $insights[] = [
                'code' => 'visibility_strong',
                'severity' => 'success',
                'message' => "Strong customer visibility ({$visibilityPct}%). You can safely run remessaging and membership offers.",
                'action_href' => '/admin/marketing',
            ];
        }

        if ($anonymous >= 3) {
            $insights[] = [
                'code' => 'anonymous_gap',
                'severity' => 'warning',
                'message' => "{$anonymous} visits this month have no contact details — those customers cannot be rebooked automatically.",
                'action_href' => '/admin/settings/crm-join-qr',
            ];
        }

        if ($opportunity > 0) {
            $money = number_format($opportunity / 100, 2);
            $insights[] = [
                'code' => 'repeat_opportunity',
                'severity' => 'info',
                'message' => "About £{$money} in repeat visits sits with customers due soon or overdue. Open Marketing to send reminders.",
                'action_href' => '/admin/marketing',
            ];
        }

        if ($joiners >= 1) {
            $insights[] = [
                'code' => 'joiners_idle',
                'severity' => 'info',
                'message' => "{$joiners} CRM joiners have not visited yet. Nudge next-visit or share the booking link.",
                'action_href' => '/admin/next-visit',
            ];
        }

        if ($failedPayments > 0) {
            $insights[] = [
                'code' => 'failed_payments',
                'severity' => 'warning',
                'message' => "{$failedPayments} failed payments need review in Payments.",
                'action_href' => '/admin/payments/failed',
            ];
        }

        if ($walkIns > $online && $walkIns >= 5) {
            $insights[] = [
                'code' => 'walk_in_heavy',
                'severity' => 'info',
                'message' => 'Walk-ins outpace online bookings this month — push online booking QR to fill quieter slots.',
                'action_href' => '/admin/bookings',
            ];
        }

        if ((float) $customerIntelligence['returning_rate'] >= 0.40 && (int) $customerIntelligence['identified_served_month'] > 0) {
            $insights[] = [
                'code' => 'returning_healthy',
                'severity' => 'success',
                'message' => "{$returningPct}% of identifiable customers this month are returning — protect them with membership/package offers.",
                'action_href' => '/admin/memberships',
            ];
        }

        return $insights;
    }
}
