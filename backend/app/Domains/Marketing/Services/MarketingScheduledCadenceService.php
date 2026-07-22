<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Crm\Models\Client;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingMessagePurpose;
use App\Domains\Marketing\Enums\MarketingMessageStatus;
use App\Domains\Marketing\Models\MarketingMessage;
use App\Domains\Memberships\Enums\ClientMembershipStatus;
use App\Domains\Memberships\Models\ClientMembership;
use Illuminate\Support\Carbon;

/**
 * Recommended v1 scheduled marketing cadences (email + in-app).
 */
class MarketingScheduledCadenceService
{
    public const WELCOME_EMAIL_TEMPLATE = 'Sample — New client welcome';

    public const WELCOME_IN_APP_TEMPLATE = 'Sample — New client welcome (In-app)';

    public const WIN_BACK_EMAIL_TEMPLATE = 'Sample — Win-back';

    public const WIN_BACK_IN_APP_TEMPLATE = 'Sample — Win-back (In-app)';

    public const MEMBERSHIP_EMAIL_TEMPLATE = 'Sample — Membership reminder';

    public const MEMBERSHIP_IN_APP_TEMPLATE = 'Sample — Membership (In-app)';

    public const BIRTHDAY_EMAIL_TEMPLATE = 'Sample — Birthday greeting';

    public const BIRTHDAY_IN_APP_TEMPLATE = 'Sample — Birthday greeting (In-app)';

    public const MONTHLY_BOOK_EMAIL_TEMPLATE = 'Sample — Monthly book nudge';

    public const MONTHLY_BOOK_IN_APP_TEMPLATE = 'Sample — Monthly book nudge (In-app)';

    public function __construct(
        private readonly MarketingScopeValidator $scope,
        private readonly MarketingAutomationSettingService $automationSettings,
        private readonly MarketingCadenceDispatcher $dispatcher,
        private readonly MarketingDeliveryService $delivery,
        private readonly MarketingWorkflowExecutionService $executions,
        private readonly MarketingStarterTemplateService $starters,
    ) {}

    /**
     * Ensure starter templates exist, then run calendar cadences + drain queues.
     *
     * @return array<string, mixed>
     */
    public function runScheduled(Carbon $now = null): array
    {
        $now ??= now();
        $this->scope->tenantId();
        $this->starters->installSamples();

        $summary = [
            'pending_dispatched' => $this->dispatchDuePending(100),
            'workflows_processed' => $this->executions->processQueued(50),
            'win_back' => $this->runWinBackCadence(),
            'birthday' => $this->runBirthdayCadence(),
            'membership_reminder' => null,
            'monthly_book_nudge' => null,
        ];

        if ($this->isLastWeekOfMonth($now)) {
            $summary['membership_reminder'] = $this->runMembershipReminderCadence();
        }

        if ($this->isFirstWeekOfMonth($now)) {
            $summary['monthly_book_nudge'] = $this->runMonthlyBookNudgeCadence($now);
        }

        return $summary;
    }

    /**
     * @return array{email: int, in_app: int}
     */
    public function runWinBackCadence(): array
    {
        $settings = $this->automationSettings->getOrCreate();
        $inactivityDays = (int) ($settings->win_back_inactivity_days ?? 120);
        $cutoff = now()->subDays($inactivityDays);
        $futureClientIds = $this->clientsWithFutureBooking();
        $lastVisits = $this->lastCompletedVisitByClient();

        $counts = ['email' => 0, 'in_app' => 0];

        foreach ($lastVisits as $clientId => $lastVisitAt) {
            if (in_array($clientId, $futureClientIds, true)) {
                continue;
            }
            if (Carbon::parse($lastVisitAt)->greaterThan($cutoff)) {
                continue;
            }

            $client = Client::query()->with('primaryLocation')->find($clientId);
            if ($client === null) {
                continue;
            }

            if ($this->dispatcher->queueNamedTemplate(
                $client,
                self::WIN_BACK_EMAIL_TEMPLATE,
                MarketingChannel::EMAIL,
                MarketingMessagePurpose::WIN_BACK,
                14,
            )) {
                $counts['email']++;
            }

            if ($this->dispatcher->queueNamedTemplate(
                $client,
                self::WIN_BACK_IN_APP_TEMPLATE,
                MarketingChannel::IN_APP,
                MarketingMessagePurpose::WIN_BACK,
                14,
            )) {
                $counts['in_app']++;
            }
        }

        return $counts;
    }

