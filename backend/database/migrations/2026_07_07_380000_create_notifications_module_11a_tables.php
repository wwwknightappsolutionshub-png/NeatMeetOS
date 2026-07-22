<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('channel', 20);
            $table->string('category', 20);
            $table->string('subject')->nullable();
            $table->text('body_text')->nullable();
            $table->text('body_html')->nullable();
            $table->json('variables_json')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'channel', 'category']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('notifications_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignUuid('checkout_id')->nullable()->constrained('commerce_checkouts')->nullOnDelete();
            $table->foreignUuid('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->foreignUuid('client_membership_id')->nullable()->constrained('client_memberships')->nullOnDelete();
            $table->foreignUuid('marketing_workflow_execution_id')->nullable()
                ->constrained('marketing_workflow_executions')->nullOnDelete();
            $table->foreignUuid('notification_template_id')->nullable()
                ->constrained('notifications_templates')->nullOnDelete();
            $table->string('source_type', 30);
            $table->string('purpose', 50);
            $table->string('channel', 20);
            $table->string('direction', 12)->default('outbound');
            $table->string('status', 20)->default('queued');
            $table->string('recipient_name')->nullable();
            $table->string('recipient_address')->nullable();
            $table->string('subject')->nullable();
            $table->text('body_text')->nullable();
            $table->text('body_html')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->foreignUuid('created_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'client_id']);
            $table->index(['tenant_id', 'appointment_id']);
            $table->index(['tenant_id', 'source_type']);
            $table->index(['tenant_id', 'purpose']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('notifications_message_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('notification_message_id')->constrained('notifications_messages')->cascadeOnDelete();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->string('provider', 30)->default('simulation');
            $table->string('provider_reference')->nullable();
            $table->string('status', 20)->default('queued');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['notification_message_id', 'attempt_number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->boolean('allow_email')->default(true);
            $table->boolean('allow_sms')->default(true);
            $table->boolean('allow_whatsapp')->default(false);
            $table->boolean('allow_push')->default(false);
            $table->boolean('booking_notifications')->default(true);
            $table->boolean('payment_notifications')->default(true);
            $table->boolean('membership_notifications')->default(true);
            $table->boolean('general_notifications')->default(true);
            $table->string('preferred_channel', 20)->nullable();
            $table->timestamp('last_synced_from_consent_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'client_id']);
        });

        Schema::create('notification_automation_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->boolean('booking_reminders_enabled')->default(true);
            $table->boolean('booking_confirmation_enabled')->default(true);
            $table->boolean('cancellation_notifications_enabled')->default(true);
            $table->boolean('payment_link_notifications_enabled')->default(true);
            $table->boolean('payment_reminders_enabled')->default(true);
            $table->boolean('membership_expiry_notifications_enabled')->default(true);
            $table->boolean('membership_renewal_notifications_enabled')->default(true);
            $table->unsignedInteger('default_booking_reminder_hours')->nullable()->default(24);
            $table->unsignedInteger('default_payment_reminder_days')->nullable()->default(3);
            $table->string('sender_name')->nullable();
            $table->string('sender_email')->nullable();
            $table->string('sender_sms_name')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_automation_settings');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications_message_attempts');
        Schema::dropIfExists('notifications_messages');
        Schema::dropIfExists('notifications_templates');
    }
};
