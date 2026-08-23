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
