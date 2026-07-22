<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_referral_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->boolean('enabled')->default(true);
            $table->integer('referrer_points')->default(100);
            $table->integer('referred_points')->default(300);
            $table->string('share_heading')->default('Get Special Grooming Treats');
            $table->text('share_body_template');
            $table->string('thank_you_subject')->default('Thank you for your referral');
            $table->text('thank_you_body_text');
            $table->unsignedInteger('max_email_invites_per_send')->default(20);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('client_referral_invites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('referrer_client_id')->index();
            $table->string('code', 32);
            $table->string('status', 20)->default('active');
            $table->text('share_message_snapshot')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('referrer_client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'referrer_client_id']);
        });

        Schema::create('client_referral_conversions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('invite_id')->index();
            $table->uuid('referrer_client_id')->index();
            $table->uuid('referred_client_id')->unique();
            $table->integer('referrer_points_awarded')->default(0);
            $table->boolean('referred_bonus_pending')->default(true);
            $table->timestamp('referrer_notified_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('invite_id')->references('id')->on('client_referral_invites')->cascadeOnDelete();
            $table->foreign('referrer_client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->foreign('referred_client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->index(['tenant_id', 'referrer_client_id']);
        });

        Schema::create('client_referral_email_sends', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('referrer_client_id')->index();
            $table->uuid('invite_id')->index();
            $table->string('recipient_email');
            $table->string('status', 40)->default('queued');
            $table->string('provider_ref')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('referrer_client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->foreign('invite_id')->references('id')->on('client_referral_invites')->cascadeOnDelete();
            $table->index(['tenant_id', 'referrer_client_id']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->uuid('referred_by_client_id')->nullable()->after('is_active');
            $table->uuid('referral_invite_id')->nullable()->after('referred_by_client_id');
            $table->timestamp('referral_attributed_at')->nullable()->after('referral_invite_id');
            $table->timestamp('referral_referred_bonus_awarded_at')->nullable()->after('referral_attributed_at');

            $table->foreign('referred_by_client_id')->references('id')->on('clients')->nullOnDelete();
            $table->foreign('referral_invite_id')->references('id')->on('client_referral_invites')->nullOnDelete();
            $table->index('referred_by_client_id');
            $table->index('referral_invite_id');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['referred_by_client_id']);
            $table->dropForeign(['referral_invite_id']);
            $table->dropColumn([
                'referred_by_client_id',
                'referral_invite_id',
                'referral_attributed_at',
                'referral_referred_bonus_awarded_at',
            ]);
        });

        Schema::dropIfExists('client_referral_email_sends');
        Schema::dropIfExists('client_referral_conversions');
        Schema::dropIfExists('client_referral_invites');
        Schema::dropIfExists('client_referral_settings');
    }
};
