<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_referral_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->boolean('enabled')->default(false);
            $table->string('reward_type', 40)->default('account_credit_cents');
            $table->unsignedInteger('reward_amount')->default(5000);
            $table->string('qualification_goal', 60)->default('referred_tenant_activated');
            $table->unsignedInteger('qualification_days')->nullable();
            $table->string('share_headline')->nullable();
            $table->text('share_body')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_referral_invites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('referrer_tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 32)->unique();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('conversions_count')->default(0);
            $table->timestamps();
        });

        Schema::create('platform_referral_conversions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invite_id')->constrained('platform_referral_invites')->cascadeOnDelete();
            $table->foreignUuid('referrer_tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('referred_tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('qualification_goal', 60);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('reward_amount')->nullable();
            $table->string('reward_type', 40)->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('referred_tenant_id');
        });

        Schema::create('tenant_owner_push_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('endpoint_hash', 64);
            $table->text('p256dh')->nullable();
            $table->text('auth')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'endpoint_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_owner_push_subscriptions');
        Schema::dropIfExists('platform_referral_conversions');
        Schema::dropIfExists('platform_referral_invites');
        Schema::dropIfExists('platform_referral_settings');
    }
};
