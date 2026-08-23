<?php

namespace App\Domains\Money\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Money\Http\Resources\MoneyEntryResource;
use App\Domains\Money\Models\MoneyEntry;
use App\Domains\Payments\Enums\PaymentDirection;
use App\Domains\Payments\Enums\PaymentTransactionStatus;
use App\Shared\Audit\AuditLogger;
use App\Shared\Commerce\Enums\CheckoutStatus;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoneyNotebookService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(?string $month): array
    {
        $tenantId = $this->requireTenantId();
        $tz = $this->timezone();
        $cursor = $this->parseMonth($month, $tz);
        $from = $cursor->startOfMonth();
        $to = $cursor->endOfMonth();

        $fromCards = $this->netPaymentsCents($tenantId, $from, $to);
        $fromTill = $this->netPosCents($tenantId, $from, $to);
        $cashAdded = $this->entrySum($tenantId, MoneyEntry::KIND_CASH_IN, $from, $to);
        $spent = $this->entrySum($tenantId, MoneyEntry::KIND_SPEND, $from, $to);
        $taken = $fromCards + $fromTill + $cashAdded;
        $left = $taken - $spent;

        $cashLines = MoneyEntry::query()
            ->where('kind', MoneyEntry::KIND_CASH_IN)
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('occurred_on')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $spendLines = MoneyEntry::query()
            ->where('kind', MoneyEntry::KIND_SPEND)
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('occurred_on')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $nextMonth = $cursor->addMonth()->startOfMonth();
        $comingUp = $this->comingUp($tenantId, $cursor, $nextMonth);

        return [
            'month' => $cursor->format('Y-m'),
            'month_label' => $cursor->isoFormat('MMMM YYYY'),
            'taken_cents' => $taken,
            'spent_cents' => $spent,
            'left_cents' => $left,
            'sentence' => $this->sentence($taken, $spent, $left),
            'taken_breakdown' => [
                'from_cards_and_app_cents' => $fromCards,
                'from_till_cents' => $fromTill,
                'cash_you_added_cents' => $cashAdded,
            ],
            'cash_you_added' => MoneyEntryResource::collection($cashLines)->resolve(),
            'spends' => MoneyEntryResource::collection($spendLines)->resolve(),
            'spend_categories' => collect(MoneyEntry::spendCategoryLabels())
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values()
                ->all(),
            'coming_up' => $comingUp,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): MoneyEntry
    {
        $kind = (string) $data['kind'];
        $amountCents = $this->resolveAmountCents($data);
        if ($amountCents < 1) {
            throw ValidationException::withMessages([
                'amount_pounds' => ['Enter an amount greater than zero.'],
            ]);
        }

        $category = $kind === MoneyEntry::KIND_CASH_IN
            ? MoneyEntry::CATEGORY_CASH
            : (string) ($data['category'] ?? '');

        if ($kind === MoneyEntry::KIND_SPEND && ! in_array($category, MoneyEntry::spendCategories(), true)) {
            throw ValidationException::withMessages([
                'category' => ['Pick what the spend was for.'],
            ]);
        }

        $entry = MoneyEntry::query()->create([
            'tenant_id' => $this->requireTenantId(),
            'kind' => $kind,
            'category' => $category,
            'amount_cents' => $amountCents,
            'occurred_on' => $data['occurred_on'],
            'note' => $data['note'] ?? null,
        ]);

        $this->auditLogger->log('money.entry.created', $entry, null, $entry->only([
            'kind', 'category', 'amount_cents', 'occurred_on', 'note',
        ]));

        return $entry->fresh();
    }

    public function delete(string $id): void
    {
        $entry = MoneyEntry::query()->findOrFail($id);
        if ($entry->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['resource' => ['Resource not found.']]);
        }

        $snapshot = $entry->only(['kind', 'category', 'amount_cents', 'occurred_on']);
        $entry->delete();
        $this->auditLogger->log('money.entry.deleted', $entry, $snapshot, null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveAmountCents(array $data): int
    {
        if (array_key_exists('amount_cents', $data) && $data['amount_cents'] !== null && $data['amount_cents'] !== '') {
            return (int) $data['amount_cents'];
        }

        $pounds = $data['amount_pounds'] ?? null;
        if ($pounds === null || $pounds === '') {
            throw ValidationException::withMessages([
                'amount_pounds' => ['Enter how much in pounds.'],
            ]);
        }

        return (int) round(((float) $pounds) * 100);
    }

    private function netPaymentsCents(string $tenantId, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $collected = (int) DB::table('payment_transactions')
            ->where('tenant_id', $tenantId)
            ->where('status', PaymentTransactionStatus::SUCCEEDED)
            ->where('direction', PaymentDirection::INBOUND)
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount_cents');

        $refunds = (int) DB::table('payment_refunds')
            ->where('tenant_id', $tenantId)
            ->where('status', PaymentTransactionStatus::SUCCEEDED)
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount_cents');

        return max(0, $collected - $refunds);
    }

    private function netPosCents(string $tenantId, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $row = DB::table('commerce_checkouts')
            ->where('tenant_id', $tenantId)
            ->where('status', CheckoutStatus::COMPLETED)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(total_cents), 0) as gross, COALESCE(SUM(refunded_total_cents), 0) as refunds')
            ->first();

        $gross = (int) ($row->gross ?? 0);
        $refunds = (int) ($row->refunds ?? 0);

        return max(0, $gross - $refunds);
    }

    private function entrySum(string $tenantId, string $kind, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) MoneyEntry::query()
            ->where('tenant_id', $tenantId)
            ->where('kind', $kind)
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])
            ->sum('amount_cents');
    }

    /**
     * @return array<string, mixed>
     */
    private function comingUp(string $tenantId, CarbonImmutable $thisMonth, CarbonImmutable $nextMonth): array
    {
        $nextEnd = $nextMonth->endOfMonth();
        $bookedCents = (int) DB::table('appointment_services')
            ->join('appointments', 'appointments.id', '=', 'appointment_services.appointment_id')
            ->where('appointment_services.tenant_id', $tenantId)
            ->whereNotIn('appointments.status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW])
            ->whereBetween('appointments.starts_at', [$nextMonth, $nextEnd])
            ->sum('appointment_services.price_cents');

        $bookedVisits = (int) DB::table('appointments')
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW])
            ->whereBetween('starts_at', [$nextMonth, $nextEnd])
            ->count();

        $monthTotals = [];
        for ($i = 1; $i <= 3; $i++) {
            $m = $thisMonth->subMonths($i)->startOfMonth();
            $monthTotals[] = $this->entrySum($tenantId, MoneyEntry::KIND_SPEND, $m, $m->endOfMonth());
        }
        $monthsWithSpend = array_values(array_filter($monthTotals, fn (int $v) => $v > 0));
        $usual = $monthsWithSpend === []
            ? 0
            : intdiv((int) array_sum($monthsWithSpend), count($monthsWithSpend));

        return [
            'next_month' => $nextMonth->format('Y-m'),
            'next_month_label' => $nextMonth->isoFormat('MMMM YYYY'),
            'booked_cents' => $bookedCents,
            'booked_visits' => $bookedVisits,
            'usual_spend_cents' => $usual,
            'usual_spend_months_used' => count($monthsWithSpend),
            'rough_left_cents' => $bookedCents - $usual,
            'warning' => 'This is a rough picture from your diary, not a promise.',
        ];
    }

    /**
     * Combined cash inflow / outflow lines for a date range (cards, till, notebook entries).
     *
     * @return array{
     *     from: string,
     *     to: string,
     *     direction: string,
     *     inflow_cents: int,
     *     outflow_cents: int,
     *     net_cents: int,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function ledger(?string $from, ?string $to, ?string $direction): array
    {
        $tenantId = $this->requireTenantId();
        $tz = $this->timezone();
        [$rangeStart, $rangeEnd] = $this->parseDateRange($from, $to, $tz);
        $direction = $direction ?: 'all';
        if (! in_array($direction, ['all', 'inflow', 'outflow'], true)) {
            throw ValidationException::withMessages([
                'direction' => ['Use all, inflow, or outflow.'],
            ]);
        }

        $rows = [];

        $payments = DB::table('payment_transactions')
            ->where('tenant_id', $tenantId)
            ->where('status', PaymentTransactionStatus::SUCCEEDED)
            ->where('direction', PaymentDirection::INBOUND)
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->orderByDesc('created_at')
            ->get(['id', 'amount_cents', 'created_at']);

        foreach ($payments as $payment) {
            $rows[] = $this->ledgerRow(
                'payment:'.$payment->id,
                'inflow',
                'Cards / the app',
                (int) $payment->amount_cents,
                CarbonImmutable::parse($payment->created_at, $tz)->toDateString(),
                null,
                false,
            );
        }

        $refunds = DB::table('payment_refunds')
            ->where('tenant_id', $tenantId)
            ->where('status', PaymentTransactionStatus::SUCCEEDED)
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->orderByDesc('created_at')
            ->get(['id', 'amount_cents', 'created_at']);

        foreach ($refunds as $refund) {
            $rows[] = $this->ledgerRow(
                'refund:'.$refund->id,
                'outflow',
                'Card refund',
                (int) $refund->amount_cents,
                CarbonImmutable::parse($refund->created_at, $tz)->toDateString(),
                null,
                false,
            );
        }

        $checkouts = DB::table('commerce_checkouts')
            ->where('tenant_id', $tenantId)
            ->where('status', CheckoutStatus::COMPLETED)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$rangeStart, $rangeEnd])
            ->orderByDesc('completed_at')
            ->get(['id', 'total_cents', 'refunded_total_cents', 'completed_at']);

        foreach ($checkouts as $checkout) {
            $net = max(0, (int) $checkout->total_cents - (int) $checkout->refunded_total_cents);
            if ($net < 1) {
                continue;
            }
            $rows[] = $this->ledgerRow(
                'till:'.$checkout->id,
                'inflow',
                'Till / POS',
                $net,
                CarbonImmutable::parse($checkout->completed_at, $tz)->toDateString(),
                null,
                false,
            );
        }

        $entries = MoneyEntry::query()
            ->whereBetween('occurred_on', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->orderByDesc('occurred_on')
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $labels = MoneyEntry::spendCategoryLabels();
        foreach ($entries as $entry) {
            $isIn = $entry->kind === MoneyEntry::KIND_CASH_IN;
            $rows[] = $this->ledgerRow(
                'entry:'.$entry->id,
                $isIn ? 'inflow' : 'outflow',
                $isIn ? 'Cash I added' : ($labels[$entry->category] ?? 'Other'),
                (int) $entry->amount_cents,
                $entry->occurred_on?->toDateString() ?? $rangeStart->toDateString(),
                $entry->note,
                true,
                $entry->id,
            );
        }

        if ($direction === 'inflow') {
            $rows = array_values(array_filter($rows, fn (array $r) => $r['direction'] === 'inflow'));
        } elseif ($direction === 'outflow') {
            $rows = array_values(array_filter($rows, fn (array $r) => $r['direction'] === 'outflow'));
        }

        usort($rows, function (array $a, array $b): int {
            $dateCmp = strcmp($b['occurred_on'], $a['occurred_on']);
            if ($dateCmp !== 0) {
                return $dateCmp;
            }

            return strcmp($b['id'], $a['id']);
        });

        $inflow = 0;
        $outflow = 0;
        foreach ($rows as $row) {
            if ($row['direction'] === 'inflow') {
                $inflow += (int) $row['amount_cents'];
            } else {
                $outflow += (int) $row['amount_cents'];
            }
        }

        return [
            'from' => $rangeStart->toDateString(),
            'to' => $rangeEnd->toDateString(),
            'direction' => $direction,
            'inflow_cents' => $inflow,
            'outflow_cents' => $outflow,
            'net_cents' => $inflow - $outflow,
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ledgerRow(
        string $id,
        string $direction,
        string $source,
        int $amountCents,
        string $occurredOn,
        ?string $note,
        bool $removable,
        ?string $entryId = null,
    ): array {
        return [
            'id' => $id,
            'direction' => $direction,
            'direction_label' => $direction === 'inflow' ? 'Inflow' : 'Outflow',
            'source' => $source,
            'amount_cents' => $amountCents,
            'occurred_on' => $occurredOn,
            'note' => $note,
            'removable' => $removable,
            'entry_id' => $entryId,
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function parseDateRange(?string $from, ?string $to, string $tz): array
    {
        $now = CarbonImmutable::now($tz);
        $defaultFrom = $now->startOfMonth();
        $defaultTo = $now->endOfMonth();

        $start = $defaultFrom;
        $end = $defaultTo;

        if (is_string($from) && $from !== '') {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
                throw ValidationException::withMessages(['from' => ['Use a date like 2026-08-01.']]);
            }
            $start = CarbonImmutable::parse($from, $tz)->startOfDay();
        }

        if (is_string($to) && $to !== '') {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                throw ValidationException::withMessages(['to' => ['Use a date like 2026-08-31.']]);
            }
            $end = CarbonImmutable::parse($to, $tz)->endOfDay();
        }

        if ($start->greaterThan($end)) {
            throw ValidationException::withMessages([
                'from' => ['“From” must be on or before “To”.'],
            ]);
        }

        if ($start->diffInDays($end) > 366) {
            throw ValidationException::withMessages([
                'to' => ['Pick a range of 366 days or less.'],
            ]);
        }

        return [$start, $end];
    }

    private function sentence(int $taken, int $spent, int $left): string
    {
        if ($left < 0) {
            return sprintf(
                'You have earned %s and spent %s. You spent %s more than you earned this month (before tax).',
                $this->pounds($taken),
                $this->pounds($spent),
                $this->pounds(abs($left)),
            );
        }

        return sprintf(
            'You have earned %s and spent %s. %s is your account balance this month (before tax).',
            $this->pounds($taken),
            $this->pounds($spent),
            $this->pounds($left),
        );
    }

    private function pounds(int $cents): string
    {
        return '£'.number_format($cents / 100, 2);
    }

    private function parseMonth(?string $month, string $tz): CarbonImmutable
    {
        $now = CarbonImmutable::now($tz);
        if ($month === null || $month === '') {
            return $now->startOfMonth();
        }
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw ValidationException::withMessages([
                'month' => ['Use a month like 2026-08.'],
            ]);
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $month.'-01', $tz)->startOfMonth();
    }

    private function timezone(): string
    {
        $tenant = $this->tenantContext->get();
        if ($tenant instanceof Tenant && is_string($tenant->timezone) && $tenant->timezone !== '') {
            return $tenant->timezone;
        }

        return 'Europe/London';
    }

    private function requireTenantId(): string
    {
        $id = $this->tenantContext->id();
        if ($id === null) {
            throw ValidationException::withMessages(['tenant' => ['Tenant context is required.']]);
        }

        return $id;
    }
}
