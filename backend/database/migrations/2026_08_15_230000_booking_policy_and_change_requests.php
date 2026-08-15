<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_policy_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->unsignedSmallInteger('min_advance_notice_minutes')->default(30);
            $table->unsignedSmallInteger('free_change_window_minutes')->default(15);
            $table->unsignedTinyInteger('late_cancel_fee_percent')->default(50);
            $table->unsignedSmallInteger('free_window_reminder_lead_minutes')->default(10);
            $table->unsignedSmallInteger('approval_reminder_interval_minutes')->default(2);
            $table->unsignedTinyInteger('approval_reminder_max_count')->default(3);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('booking_change_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('appointment_id')->index();
            $table->string('type', 32);
            $table->string('initiated_by', 32);
            $table->string('status', 32)->default('pending')->index();
            $table->boolean('decline_allowed')->default(true);
            $table->boolean('late_fee_applies')->default(false);
            $table->unsignedInteger('late_fee_cents')->nullable();
            $table->timestamp('proposed_starts_at')->nullable();
            $table->timestamp('proposed_ends_at')->nullable();
            $table->uuid('proposed_team_member_id')->nullable();
            $table->uuid('proposed_workspace_id')->nullable();
            $table->text('reason')->nullable();
            $table->string('action_token', 64)->unique();
            $table->unsignedTinyInteger('reminder_count')->default(0);
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_via', 32)->nullable();
            $table->uuid('resolved_by_team_member_id')->nullable();
            $table->uuid('staff_sos_alert_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('appointment_id')->references('id')->on('appointments')->cascadeOnDelete();
            $table->index(['tenant_id', 'status', 'last_reminded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_change_requests');
        Schema::dropIfExists('booking_policy_settings');
    }
};
