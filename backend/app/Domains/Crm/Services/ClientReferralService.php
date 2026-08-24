<?php

namespace App\Domains\Crm\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientReferralConversion;
use App\Domains\Crm\Models\ClientReferralEmailSend;
use App\Domains\Crm\Models\ClientReferralInvite;
use App\Domains\Crm\Models\ClientReferralSetting;
use App\Domains\Crm\Models\ClientTimelineEvent;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Memberships\Enums\LoyaltyEntryDirection;
use App\Domains\Memberships\Enums\LoyaltyEntryType;
use App\Domains\Memberships\Services\LoyaltyLedgerService;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationPurpose;
use App\Domains\Notifications\Enums\NotificationSourceType;
use App\Domains\Notifications\Services\NotificationMessageService;
use App\Domains\Notifications\Services\NotificationTriggerService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientReferralService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly LoyaltyLedgerService $loyaltyLedger,
        private readonly ClientTimelineService $timeline,
        private readonly NotificationTriggerService $notifications,
        private readonly NotificationMessageService $messages,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function settings(): ClientReferralSetting
    {
        $tenantId = $this->requireTenantId();

        $existing = ClientReferralSetting::query()->where('tenant_id', $tenantId)->first();
        if ($existing !== null) {
            return $existing;
        }

        return ClientReferralSetting::query()->create([
            'tenant_id' => $tenantId,
            'enabled' => true,
            'referrer_points' => 100,
            'referred_points' => 300,
            'share_heading' => ClientReferralSetting::DEFAULT_SHARE_HEADING,
            'share_body_template' => ClientReferralSetting::DEFAULT_SHARE_BODY,
            'thank_you_subject' => ClientReferralSetting::DEFAULT_THANK_YOU_SUBJECT,
            'thank_you_body_text' => ClientReferralSetting::DEFAULT_THANK_YOU_BODY,
            'max_email_invites_per_send' => 20,
        ]);
    }

    public function ensureInviteForClient(Client $client): ClientReferralInvite
    {
        $this->assertTenantClient($client);
        $settings = $this->settings();

        $invite = ClientReferralInvite::query()
            ->where('referrer_client_id', $client->id)
            ->where('status', ClientReferralInvite::STATUS_ACTIVE)
            ->first();

        if ($invite !== null) {
            return $invite;
        }

        $code = $this->generateUniqueCode();
        $message = $this->renderShareBody($settings, $client, $code);

        $invite = ClientReferralInvite::query()->create([
            'tenant_id' => $this->requireTenantId(),
            'referrer_client_id' => $client->id,
            'code' => $code,
            'status' => ClientReferralInvite::STATUS_ACTIVE,
            'share_message_snapshot' => $message,
        ]);

        $this->auditLogger->log('client_referral.invite_created', $invite, null, [
            'referrer_client_id' => $client->id,
            'code' => $code,
        ]);

        return $invite;
    }

    /**
     * @return array{
     *     enabled: bool,
     *     code: string,
     *     heading: string,
     *     message: string,
     *     join_url: string,
     *     book_url: string,
     *     whatsapp_url: string,
     *     referrer_points: int,
     *     referred_points: int,
     *     max_email_invites_per_send: int,
     *     stats: array{conversions: int, pending_referred_bonus: int, emails_sent: int}
     * }
     */
    public function getSharePayload(Client $client): array
    {
        $this->assertTenantClient($client);
        $settings = $this->settings();
        $invite = $this->ensureInviteForClient($client);
        $urls = $this->buildUrls($invite->code);
        $message = $this->renderShareBody($settings, $client, $invite->code, $urls['join_url']);

        if ($invite->share_message_snapshot !== $message) {
            $invite->share_message_snapshot = $message;
            $invite->save();
        }

        $conversions = ClientReferralConversion::query()
            ->where('referrer_client_id', $client->id)
            ->get();

        $emailsSent = ClientReferralEmailSend::query()
            ->where('referrer_client_id', $client->id)
            ->whereIn('status', [
                ClientReferralEmailSend::STATUS_SENT,
                ClientReferralEmailSend::STATUS_SIMULATED,
            ])
            ->count();

        return [
            'enabled' => (bool) $settings->enabled,
            'code' => $invite->code,
            'heading' => $settings->share_heading,
            'message' => $message,
            'join_url' => $urls['join_url'],
            'book_url' => $urls['book_url'],
            'whatsapp_url' => 'https://wa.me/?text='.rawurlencode($message),
            'referrer_points' => (int) $settings->referrer_points,
            'referred_points' => (int) $settings->referred_points,
            'max_email_invites_per_send' => (int) $settings->max_email_invites_per_send,
            'stats' => [
                'conversions' => $conversions->count(),
                'pending_referred_bonus' => $conversions->where('referred_bonus_pending', true)->count(),
                'emails_sent' => $emailsSent,
            ],
        ];
    }

    /**
     * @param  list<string>  $emails
     * @return array{sent: int, skipped: int, results: list<array{email: string, status: string, error: string|null}>}
     */
    public function sendEmailInvites(Client $client, array $emails): array
    {
        $this->assertTenantClient($client);
        $settings = $this->settings();

        if (! $settings->enabled) {
            throw ValidationException::withMessages([
                'referral' => ['Referral programme is not enabled for this salon.'],
            ]);
        }

        $normalized = [];
        foreach ($emails as $raw) {
            $email = strtolower(trim((string) $raw));
            if ($email === '') {
                continue;
            }
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([
                    'emails' => ["Invalid email address: {$raw}"],
                ]);
            }
            $normalized[$email] = $email;
        }

        $unique = array_values($normalized);
        $max = (int) $settings->max_email_invites_per_send;
        if (count($unique) === 0) {
            throw ValidationException::withMessages([
                'emails' => ['Provide at least one email address.'],
            ]);
        }
        if (count($unique) > $max) {
            throw ValidationException::withMessages([
                'emails' => ["You can send at most {$max} email invites at once."],
            ]);
        }

        $invite = $this->ensureInviteForClient($client);
        $payload = $this->getSharePayload($client);
        $tenant = Tenant::query()->findOrFail($this->requireTenantId());
        $salonName = $tenant->trading_name ?: $tenant->name;

        $results = [];
        $sent = 0;
        $skipped = 0;

        foreach ($unique as $email) {
            $ownEmail = strtolower(trim((string) ($client->email ?? '')));
            if ($ownEmail !== '' && $ownEmail === $email) {
                $skipped++;
                $results[] = ['email' => $email, 'status' => 'skipped', 'error' => 'Cannot invite your own email.'];
                continue;
            }

            try {
                $bodyText = $payload['heading']."\n\n".$payload['message'];
                $bodyHtml = '<p style="font-family:Arial,sans-serif;line-height:1.5;">'
                    .'<strong>'.e($payload['heading']).'</strong></p>'
                    .'<p style="font-family:Arial,sans-serif;line-height:1.5;">'
                    .nl2br(e($payload['message'])).'</p>';

                $message = $this->messages->createSystemMessage([
                    'client_id' => null,
                    'source_type' => NotificationSourceType::CRM,
                    'purpose' => NotificationPurpose::REFERRAL_INVITE,
                    'channel' => NotificationChannel::EMAIL,
                    'recipient_name' => null,
                    'recipient_address' => $email,
                    'subject' => $payload['heading'].' — '.$salonName,
                    'body_text' => $bodyText,
                    'body_html' => $bodyHtml,
                    'metadata' => [
                        'via' => 'client_referral_invite',
                        'invite_id' => $invite->id,
                        'referrer_client_id' => $client->id,
                        'join_url' => $payload['join_url'],
                    ],
                ]);

                $status = in_array($message->status, ['sent', 'delivered'], true)
                    ? ClientReferralEmailSend::STATUS_SENT
                    : ($message->status === 'failed'
                        ? ClientReferralEmailSend::STATUS_FAILED
                        : ClientReferralEmailSend::STATUS_SIMULATED);

                $row = ClientReferralEmailSend::query()->create([
                    'tenant_id' => $this->requireTenantId(),
                    'referrer_client_id' => $client->id,
                    'invite_id' => $invite->id,
                    'recipient_email' => $email,
                    'status' => $status,
                    'provider_ref' => $message->id,
                    'error' => $message->failure_reason,
                ]);

                if ($status === ClientReferralEmailSend::STATUS_FAILED) {
                    $results[] = ['email' => $email, 'status' => 'failed', 'error' => $message->failure_reason];
                } else {
                    $sent++;
                    $results[] = ['email' => $email, 'status' => $row->status, 'error' => null];
                }
            } catch (\Throwable $e) {
                ClientReferralEmailSend::query()->create([
                    'tenant_id' => $this->requireTenantId(),
                    'referrer_client_id' => $client->id,
                    'invite_id' => $invite->id,
                    'recipient_email' => $email,
                    'status' => ClientReferralEmailSend::STATUS_FAILED,
                    'provider_ref' => null,
                    'error' => $e->getMessage(),
                ]);
                $results[] = ['email' => $email, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        $this->auditLogger->log('client_referral.email_invites_sent', $invite, null, [
            'referrer_client_id' => $client->id,
            'sent' => $sent,
            'skipped' => $skipped,
            'emails' => array_column($results, 'email'),
        ]);

        $this->timeline->record(
            $client,
            ClientTimelineEvent::EVENT_COMMUNICATION,
            'Referral email invites sent',
            "Sent {$sent} referral invite(s)",
            ['sent' => $sent, 'skipped' => $skipped],
        );

        return [
            'sent' => $sent,
            'skipped' => $skipped,
            'results' => $results,
        ];
    }

    public function resolveInviteByCode(string $code): ?ClientReferralInvite
    {
        $normalized = strtoupper(trim($code));
        if ($normalized === '') {
            return null;
        }

        return ClientReferralInvite::query()
            ->where('tenant_id', $this->requireTenantId())
            ->where('code', $normalized)
            ->where('status', ClientReferralInvite::STATUS_ACTIVE)
            ->first();
    }

    public function convertOnJoin(Client $newClient, ?string $refCode): void
    {
        $this->assertTenantClient($newClient);

        if ($refCode === null || trim($refCode) === '') {
            return;
        }

        $settings = $this->settings();
        if (! $settings->enabled) {
            return;
        }

        if ($newClient->referred_by_client_id !== null || $newClient->referral_attributed_at !== null) {
            return;
        }

        $invite = $this->resolveInviteByCode($refCode);
        if ($invite === null) {
            return;
        }

        if ($invite->referrer_client_id === $newClient->id) {
            return;
        }

        $referrer = Client::query()->find($invite->referrer_client_id);
        if ($referrer === null || $referrer->tenant_id !== $newClient->tenant_id) {
            return;
        }

        if (ClientReferralConversion::query()->where('referred_client_id', $newClient->id)->exists()) {
            return;
        }

        $points = max(0, (int) $settings->referrer_points);

        DB::transaction(function () use ($newClient, $invite, $referrer, $points, $settings) {
            $newClient->referred_by_client_id = $referrer->id;
            $newClient->referral_invite_id = $invite->id;
            $newClient->referral_attributed_at = now();
            $newClient->save();

            $conversion = ClientReferralConversion::query()->create([
                'tenant_id' => $this->requireTenantId(),
                'invite_id' => $invite->id,
                'referrer_client_id' => $referrer->id,
                'referred_client_id' => $newClient->id,
                'referrer_points_awarded' => $points,
                'referred_bonus_pending' => true,
                'referrer_notified_at' => null,
            ]);

            if ($points > 0) {
                $this->loyaltyLedger->postEntry([
                    'client_id' => $referrer->id,
                    'entry_type' => LoyaltyEntryType::REFERRAL_REFERRER,
                    'direction' => LoyaltyEntryDirection::CREDIT,
                    'points' => $points,
                    'source_type' => 'client_referral_conversion',
                    'source_id' => $conversion->id,
                    'notes' => 'Referral reward for inviting a new client',
                ]);
            }

            $this->notifications->safe(
                fn () => $this->notifications->sendReferralThankYou($referrer, [
                    'subject' => $settings->thank_you_subject,
                    'body_text' => $settings->thank_you_body_text,
                    'points' => $points,
                    'referred_client_id' => $newClient->id,
                ])
            );

            $conversion->referrer_notified_at = now();
            $conversion->save();

            $this->auditLogger->log('client_referral.converted', $conversion, null, [
                'referrer_client_id' => $referrer->id,
                'referred_client_id' => $newClient->id,
                'referrer_points' => $points,
            ]);

            $this->timeline->record(
                $referrer,
                ClientTimelineEvent::EVENT_CLIENT_UPDATED,
                'Referral reward earned',
                "{$points} loyalty points for inviting a friend",
                ['points' => $points, 'referred_client_id' => $newClient->id],
            );

            $this->timeline->record(
                $newClient,
                ClientTimelineEvent::EVENT_CLIENT_UPDATED,
                'Joined via referral',
                'Attributed to a friend referral invite',
                ['referrer_client_id' => $referrer->id, 'invite_id' => $invite->id],
            );
        });
    }

    public function awardReferredBonusOnPurchase(Client $client): void
    {
        $this->assertTenantClient($client);

        if ($client->referred_by_client_id === null || $client->referral_attributed_at === null) {
            return;
        }

        if ($client->referral_referred_bonus_awarded_at !== null) {
            return;
        }

        $settings = $this->settings();
        if (! $settings->enabled) {
            return;
        }

        $conversion = ClientReferralConversion::query()
            ->where('referred_client_id', $client->id)
            ->first();

        if ($conversion === null || ! $conversion->referred_bonus_pending) {
            return;
        }

        $points = max(0, (int) $settings->referred_points);
        if ($points <= 0) {
            $client->referral_referred_bonus_awarded_at = now();
            $client->save();
            $conversion->referred_bonus_pending = false;
            $conversion->save();

            return;
        }

        DB::transaction(function () use ($client, $conversion, $points) {
            $locked = Client::query()->where('id', $client->id)->lockForUpdate()->first();
            if ($locked === null || $locked->referral_referred_bonus_awarded_at !== null) {
                return;
            }

            $conv = ClientReferralConversion::query()
                ->where('id', $conversion->id)
                ->lockForUpdate()
                ->first();
            if ($conv === null || ! $conv->referred_bonus_pending) {
                return;
            }

            $this->loyaltyLedger->postEntry([
                'client_id' => $locked->id,
                'entry_type' => LoyaltyEntryType::REFERRAL_REFERRED,
                'direction' => LoyaltyEntryDirection::CREDIT,
                'points' => $points,
                'source_type' => 'client_referral_conversion',
                'source_id' => $conv->id,
                'notes' => 'Referral join bonus on first membership/package purchase',
            ]);

            $locked->referral_referred_bonus_awarded_at = now();
            $locked->save();

            $conv->referred_bonus_pending = false;
            $conv->save();

            $this->auditLogger->log('client_referral.referred_bonus_awarded', $conv, null, [
                'referred_client_id' => $locked->id,
                'points' => $points,
            ]);

            $this->timeline->record(
                $locked,
                ClientTimelineEvent::EVENT_CLIENT_UPDATED,
                'Referral join bonus awarded',
                "{$points} loyalty points for first membership/package purchase",
                ['points' => $points],
            );
        });
    }

    /**
     * @return array{join_url: string, book_url: string}
     */
    private function buildUrls(string $code): array
    {
        $tenant = Tenant::query()->findOrFail($this->requireTenantId());
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $slug = $tenant->slug;
        $ref = rawurlencode($code);

        return [
            'join_url' => "{$frontend}/book/{$slug}?ref={$ref}",
            'book_url' => "{$frontend}/book/{$slug}?ref={$ref}",
        ];
    }

    private function renderShareBody(
        ClientReferralSetting $settings,
        Client $client,
        string $code,
        ?string $joinUrl = null,
    ): string {
        $tenant = Tenant::query()->findOrFail($this->requireTenantId());
        $businessName = $tenant->trading_name ?: $tenant->name;
        $urls = $joinUrl !== null
            ? ['join_url' => $joinUrl]
            : $this->buildUrls($code);

        return str_replace(
            ['{{business.name}}', '{{join_or_book_link}}'],
            [$businessName, $urls['join_url']],
            $settings->share_body_template,
        );
    }

    private function generateUniqueCode(): string
    {
        $tenantId = $this->requireTenantId();
        for ($i = 0; $i < 12; $i++) {
            $code = strtoupper(Str::random(8));
            $exists = ClientReferralInvite::query()
                ->where('tenant_id', $tenantId)
                ->where('code', $code)
                ->exists();
            if (! $exists) {
                return $code;
            }
        }

        return strtoupper(Str::replace('-', '', (string) Str::uuid()));
    }

    private function assertTenantClient(Client $client): void
    {
        $tenantId = $this->requireTenantId();
        if ($client->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'client' => ['Client not found.'],
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
