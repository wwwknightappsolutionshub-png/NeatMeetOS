<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_automation_workflows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('trigger_type', 50);
            $table->string('channel', 20);
            $table->string('status', 20)->default('draft');
            $table->json('audience_rules_json')->nullable();
            $table->foreignUuid('template_id')->nullable()->constrained('marketing_templates')->nullOnDelete();
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->unsignedInteger('cooldown_days')->nullable();
            $table->boolean('allow_repeat')->default(false);
            $table->unsignedInteger('max_executions_per_client')->nullable();
            $table->json('settings_json')->nullable();
            $table->foreignUuid('created_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'trigger_type', 'status']);
        });

        Schema::create('marketing_workflow_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('workflow_id')->constrained('marketing_automation_workflows')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('step_type', 30);
            $table->unsignedInteger('delay_minutes')->nullable();
            $table->foreignUuid('template_id')->nullable()->constrained('marketing_templates')->nullOnDelete();
            $table->string('channel', 20)->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'position']);
        });

        Schema::create('marketing_workflow_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('workflow_id')->constrained('marketing_automation_workflows')->cascadeOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignUuid('campaign_id')->nullable()->constrained('marketing_campaigns')->nullOnDelete();
            $table->string('trigger_type', 50);
            $table->string('trigger_reference_type')->nullable();
            $table->uuid('trigger_reference_id')->nullable();
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('current_step_position')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('context_json')->nullable();
            $table->foreignUuid('created_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'workflow_id', 'status']);
            $table->index(['tenant_id', 'client_id', 'workflow_id']);
            $table->index(['tenant_id', 'trigger_type']);
        });

        Schema::create('marketing_workflow_execution_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('workflow_execution_id')->constrained('marketing_workflow_executions')->cascadeOnDelete();
            $table->foreignUuid('workflow_step_id')->nullable()->constrained('marketing_workflow_steps')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('step_type', 30);
            $table->string('status', 20)->default('queued');
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->foreignUuid('message_id')->nullable()->constrained('marketing_messages')->nullOnDelete();
            $table->json('context_json')->nullable();
            $table->timestamps();

            $table->index(['workflow_execution_id', 'position']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('marketing_contact_suppressions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('channel', 20);
            $table->string('contact_value');
            $table->string('reason', 30);
            $table->string('source', 30)->default('system');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'channel', 'is_active']);
            $table->index(['tenant_id', 'contact_value']);
            $table->index(['tenant_id', 'client_id']);
        });

        Schema::table('marketing_messages', function (Blueprint $table) {
            $table->foreignUuid('workflow_execution_id')->nullable()->after('marketing_run_id')
                ->constrained('marketing_workflow_executions')->nullOnDelete();
            $table->foreignUuid('workflow_step_id')->nullable()->after('workflow_execution_id')
                ->constrained('marketing_workflow_steps')->nullOnDelete();
            $table->timestamp('suppressed_at')->nullable()->after('failed_at');
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
            $table->timestamp('opened_at')->nullable()->after('delivered_at');
            $table->timestamp('clicked_at')->nullable()->after('opened_at');
            $table->timestamp('unsubscribed_at')->nullable()->after('clicked_at');
            $table->string('failure_category')->nullable()->after('error_message');
            $table->string('provider_message_id')->nullable()->after('provider_message_reference');
        });

        Schema::table('marketing_message_attempts', function (Blueprint $table) {
            $table->string('provider_message_id')->nullable()->after('provider_reference');
            $table->string('failure_category')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_message_attempts', function (Blueprint $table) {
            $table->dropColumn(['provider_message_id', 'failure_category']);
        });

        Schema::table('marketing_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_execution_id');
            $table->dropConstrainedForeignId('workflow_step_id');
            $table->dropColumn([
                'suppressed_at', 'delivered_at', 'opened_at', 'clicked_at',
                'unsubscribed_at', 'failure_category', 'provider_message_id',
            ]);
        });

        Schema::dropIfExists('marketing_contact_suppressions');
        Schema::dropIfExists('marketing_workflow_execution_steps');
        Schema::dropIfExists('marketing_workflow_executions');
        Schema::dropIfExists('marketing_workflow_steps');
        Schema::dropIfExists('marketing_automation_workflows');
    }
};
