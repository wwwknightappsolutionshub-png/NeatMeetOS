<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\BookableService;
use App\Domains\Identity\Models\AuthActionToken;
use App\Domains\Identity\Models\SubscriptionPlan;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\TenantSubscription;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\SignupFormDefinitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantSignupAndAuthLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        foreach ([
            ['slug' => 'basic', 'name' => 'Basic', 'price' => 4900],
            ['slug' => 'pro', 'name' => 'Pro', 'price' => 12900],
            ['slug' => 'diamond', 'name' => 'Diamond', 'price' => 29900],
        ] as $plan) {
            SubscriptionPlan::query()->firstOrCreate(
                ['slug' => $plan['slug']],
                [
                    'name' => $plan['name'],
                    'billing_interval' => 'monthly',
                    'display_price_cents' => $plan['price'],
                    'features' => ['booking' => true],
                    'limits' => ['max_locations' => 1],
                    'is_active' => true,
                ],
            );
        }

        app(SignupFormDefinitionService::class)->ensureDefaultActive();
    }

    public function test_public_signup_form_returns_steps_and_tiers(): void
    {
        $this->getJson('/api/v1/signup/form')
            ->assertOk()
            ->assertJsonPath('data.default_plan_slug', 'basic')
            ->assertJsonPath('data.trial_days', 30)
            ->assertJsonCount(3, 'data.plans')
            ->assertJsonStructure(['data' => ['service_catalogue']])
            ->assertJsonPath('data.steps.0.id', 'business')
            ->assertJsonPath('data.steps.1.id', 'services')
            ->assertJsonPath('data.steps.2.id', 'owner')
            ->assertJsonPath('data.steps.3.id', 'location')
            ->assertJsonPath('data.steps.4.id', 'plan');
    }

    public function test_register_creates_pending_tenant_on_basic_and_sends_activation_mail(): void
    {
        $response = $this->postJson('/api/v1/signup/register', [
            'answers' => $this->validAnswers(['desired_plan_slug' => 'pro']),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.tenant.status', 'pending_activation')
            ->assertJsonPath('data.activation_sent', true);

        $tenant = Tenant::query()->where('slug', 'bloom-hair')->first();
        $this->assertNotNull($tenant);
        $this->assertSame('basic', $tenant->subscriptionPlan?->slug);
        $this->assertSame('+447700900123', $tenant->owner_whatsapp);

        $sub = TenantSubscription::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertSame('pro', $sub?->desired_plan_slug);
        $this->assertFalse((bool) $sub?->tier_unlocked);

        $this->assertSame(
            2,
            BookableService::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
        );
        $this->assertDatabaseHas('booking_services', [
            'tenant_id' => $tenant->id,
            'name' => 'Cut & Blow Dry',
            'is_bookable_online' => true,
        ]);

        $location = $tenant->locations()->first();
        $this->assertNotNull($location);
        $hours = $location->opening_hours;
        $this->assertIsArray($hours);
        $monday = collect($hours)->firstWhere('day_of_week', 1);
        $this->assertSame('09:00', $monday['start_time'] ?? null);
        $this->assertSame('18:00', $monday['end_time'] ?? null);

        $this->assertDatabaseCount('auth_action_tokens', 1);
        $this->assertDatabaseHas('auth_action_tokens', [
            'purpose' => AuthActionToken::PURPOSE_ACTIVATION,
        ]);
    }

    public function test_activate_sets_password_and_allows_login(): void
    {
        $this->postJson('/api/v1/signup/register', [
            'answers' => $this->validAnswers(),
        ])->assertCreated();

        $token = AuthActionToken::query()->first();
        $plain = 'activation-test-token-value-0123456789abcdef0123456789ab';
        $token->forceFill(['token_hash' => hash('sha256', $plain)])->save();

        $activate = $this->postJson('/api/v1/signup/activate', [
            'token' => $plain,
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $activate->assertOk()->assertJsonPath('data.tenant.slug', 'bloom-hair');

        $tenant = Tenant::query()->where('slug', 'bloom-hair')->first();
        $this->assertSame('active', $tenant?->status);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@bloom.test',
            'password' => 'Password1!',
        ])->assertOk();
    }

    public function test_pending_activation_blocks_password_login(): void
    {
        $this->postJson('/api/v1/signup/register', [
            'answers' => $this->validAnswers(),
        ])->assertCreated();

        $user = User::query()->where('email', 'owner@bloom.test')->firstOrFail();
        $user->forceFill(['password' => 'password'])->save();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@bloom.test',
            'password' => 'password',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_pro_plan_locked_until_platform_unlock(): void
    {
        $this->postJson('/api/v1/signup/register', [
            'answers' => $this->validAnswers(),
        ])->assertCreated();

        $plain = 'activation-test-token-value-0123456789abcdef0123456789ab';
        AuthActionToken::query()->first()?->forceFill(['token_hash' => hash('sha256', $plain)])->save();

        $activate = $this->postJson('/api/v1/signup/activate', [
            'token' => $plain,
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertOk();

        $authToken = $activate->json('data.token');
        $tenantSlug = $activate->json('data.tenant.slug');

        $this->withHeader('Authorization', 'Bearer '.$authToken)
            ->withHeader('X-Tenant-Slug', $tenantSlug)
            ->postJson('/api/v1/admin/subscription/change-plan', ['plan_slug' => 'pro'])
            ->assertStatus(422);

        $admin = User::factory()->create([
            'email' => 'platform-unlock@test.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $tenantId = Tenant::query()->where('slug', $tenantSlug)->value('id');

        $this->postJson('/api/v1/platform/tenants/'.$tenantId.'/unlock-tiers', [
            'activate_plan_slug' => 'pro',
        ])->assertOk()->assertJsonPath('data.plan_slug', 'pro');

        $this->assertSame('pro', Tenant::query()->find($tenantId)?->subscriptionPlan?->slug);
    }

    public function test_platform_admin_can_crud_signup_forms(): void
    {
        $admin = User::factory()->create([
            'email' => 'platform-forms@test.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/v1/platform/signup-forms', [
            'name' => 'Alt wizard',
            'slug' => 'alt-wizard',
            'steps' => SignupFormDefinitionService::defaultSteps(),
            'is_active' => true,
        ])->assertCreated();

        $id = $create->json('data.id');

        $this->putJson('/api/v1/platform/signup-forms/'.$id, [
            'name' => 'Alt wizard v2',
            'is_active' => true,
        ])->assertOk()->assertJsonPath('data.name', 'Alt wizard v2');

        $this->getJson('/api/v1/platform/signup-forms')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'alt-wizard']);

        $this->putJson('/api/v1/platform/signup-forms/'.$id, ['is_active' => false])->assertOk();
        $this->deleteJson('/api/v1/platform/signup-forms/'.$id)->assertOk();
    }

    public function test_magic_link_and_password_reset_flow(): void
    {
        $user = User::factory()->create([
            'email' => 'magic@test.local',
            'password' => Hash::make('password'),
        ]);

        $this->postJson('/api/v1/auth/magic-link', ['email' => 'magic@test.local'])
            ->assertOk();

        $this->assertDatabaseHas('auth_action_tokens', [
            'purpose' => AuthActionToken::PURPOSE_MAGIC_LOGIN,
            'user_id' => $user->id,
        ]);

        $plain = 'magic-link-token-value-0123456789abcdef0123456789abcdef';
        AuthActionToken::query()->where('purpose', AuthActionToken::PURPOSE_MAGIC_LOGIN)
            ->first()
            ?->forceFill(['token_hash' => hash('sha256', $plain)])
            ->save();

        $this->postJson('/api/v1/auth/magic-link/consume', ['token' => $plain])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'magic@test.local');

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'magic@test.local'])
            ->assertOk();

        $this->assertDatabaseHas('auth_action_tokens', [
            'purpose' => AuthActionToken::PURPOSE_PASSWORD_RESET,
            'user_id' => $user->id,
        ]);

        $resetPlain = 'reset-link-token-value-0123456789abcdef0123456789abcdef0';
        AuthActionToken::query()->where('purpose', AuthActionToken::PURPOSE_PASSWORD_RESET)
            ->latest()
            ->first()
            ?->forceFill(['token_hash' => hash('sha256', $resetPlain)])
            ->save();

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $resetPlain,
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword1!', $user->fresh()->password));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validAnswers(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Bloom Hair',
            'trading_name' => 'Bloom Hair Ltd',
            'slug' => 'bloom-hair',
            'business_type' => 'boutique',
            'timezone' => 'Europe/London',
            'owner_first_name' => 'Ada',
            'owner_last_name' => 'Owner',
            'owner_email' => 'owner@bloom.test',
            'owner_whatsapp' => '+447700900123',
            'contact_email' => 'hello@bloom.test',
            'location_name' => 'High Street',
            'address_line1' => '1 High Street',
            'city' => 'London',
            'postcode' => 'E1 1AA',
            'country' => 'GB',
            'opening_time' => '09:00',
            'closing_time' => '18:00',
            'desired_plan_slug' => 'basic',
            'services' => [
                [
                    'name' => 'Cut & Blow Dry',
                    'category' => 'hair',
                    'description' => 'Consultation, cut, and finish.',
                    'duration_minutes' => 60,
                    'base_price_cents' => 4500,
                    'image_url' => null,
                ],
                [
                    'name' => 'Blow Dry',
                    'category' => 'hair',
                    'description' => 'Wash and blow-dry.',
                    'duration_minutes' => 45,
                    'base_price_cents' => 3500,
                    'image_url' => null,
                ],
            ],
        ], $overrides);
    }

    public function test_signup_rejects_more_than_basic_service_limit(): void
    {
        $services = [];
        for ($i = 1; $i <= 5; $i++) {
            $services[] = [
                'name' => "Service {$i}",
                'category' => 'hair',
                'description' => 'Test',
                'duration_minutes' => 30,
                'base_price_cents' => 1000,
                'image_url' => null,
            ];
        }

        $this->postJson('/api/v1/signup/register', [
            'answers' => $this->validAnswers([
                'owner_email' => 'limit@bloom.test',
                'slug' => 'bloom-limit',
                'services' => $services,
            ]),
        ])->assertStatus(422);
    }
}
