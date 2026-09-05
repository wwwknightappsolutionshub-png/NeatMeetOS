<?php

namespace Tests\Feature;

use App\Domains\GrowthAssessment\Models\SalonGrowthAssessment;
use App\Domains\GrowthAssessment\Services\SalonGrowthScoringService;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalonGrowthAssessmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('security.turnstile.enabled', false);
        Mail::fake();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Northside Cuts',
            'business_type' => 'barber_shop',
            'staff_band' => '2_3',
            'customers_per_month_band' => '101_250',
            'contact_name' => 'Alex Owner',
            'email' => 'alex@northside.test',
            'phone' => '07700900111',
            'postcode' => 'M1 1AE',
            'marketing_consent' => true,
            'send_whatsapp' => false,
            'source' => 'landing',
            'hp_trap' => '',
            'answers' => [
                'knows_last_month_visitors' => 'approximately',
                'knows_how_many_returned' => 'no',
                'tracking_method' => 'booking_software',
                'knows_when_due_return' => 'rarely',
                'return_percentage_band' => '20_40',
                'encourage_return_methods' => ['sms', 'discounts'],
                'avg_spend_band' => '20_40',
                'knows_missed_revenue' => 'no',
                'uses_software' => 'yes',
                'software_helps_with' => ['booking'],
                'software_satisfaction' => 'not_very_satisfied',
            ],
        ], $overrides);
    }

    public function test_scoring_produces_indicative_opportunity(): void
    {
        $scored = (new SalonGrowthScoringService)->score($this->validPayload()['answers'] + [
            'customers_per_month_band' => '101_250',
            'avg_spend_band' => '20_40',
            'return_percentage_band' => '20_40',
        ]);

        // 175 × 30 × 0.50 = 2625 → £2625
        $this->assertSame(262500, $scored['estimated_opportunity_cents']);
        $this->assertGreaterThan(0, $scored['score_overall']);
        $this->assertLessThanOrEqual(100, $scored['score_overall']);
    }

    public function test_public_can_submit_assessment(): void
    {
        $response = $this->postJson('/api/v1/growth-assessments', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'public_token',
                    'score_overall',
                    'score_visibility',
                    'score_retention',
                    'score_revenue_visibility',
                    'score_reengagement',
                    'estimated_opportunity_cents',
                    'primary_opportunity_label',
                    'neatmeet_capabilities',
                ],
            ]);

        $this->assertDatabaseHas('salon_growth_assessments', [
            'business_name' => 'Northside Cuts',
            'email' => 'alex@northside.test',
            'lead_status' => 'new',
            'phone_normalized' => '+447700900111',
        ]);

        $token = $response->json('data.public_token');
        $this->getJson('/api/v1/growth-assessments/'.$token)
            ->assertOk()
            ->assertJsonPath('data.business_name', 'Northside Cuts');
    }

    public function test_honeypot_rejects_bots(): void
    {
        $this->postJson('/api/v1/growth-assessments', $this->validPayload([
            'hp_trap' => 'http://spam.test',
        ]))->assertStatus(422);

        $this->assertDatabaseCount('salon_growth_assessments', 0);
    }

    public function test_platform_admin_can_list_and_update_lead(): void
    {
        $this->postJson('/api/v1/growth-assessments', $this->validPayload())->assertCreated();
        $row = SalonGrowthAssessment::query()->firstOrFail();

        $admin = User::query()->create([
            'name' => 'Platform Manager GA',
            'email' => 'platform.ga@example.test',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
            'platform_role' => 'manager',
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/platform/growth-assessments')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $this->getJson('/api/v1/platform/growth-assessments/'.$row->id)
            ->assertOk()
            ->assertJsonPath('data.prospect_opportunity.main_weakness', $row->primary_opportunity_label);

        $this->patchJson('/api/v1/platform/growth-assessments/'.$row->id, [
            'lead_status' => 'qualified',
            'internal_notes' => 'Strong switch/layer opportunity — already on booking software.',
            'next_follow_up_on' => '2026-09-12',
        ])
            ->assertOk()
            ->assertJsonPath('data.lead_status', 'qualified');

        $this->assertDatabaseHas('salon_growth_assessments', [
            'id' => $row->id,
            'lead_status' => 'qualified',
        ]);
    }

    public function test_tenant_user_cannot_access_platform_assessments(): void
    {
        $user = User::query()->create([
            'name' => 'Tenant User',
            'email' => 'tenant.ga@example.test',
            'password' => Hash::make('password'),
            'is_platform_admin' => false,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/platform/growth-assessments')->assertForbidden();
    }
}
