<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 50);
            $table->string('channel', 20);
            $table->string('subject')->nullable();
            $table->longText('body_text');
            $table->longText('body_html')->nullable();
            $table->json('variables_json')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'channel', 'is_active']);
            $table->index(['tenant_id', 'category']);
        });

        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('campaign_type', 30);
            $table->string('trigger_type', 50)->nullable();
            $table->string('channel', 20);
            $table->string('status', 30)->default('draft');
            $table->foreignUuid('template_id')->nullable()->constrained('marketing_templates')->nullOnDelete();
            $table->string('audience_name')->nullable();
            $table->json('audience_rules_json')->nullable();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignUuid('created_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'campaign_type', 'trigger_type']);
        });

        Schema::create('marketing_audiences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->json('rules_json');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('marketing_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('marketing_campaign_id')->nullable()->constrained('marketing_campaigns')->nullOnDelete();
            $table->string('trigger_type', 50)->nullable();
            $table->string('run_source', 30)->default('manual');
            $table->string('status', 30)->default('pending');
            $table->json('filters_json')->nullable();
            $table->json('summary_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignUuid('created_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'marketing_campaign_id']);
        });

        Schema::create('marketing_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('marketing_campaign_id')->nullable()->constrained('marketing_campaigns')->nullOnDelete();
            $table->foreignUuid('marketing_run_id')->nullable()->constrained('marketing_runs')->nullOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignUuid('membership_id')->nullable()->constrained('client_memberships')->nullOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('channel', 20);
            $table->string('purpose', 50);
            $table->string('status', 30)->default('pending');
            $table->string('recipient_address')->nullable();
            $table->string('subject')->nullable();
            $table->longText('rendered_body_text')->nullable();
            $table->longText('rendered_body_html')->nullable();
            $table->json('template_snapshot_json')->nullable();
            $table->json('variables_snapshot_json')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('skipped_reason')->nullable();
            $table->string('provider_message_reference')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['marketing_run_id', 'status']);
            $table->index(['client_id', 'purpose']);
        });

        Schema::create('marketing_message_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('marketing_message_id')->constrained('marketing_messages')->cascadeOnDelete();
            $table->string('status', 30);
            $table->timestamp('attempted_at');
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->json('payload_json')->nullable();
            $table->json('response_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['marketing_message_id', 'attempted_at']);
        });

        Schema::create('marketing_automation_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->unsignedSmallInteger('booking_reminder_hours_before')->default(24);
            $table->unsignedSmallInteger('review_request_delay_hours')->default(24);
            $table->unsignedSmallInteger('rebooking_window_days')->nullable();
            $table->unsignedSmallInteger('win_back_inactivity_days')->nullable();
            $table->boolean('review_request_enabled')->default(true);
            $table->boolean('auto_pause_on_consent_withdrawal')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_automation_settings');
        Schema::dropIfExists('marketing_message_attempts');
        Schema::dropIfExists('marketing_messages');
        Schema::dropIfExists('marketing_runs');
        Schema::dropIfExists('marketing_audiences');
        Schema::dropIfExists('marketing_campaigns');
        Schema::dropIfExists('marketing_templates');
    }
};
