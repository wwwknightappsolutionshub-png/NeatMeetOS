<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-tenant Free Salon Growth Assessment prospects.
 * Not tenant-scoped — these are platform sales leads, not salon CRM clients.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salon_growth_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('public_token', 64)->unique();

            $table->string('business_name');
            $table->string('business_type', 64);
            $table->string('staff_band', 32)->nullable();
            $table->string('customers_per_month_band', 32)->nullable();

            $table->string('contact_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('phone_normalized')->nullable()->index();
            $table->string('postcode', 32)->nullable();
            $table->boolean('marketing_consent')->default(false);

            $table->json('answers');
            $table->unsignedTinyInteger('score_overall')->default(0);
            $table->unsignedTinyInteger('score_visibility')->default(0);
            $table->unsignedTinyInteger('score_retention')->default(0);
            $table->unsignedTinyInteger('score_revenue_visibility')->default(0);
            $table->unsignedTinyInteger('score_reengagement')->default(0);
            $table->unsignedInteger('estimated_opportunity_cents')->default(0);
            $table->string('primary_opportunity', 64)->nullable();
            $table->string('primary_opportunity_label')->nullable();
            $table->text('sales_conversation_hint')->nullable();

            $table->string('uses_software', 16)->nullable();
            $table->json('software_helps_with')->nullable();
            $table->string('software_satisfaction', 32)->nullable();
            $table->string('tracking_methods')->nullable();

            $table->string('lead_status', 32)->default('new')->index();
            $table->uuid('assigned_platform_user_id')->nullable()->index();
            $table->text('internal_notes')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->date('next_follow_up_on')->nullable();

            $table->string('email_delivery_status', 32)->default('pending');
            $table->timestamp('email_sent_at')->nullable();
            $table->string('whatsapp_delivery_status', 32)->default('not_requested');
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->text('whatsapp_delivery_error')->nullable();

            $table->string('source', 64)->default('landing');
            $table->string('referral_code')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->timestamps();

            $table->index(['lead_status', 'created_at']);
            $table->index(['score_overall', 'estimated_opportunity_cents']);
            $table->index('business_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salon_growth_assessments');
    }
};
