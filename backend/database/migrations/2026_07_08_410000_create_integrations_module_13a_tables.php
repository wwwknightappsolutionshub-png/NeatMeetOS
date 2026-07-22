<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 30);
            $table->string('driver', 30);
            $table->string('status', 20)->default('active');
            $table->boolean('is_default')->default(false);
            $table->json('configuration_json')->nullable();
            $table->text('credentials_json')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_address')->nullable();
            $table->string('reply_to')->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_result')->nullable();
            $table->foreignUuid('created_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->foreignUuid('updated_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'category']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'category', 'is_default']);
        });

        Schema::create('provider_delivery_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('provider_account_id')->nullable()->constrained('provider_accounts')->nullOnDelete();
            $table->string('category', 30);
            $table->string('source_domain', 30);
            $table->string('source_type', 50);
            $table->uuid('source_id')->nullable();
            $table->foreignUuid('related_client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignUuid('related_appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignUuid('related_payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->string('direction', 15)->default('outbound');
            $table->string('purpose', 50)->nullable();
            $table->string('recipient_address')->nullable();
            $table->string('recipient_phone', 30)->nullable();
            $table->string('subject')->nullable();
            $table->json('payload_json')->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('failure_code', 50)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'category']);
            $table->index(['tenant_id', 'source_domain']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'related_client_id']);
            $table->index(['tenant_id', 'created_at']);
            $table->unique(['tenant_id', 'idempotency_key']);
        });

        Schema::create('provider_webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignUuid('provider_account_id')->nullable()->constrained('provider_accounts')->nullOnDelete();
            $table->string('category', 30)->nullable();
            $table->string('driver', 30);
            $table->string('event_type', 100);
            $table->string('external_event_id')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('processing_status', 20)->default('received');
            $table->text('processing_error')->nullable();
            $table->boolean('signature_valid')->nullable();
            $table->json('payload_json');
            $table->json('headers_json')->nullable();
            $table->string('resolved_source_domain', 30)->nullable();
            $table->string('resolved_source_type', 50)->nullable();
            $table->uuid('resolved_source_id')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'driver']);
            $table->index(['tenant_id', 'processing_status']);
            $table->index(['provider_account_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_webhook_events');
        Schema::dropIfExists('provider_delivery_attempts');
        Schema::dropIfExists('provider_accounts');
    }
};
