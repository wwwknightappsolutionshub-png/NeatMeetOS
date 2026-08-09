<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Models\PlatformWhatsAppSettings;
use App\Jobs\SendTenantSignupWelcomeWhatsAppJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantSignupWelcomeWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
    }

    protected function actingAsPlatformOwner(): User
    {
        $user = User::query()->create([
            'name' => 'Platform Owner WA Welcome',
            'email' => 'platform.wa.welcome@example.test',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
            'platform_role' => 'owner',
        ]);
        Sanctum::actingAs($user);

        return $user;
    }

    protected function enablePlatformWhatsApp(): void
    {
        PlatformWhatsAppSettings::query()->create([
            'enabled' => true,
            'provider' => 'genius',
            'api_key' => 'api-welcome-key',
            'session_id' => 'session_platform_welcome',
            'base_url' => 'https://restapi.geniusdevel.com',
            'signup_welcome_enabled' => true,
        ]);
    }

    public function test_lead_with_whatsapp_queues_welcome_trial_job(): void
    {
        $this->enablePlatformWhatsApp();

        $this->postJson('/api/v1/signup/lead', [
            'name' => 'Ada Owner',
            'email' => 'ada.welcome@example.test',
            'whatsapp' => '+447700900111',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'created');

        Queue::assertPushed(SendTenantSignupWelcomeWhatsAppJob::class, function (SendTenantSignupWelcomeWhatsAppJob $job) {
            return $job->type === 'welcome_trial'
                && ($job->vars['phone'] ?? null) === '+447700900111'
                && filled($job->vars['password'] ?? null)
                && str_contains((string) ($job->vars['link'] ?? ''), 'tab=signup');
        });
    }

    public function test_register_queues_activation_whatsapp_job(): void
    {
        $this->enablePlatformWhatsApp();

        $this->postJson('/api/v1/signup/register', [
            'answers' => [
                'business_name' => 'Welcome Salon Ltd',
                'trading_name' => 'Welcome Salon',
                'slug' => 'welcome-salon-wa',
                'business_type' => 'hair',
                'timezone' => 'Europe/London',
                'owner_first_name' => 'Ada',
                'owner_last_name' => 'Owner',
                'owner_email' => 'ada.register.wa@example.test',
                'owner_whatsapp' => '+447700900222',
                'contact_email' => 'ada.register.wa@example.test',
                'location_name' => 'Main',
                'address_line1' => '1 High Street',
                'city' => 'London',
                'postcode' => 'E1 6AN',
                'country' => 'GB',
                'opening_time' => '09:00',
                'closing_time' => '18:00',
                'desired_plan_slug' => 'basic',
                'services' => [
                    [
                        'name' => 'Cut',
                        'duration_minutes' => 30,
                        'base_price_cents' => 2500,
                    ],
                ],
            ],
        ])->assertCreated();

        Queue::assertPushed(SendTenantSignupWelcomeWhatsAppJob::class, function (SendTenantSignupWelcomeWhatsAppJob $job) {
            return $job->type === 'activation'
                && ($job->vars['phone'] ?? null) === '+447700900222'
                && str_contains((string) ($job->vars['link'] ?? ''), 'activate=');
        });
    }

    public function test_platform_can_edit_signup_welcome_copy_and_upload_banner(): void
    {
        $this->actingAsPlatformOwner();
        Storage::fake('public');
        $this->enablePlatformWhatsApp();

        $this->putJson('/api/v1/platform/whatsapp-settings', [
            'signup_welcome_enabled' => true,
            'signup_welcome_trial_body' => 'Hi {{name}} trial {{password}} {{link}}',
            'signup_welcome_activation_body' => 'Hi {{name}} activate {{salon}} {{link}}',
        ])
            ->assertOk()
            ->assertJsonPath('data.whatsapp.signup_welcome.trial_body', 'Hi {{name}} trial {{password}} {{link}}')
            ->assertJsonPath('data.whatsapp.signup_welcome.activation_body', 'Hi {{name}} activate {{salon}} {{link}}');

        $upload = $this->post('/api/v1/platform/whatsapp-settings/signup-welcome-banner', [
            'image' => UploadedFile::fake()->image('banner.jpg', 640, 360),
        ], [
            'Accept' => 'application/json',
        ]);

        $upload->assertOk()
            ->assertJsonPath('data.whatsapp.signup_welcome.banner.has_data', true);

        $this->get('/api/v1/public/whatsapp/signup-welcome-banner')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_signup_welcome_job_sends_custom_body_and_banner(): void
    {
        $this->enablePlatformWhatsApp();
        $settings = PlatformWhatsAppSettings::query()->firstOrFail();
        $settings->update([
            'signup_welcome_trial_body' => 'CUSTOM {{name}} / {{password}}',
            'signup_welcome_banner_data' => base64_encode('fake-jpeg-bytes'),
            'signup_welcome_banner_mime' => 'image/jpeg',
            'signup_welcome_banner_path' => 'platform/whatsapp/signup-welcome-banner.jpg',
            'signup_welcome_banner_url' => 'https://example.test/api/v1/public/whatsapp/signup-welcome-banner',
        ]);

        Http::fake([
            'restapi.geniusdevel.com/*' => Http::response(['ok' => true], 200),
        ]);

        $job = new SendTenantSignupWelcomeWhatsAppJob('welcome_trial', [
            'name' => 'Ada',
            'email' => 'ada@example.test',
            'password' => 'TempPass12',
            'link' => 'https://app.test/login',
            'phone' => '+447700900333',
        ]);
        $job->handle(app(\App\Domains\Notifications\Services\WhatsApp\PlatformSignupWhatsAppWelcomeService::class));

        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            $data = $request->data();

            return ($data['type'] ?? null) === 'image'
                && filled($data['mediaUrl'] ?? $data['url'] ?? null);
        });
        Http::assertSent(function ($request) {
            $data = $request->data();

            return ($data['type'] ?? null) === 'text'
                && str_contains((string) ($data['message'] ?? ''), 'CUSTOM Ada / TempPass12');
        });
    }
}
