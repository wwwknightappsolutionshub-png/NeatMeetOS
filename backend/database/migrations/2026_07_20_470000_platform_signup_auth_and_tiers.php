<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_signup_form_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('steps');
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('auth_action_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('purpose', 40);
            $table->string('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['purpose', 'token_hash']);
            $table->index(['user_id', 'purpose', 'expires_at']);
        });

        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->string('desired_plan_slug', 40)->nullable()->after('subscription_plan_id');
            $table->boolean('tier_unlocked')->default(false)->after('desired_plan_slug');
            $table->timestamp('tier_unlocked_at')->nullable()->after('tier_unlocked');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('owner_whatsapp', 40)->nullable()->after('contact_phone');
        });

        // Ensure Basic / Pro / Diamond catalogue exists (idempotent for existing DBs).
        $now = now();
        $plans = [
            [
                'slug' => 'basic',
                'name' => 'Basic',
                'description' => 'Essentials for a single salon — booking, CRM capture, and day-to-day ops.',
                'features' => json_encode(['booking' => true, 'crm' => true, 'pos' => false, 'analytics' => false]),
                'limits' => json_encode(['max_locations' => 1, 'max_staff' => 5, 'max_workspaces' => 10]),
                'display_price_cents' => 4900,
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'description' => 'Growing teams — POS, inventory, memberships, and multi-chair workflows.',
                'features' => json_encode(['booking' => true, 'crm' => true, 'pos' => true, 'inventory' => true, 'memberships' => true, 'analytics' => true]),
                'limits' => json_encode(['max_locations' => 5, 'max_staff' => 25, 'max_workspaces' => 50]),
                'display_price_cents' => 12900,
            ],
            [
                'slug' => 'diamond',
                'name' => 'Diamond',
                'description' => 'Multi-location brands — full suite, advanced analytics, and priority platform support.',
                'features' => json_encode(['booking' => true, 'crm' => true, 'pos' => true, 'inventory' => true, 'memberships' => true, 'marketing' => true, 'analytics' => true, 'integrations' => true, 'ecommerce' => true]),
                'limits' => json_encode(['max_locations' => 25, 'max_staff' => 200, 'max_workspaces' => 500]),
                'display_price_cents' => 29900,
            ],
        ];

        foreach ($plans as $plan) {
            $exists = DB::table('subscription_plans')->where('slug', $plan['slug'])->exists();
            if ($exists) {
                continue;
            }
            DB::table('subscription_plans')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'name' => $plan['name'],
                'slug' => $plan['slug'],
                'description' => $plan['description'],
                'billing_interval' => 'monthly',
                'features' => $plan['features'],
                'limits' => $plan['limits'],
                'display_price_cents' => $plan['display_price_cents'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('owner_whatsapp');
        });

        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['desired_plan_slug', 'tier_unlocked', 'tier_unlocked_at']);
        });

        Schema::dropIfExists('auth_action_tokens');
        Schema::dropIfExists('platform_signup_form_definitions');
    }
};
