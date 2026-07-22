<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientConsentRecord;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingMessagePurpose;
use App\Domains\Marketing\Enums\MarketingMessageStatus;
use App\Domains\Marketing\Models\MarketingMessage;
use App\Domains\Marketing\Services\MarketingEmailLayoutService;
use App\Domains\Marketing\Services\MarketingScheduledCadenceService;
use App\Domains\Marketing\Services\MarketingStarterTemplateService;
use App\Domains\Marketing\Services\MarketingTemplateService;
use App\Domains\Marketing\Services\MarketingWelcomeAutomationService;
use App\Domains\Memberships\Enums\ClientMembershipStatus;
use App\Domains\Memberships\Enums\MembershipBillingFrequency;
use App\Domains\Memberships\Enums\MembershipPlanStatus;
use App\Domains\Memberships\Models\ClientMembership;
use App\Domains\Memberships\Models\MembershipPlan;
use App\Jobs\DispatchClientWelcomeMarketingJob;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class MarketingEmailLayoutAndCadenceTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    protected function modulePermissions(): array
    {
        return [
            'marketing.view',
            'marketing.manage',
            'marketing.dispatch',
            'crm.view',
            'crm.manage',
        ];
    }

    public function test_email_preview_includes_branding_chrome_and_powered_by(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);
        $tenant = $ctx['tenant'];
        $tenant->setBranding([
            'primary_color' => '#0a7a5c',
            'brand_display_name' => 'Chrome Salon',
            'logo_url' => 'https://cdn.example.test/logo.png',
        ]);
        $tenant->save();
        app(TenantContext::class)->set($tenant->fresh());

        app(MarketingStarterTemplateService::class)->installSamples();
        $template = app(MarketingStarterTemplateService::class)->findByName('Sample — New client welcome');
        $this->assertNotNull($template);

        $preview = app(MarketingTemplateService::class)->preview($template);

        $this->assertStringContainsString(MarketingEmailLayoutService::POWERED_BY, (string) $preview['body_html']);
        $this->assertStringContainsString('#0a7a5c', (string) $preview['body_html']);
        $this->assertStringContainsString('Chrome Salon', (string) $preview['body_html']);
        $this->assertStringContainsString('Anek Latin', (string) $preview['body_html']);
        $this->assertStringContainsString('cdn.example.test/logo.png', (string) $preview['body_html']);
        $this->assertStringNotContainsString('font-family:Georgia', (string) $preview['body_html']);
    }

    public function test_client_create_queues_welcome_email_job(): void
    {
        Queue::fake();
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $created = app(\App\Domains\Crm\Services\ClientService::class)->create([
            'first_name' => 'Queued',
            'last_name' => 'Welcome',
            'email' => 'queued.welcome@example.test',
            'phone' => '+447700900222',
            'primary_location_id' => $ctx['location']->id,
        ]);

        Queue::assertPushed(DispatchClientWelcomeMarketingJob::class, function (DispatchClientWelcomeMarketingJob $job) use ($created) {
            return $job->clientId === $created->id && $job->tenantId === $created->tenant_id;
        });
    }

    public function test_welcome_email_and_first_login_in_app_send(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);
        app(MarketingStarterTemplateService::class)->installSamples();

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'InApp',
            'last_name' => 'Welcome',
            'email' => 'inapp.welcome@example.test',
            'phone' => '+447700900333',
            'primary_location_id' => $ctx['location']->id,
            'is_active' => true,
            'preferences' => [],
        ]);

        ClientConsentRecord::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'consent_type' => ClientConsentRecord::TYPE_MARKETING_EMAIL,
            'granted' => true,
            'source' => ClientConsentRecord::SOURCE_STAFF_ENTRY,
            'recorded_at' => now(),
        ]);

        $welcome = app(MarketingWelcomeAutomationService::class);
        $email = $welcome->sendWelcomeEmailNow($client->fresh());
        $this->assertNotNull($email);
        $this->assertSame(MarketingChannel::EMAIL, $email->channel);
        $this->assertSame(MarketingMessagePurpose::WELCOME, $email->purpose);
        $this->assertStringContainsString(MarketingEmailLayoutService::POWERED_BY, (string) $email->rendered_body_html);

        $inApp = $welcome->sendWelcomeInAppOnce($client->fresh());
        $this->assertNotNull($inApp);
        $this->assertSame(MarketingChannel::IN_APP, $inApp->channel);

        $again = $welcome->sendWelcomeInAppOnce($client->fresh());
        $this->assertNull($again);
    }

    public function test_birthday_and_membership_cadences_dual_channel(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);
        app(MarketingStarterTemplateService::class)->installSamples();

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Birthday',
            'last_name' => 'Cadence',
            'email' => 'bday@example.test',
            'phone' => '+447700900444',
            'primary_location_id' => $ctx['location']->id,
            'is_active' => true,
            'date_of_birth' => now()->subYears(25)->toDateString(),
        ]);

        ClientConsentRecord::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'consent_type' => ClientConsentRecord::TYPE_MARKETING_EMAIL,
            'granted' => true,
            'source' => ClientConsentRecord::SOURCE_STAFF_ENTRY,
            'recorded_at' => now(),
        ]);

        $cadence = app(MarketingScheduledCadenceService::class);
        $birthday = $cadence->runBirthdayCadence();
        $this->assertGreaterThanOrEqual(1, $birthday['email']);
        $this->assertGreaterThanOrEqual(1, $birthday['in_app']);

        $plan = MembershipPlan::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Gold',
            'status' => MembershipPlanStatus::ACTIVE,
            'billing_frequency' => MembershipBillingFrequency::MONTHLY,
            'price_cents' => 5000,
            'joining_fee_cents' => 0,
            'is_public' => true,
        ]);

        ClientMembership::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'membership_plan_id' => $plan->id,
            'status' => ClientMembershipStatus::ACTIVE,
            'started_at' => now()->subMonth(),
            'price_cents_snapshot' => 5000,
        ]);

        $membership = $cadence->runMembershipReminderCadence();
        $this->assertGreaterThanOrEqual(1, $membership['email']);
        $this->assertGreaterThanOrEqual(1, $membership['in_app']);

        $this->assertTrue(
            MarketingMessage::query()
                ->where('client_id', $client->id)
                ->where('purpose', MarketingMessagePurpose::BIRTHDAY)
                ->where('channel', MarketingChannel::EMAIL)
                ->exists()
        );
    }

    public function test_win_back_cadence_respects_inactivity_and_join_captures_dob(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);
        app(MarketingStarterTemplateService::class)->installSamples();

        $inactive = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Inactive',
            'last_name' => 'WinBack',
            'email' => 'winback@example.test',
            'phone' => '+447700900555',
            'primary_location_id' => $ctx['location']->id,
            'is_active' => true,
        ]);

        ClientConsentRecord::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $inactive->id,
            'consent_type' => ClientConsentRecord::TYPE_MARKETING_EMAIL,
            'granted' => true,
            'source' => ClientConsentRecord::SOURCE_STAFF_ENTRY,
            'recorded_at' => now(),
        ]);

        Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $inactive->id,
            'team_member_id' => $ctx['teamMember']->id,
            'status' => Appointment::STATUS_COMPLETED,
            'booking_source' => Appointment::SOURCE_ADMIN,
            'booking_reference' => 'NM-'.Str::upper(Str::random(8)),
            'deposit_status' => Appointment::DEPOSIT_NOT_REQUIRED,
            'starts_at' => now()->subDays(150),
            'ends_at' => now()->subDays(150)->addHour(),
        ]);

        $counts = app(MarketingScheduledCadenceService::class)->runWinBackCadence();
        $this->assertGreaterThanOrEqual(1, $counts['email']);
        $this->assertGreaterThanOrEqual(1, $counts['in_app']);

        $join = $this->postJson('/api/v1/join/clients', [
            'first_name' => 'Dob',
            'whatsapp_number' => '+447700900666',
            'email' => 'dob.join@example.test',
            'date_of_birth' => '1990-07-22',
            'location_id' => $ctx['location']->id,
        ], [
            'X-Tenant-Slug' => $ctx['tenant']->slug,
        ]);

        $join->assertSuccessful();
        $saved = Client::withoutGlobalScopes()->where('email', 'dob.join@example.test')->first();
        $this->assertNotNull($saved);
        $this->assertSame('1990-07-22', $saved->date_of_birth?->toDateString());
    }

    public function test_monthly_book_nudge_purpose_is_distinct(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);
        app(MarketingStarterTemplateService::class)->installSamples();

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Monthly',
            'last_name' => 'Nudge',
            'email' => 'monthly@example.test',
            'phone' => '+447700900777',
            'primary_location_id' => $ctx['location']->id,
            'is_active' => true,
        ]);

        ClientConsentRecord::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'consent_type' => ClientConsentRecord::TYPE_MARKETING_EMAIL,
            'granted' => true,
            'source' => ClientConsentRecord::SOURCE_STAFF_ENTRY,
            'recorded_at' => now(),
        ]);

        $counts = app(MarketingScheduledCadenceService::class)->runMonthlyBookNudgeCadence(now()->startOfMonth());
        $this->assertGreaterThanOrEqual(1, $counts['email']);

        $this->assertTrue(
            MarketingMessage::query()
                ->where('client_id', $client->id)
                ->where('purpose', MarketingMessagePurpose::MONTHLY_BOOK_NUDGE)
                ->where('status', '!=', MarketingMessageStatus::SKIPPED)
                ->exists()
        );
    }
}
