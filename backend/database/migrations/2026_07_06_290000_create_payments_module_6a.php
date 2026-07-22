<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignUuid('team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->string('transaction_type', 30);
            $table->string('direction', 20);
            $table->string('status', 30)->default('pending');
            $table->integer('amount_cents');
            $table->char('currency', 3)->default('GBP');
            $table->string('provider', 30)->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('external_reference')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->string('payment_method_type', 30)->nullable();
            $table->string('payment_method_label')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 50)->nullable();
            $table->text('failure_message')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignUuid('created_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->foreignUuid('updated_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'appointment_id']);
            $table->index(['tenant_id', 'client_id']);
            $table->index(['provider_reference']);
            $table->index(['processed_at']);
            $table->unique(['tenant_id', 'idempotency_key']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('payment_transaction_id')->constrained('payment_transactions')->cascadeOnDelete();
            $table->string('allocation_type', 30);
            $table->integer('amount_cents');
            $table->foreignUuid('commerce_checkout_id')->nullable()->constrained('commerce_checkouts')->nullOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignUuid('commerce_deposit_record_id')->nullable()->constrained('commerce_deposit_records')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['payment_transaction_id']);
            $table->index(['tenant_id', 'allocation_type']);
        });

        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('payment_transaction_id')->constrained('payment_transactions')->cascadeOnDelete();
            $table->foreignUuid('refund_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->integer('amount_cents');
            $table->string('reason')->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('provider_reference')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignUuid('created_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'payment_transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payment_transactions');
    }
};