    /**
     * @return array{email: int, in_app: int}
     */
    public function runBirthdayCadence(): array
    {
        $counts = ['email' => 0, 'in_app' => 0];
        $clients = Client::query()
            ->where('is_active', true)
            ->whereNotNull('date_of_birth')
            ->whereMonth('date_of_birth', now()->month)
            ->whereDay('date_of_birth', now()->day)
            ->with('primaryLocation')
            ->get();

        foreach ($clients as $client) {
            if ($this->dispatcher->queueNamedTemplate(
                $client,
                self::BIRTHDAY_EMAIL_TEMPLATE,
                MarketingChannel::EMAIL,
                MarketingMessagePurpose::BIRTHDAY,
                360,
            )) {
                $counts['email']++;
            }

            if ($this->dispatcher->queueNamedTemplate(
                $client,
                self::BIRTHDAY_IN_APP_TEMPLATE,
                MarketingChannel::IN_APP,
                MarketingMessagePurpose::BIRTHDAY,
                360,
            )) {
                $counts['in_app']++;
            }
        }

        return $counts;
    }

    /**
     * @return array{email: int, in_app: int}
     */
    public function runMembershipReminderCadence(): array
    {
        $counts = ['email' => 0, 'in_app' => 0];
        $memberships = ClientMembership::query()
            ->with(['client.primaryLocation', 'membershipPlan'])
            ->whereIn('status', [ClientMembershipStatus::ACTIVE, ClientMembershipStatus::TRIALING])
            ->get();

        foreach ($memberships as $membership) {
            $client = $membership->client;
            if ($client === null || ! $client->is_active) {
                continue;
            }

            // Once per client per calendar month.
            $cooldownDays = max(28, (int) now()->daysInMonth);

            if ($this->dispatcher->queueNamedTemplate(
                $client,
                self::MEMBERSHIP_EMAIL_TEMPLATE,
                MarketingChannel::EMAIL,
                MarketingMessagePurpose::MEMBERSHIP_REMINDER,
                $cooldownDays,
                true,
                $membership,
            )) {
                $counts['email']++;
            }

            if ($this->dispatcher->queueNamedTemplate(
                $client,
                self::MEMBERSHIP_IN_APP_TEMPLATE,
                MarketingChannel::IN_APP,
                MarketingMessagePurpose::MEMBERSHIP_REMINDER,
                $cooldownDays,
                true,
                $membership,
            )) {
                $counts['in_app']++;
            }
        }

        return $counts;
    }

    /**
     * Calendar book nudge for week 1 — separate from visit-based rebooking.
     *
     * @return array{email: int, in_app: int}
     */
    public function runMonthlyBookNudgeCadence(Carbon $now = null): array
    {
        $now ??= now();
        $counts = ['email' => 0, 'in_app' => 0];
        $cooldownDays = max(28, (int) $now->daysInMonth);

        $clients = Client::query()
            ->where('is_active', true)
            ->with('primaryLocation')
            ->orderBy('id')
            ->cursor();

        foreach ($clients as $client) {
            if ($this->dispatcher->queueNamedTemplate(
                $client,
                self::MONTHLY_BOOK_EMAIL_TEMPLATE,
                MarketingChannel::EMAIL,
                MarketingMessagePurpose::MONTHLY_BOOK_NUDGE,
                $cooldownDays,
            )) {
                $counts['email']++;
            }

            if ($this->dispatcher->queueNamedTemplate(
                $client,
                self::MONTHLY_BOOK_IN_APP_TEMPLATE,
                MarketingChannel::IN_APP,
                MarketingMessagePurpose::MONTHLY_BOOK_NUDGE,
                $cooldownDays,
            )) {
                $counts['in_app']++;
            }
        }

        return $counts;
    }

    public function dispatchDuePending(int $limit = 100): int
    {
        $messages = MarketingMessage::query()
            ->where('status', MarketingMessageStatus::PENDING)
            ->where(function ($q) {
                $q->whereNull('scheduled_for')
                    ->orWhere('scheduled_for', '<=', now());
            })
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->get();

        $count = 0;
        foreach ($messages as $message) {
            $this->delivery->dispatchMessage($message);
            $count++;
        }

        return $count;
    }

    public function isLastWeekOfMonth(Carbon $now): bool
    {
        return $now->day > ($now->daysInMonth - 7);
    }

    public function isFirstWeekOfMonth(Carbon $now): bool
    {
        return $now->day <= 7;
    }

    /**
     * @return array<int, string>
     */
    private function clientsWithFutureBooking(): array
    {
        return Appointment::query()
            ->whereNotNull('client_id')
            ->where('starts_at', '>', now())
            ->whereIn('status', [
                Appointment::STATUS_PENDING,
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_CHECKED_IN,
            ])
            ->distinct()
            ->pluck('client_id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function lastCompletedVisitByClient(): array
    {
        return Appointment::query()
            ->where('status', Appointment::STATUS_COMPLETED)
            ->whereNotNull('client_id')
            ->selectRaw('client_id, MAX(COALESCE(ends_at, starts_at)) as last_visit')
            ->groupBy('client_id')
            ->pluck('last_visit', 'client_id')
            ->all();
    }
}
