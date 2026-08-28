<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\SubscriptionPlan;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\TenantWorkspaceWelcomeService;
use App\Domains\Notifications\Models\PlatformWhatsAppSettings;
use App\Jobs\SendTenantWorkspaceWelcomeJob;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantWorkspaceWelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_phone_converts_uk_local_to_e164(): void
    {
        $service = app(TenantWorkspaceWelcomeService::class);

        $this->assertSame('+447853101133', $service->normalizePhone('07853101133'));
        $this->assertSame('+447723010888', $service->normalizePhone('+447723010888'));
    }

    public function test_queue_command_dispatches_delayed_jobs(): void
    {
        Queue::fake();

        Artisan::call('tenants:queue-workspace-welcomes', [
            '--at' => now('Europe/London')->addHour()->format('Y-m-d H:i:s'),
        ]);

        Queue::assertPushed(SendTenantWorkspaceWelcomeJob::class, 6);
    }

    public function test_queue_command_rejects_past_send_time(): void
    {
        $exitCode = Artisan::call('tenants:queue-workspace-welcomes', [
            '--at' => '2020-01-01 10:00:00',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('past', Artisan::output());
    }

    public function test_queue_command_uses_config_scheduled_at(): void
    {
        Queue::fake();
        config([
            'post_deploy_welcomes.scheduled_at' => Carbon::now('Europe/London')->addHours(2)->format('Y-m-d H:i:s'),
            'post_deploy_welcomes.scheduled_timezone' => 'Europe/London',
        ]);

        Artisan::call('tenants:queue-workspace-welcomes');

        Queue::assertPushed(SendTenantWorkspaceWelcomeJob::class, 6);
    }

    public function test_workspace_welcome_sends_email_and_whatsapp(): void
    {
        Http::fake([
            'https://restapi.geniusdevel.com/*' => Http::response(['ok' => true], 200),
        ]);

        PlatformWhatsAppSettings::query()->create([
            'enabled' => true,
            'provider' => 'genius',
            'api_key' => 'api-welcome-key',
            'session_id' => 'session_platform_welcome',
            'base_url' => 'https://restapi.geniusdevel.com',
            'signup_welcome_enabled' => true,
        ]);

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Basic',
            'slug' => 'basic-'.Str::random(6),
            'billing_interval' => 'monthly',
            'limits' => ['max_locations' => 1],
            'features' => ['booking' => true],
            'is_active' => true,
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Glow Studio',
            'slug' => 'glow-'.Str::random(6),
            'status' => 'active',
            'subscription_plan_id' => $plan->id,
            'owner_whatsapp' => '+447700900222',
        ]);

        $user = User::factory()->create([
            'email' => 'workspace.welcome@example.test',
            'password' => Hash::make('password'),
            'workspace_status' => User::WORKSPACE_COMPLETE,
        ]);

        TeamMember::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'first_name' => 'Welcome',
            'last_name' => 'Owner',
            'employment_type' => TeamMember::EMPLOYMENT_OWNER,
            'display_name' => 'Welcome Owner',
            'is_active' => true,
        ]);

        $this->mock(\App\Domains\Identity\Services\AuthMailService::class, function ($mock) {
            $mock->shouldReceive('sendWorkspaceWelcome')->once();
        });

        $result = app(TenantWorkspaceWelcomeService::class)->sendToEmail($user->email, '+447700900222');

        $this->assertTrue($result['email_sent']);
        $this->assertTrue($result['whatsapp']['sent'] ?? false);
    }
}
