<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingMessagePurpose;
use App\Jobs\DispatchClientWelcomeMarketingJob;

/**
 * Welcome email (~15s after join) + welcome in-app once on first member PWA login.
 */
class MarketingWelcomeAutomationService
{
    public const PREF_WELCOME_IN_APP_SENT = 'welcome_in_app_sent_at';

    public function __construct(
        private readonly MarketingCadenceDispatcher $dispatcher,
        private readonly MarketingStarterTemplateService $starters,
    ) {}

    public function queueWelcomeEmail(Client $client, int $delaySeconds = 15): void
    {
        $tenantId = $client->tenant_id;
        DispatchClientWelcomeMarketingJob::dispatch($tenantId, $client->id)
            ->delay(now()->addSeconds(max(0, $delaySeconds)))
            ->afterCommit();
    }

    public function sendWelcomeEmailNow(Client $client): ?\App\Domains\Marketing\Models\MarketingMessage
    {
        $this->starters->installSamples();

        return $this->dispatcher->queueNamedTemplate(
            $client,
            MarketingScheduledCadenceService::WELCOME_EMAIL_TEMPLATE,
            MarketingChannel::EMAIL,
            MarketingMessagePurpose::WELCOME,
            30,
        );
    }

    /**
     * First member PWA login only — marks preference before send to prevent races.
     */
    public function sendWelcomeInAppOnce(Client $client): ?\App\Domains\Marketing\Models\MarketingMessage
    {
        $prefs = is_array($client->preferences) ? $client->preferences : [];
        if (! empty($prefs[self::PREF_WELCOME_IN_APP_SENT])) {
            return null;
        }

        if ($this->dispatcher->alreadyQueued(
            $client->id,
            MarketingMessagePurpose::WELCOME,
            MarketingChannel::IN_APP,
            3650,
        )) {
            $prefs[self::PREF_WELCOME_IN_APP_SENT] = now()->toIso8601String();
            $client->preferences = $prefs;
            $client->save();

            return null;
        }

        $prefs[self::PREF_WELCOME_IN_APP_SENT] = now()->toIso8601String();
        $client->preferences = $prefs;
        $client->save();

        $this->starters->installSamples();

        return $this->dispatcher->queueNamedTemplate(
            $client,
            MarketingScheduledCadenceService::WELCOME_IN_APP_TEMPLATE,
            MarketingChannel::IN_APP,
            MarketingMessagePurpose::WELCOME,
            3650,
        );
    }
}
