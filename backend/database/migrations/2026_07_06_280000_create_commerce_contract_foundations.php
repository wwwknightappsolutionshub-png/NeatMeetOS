<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_checkouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignUuid('team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->string('status', 30)->default('draft');
            $table->char('currency', 3)->default('GBP');
            $table->integer('subtotal_cents')->default(0);
            $table->integer('discount_cents')->default(0);
            $table->integer('tax_cents')->default(0);
            $table->integer('tip_cents')->default(0);
            $table->integer('total_cents')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at']);
        });

        Schema::create('commerce_checkout_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('checkout_id')->constrained('commerce_checkouts')->cascadeOnDelete();
            $table->string('line_type', 40);
            $table->string('description');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->integer('unit_price_cents');
            $table->integer('line_total_cents');
            $table->string('reference_type', 50)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->json('pricing_snapshot')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['checkout_id', 'sort_order']);
        });

        Schema::create('commerce_checkout_appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('checkout_id')->constrained('commerce_checkouts')->cascadeOnDelete();
            $table->foreignUuid('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->string('role', 20)->default('primary');
            $table->timestamps();

            $table->unique(['checkout_id', 'appointment_id']);
        });

        Schema::create('commerce_deposit_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->string('booking_deposit_status', 30);
            $table->integer('required_cents');
            $table->integer('collected_cents')->nullable();
            $table->string('lifecycle_state', 30);
            $table->uuid('payment_transaction_id')->nullable();
            $table->foreignUuid('applied_checkout_id')->nullable()->constrained('commerce_checkouts')->nullOnDelete();
            $table->json('rule_snapshot')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'appointment_id']);
            $table->index(['lifecycle_state']);
        });

        Schema::create('commerce_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('event_name', 80);
            $table->string('aggregate_type', 80);
            $table->uuid('aggregate_id');
            $table->json('payload');
            $table->timestamp('emitted_at');
            $table->timestamps();

            $table->index(['tenant_id', 'event_name', 'emitted_at']);
            $table->index(['aggregate_type', 'aggregate_id']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('billing_settlement_status', 30)
                ->default('not_applicable')
                ->after('deposit_rule_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('billing_settlement_status');
        });

        Schema::dropIfExists('commerce_events');
        Schema::dropIfExists('commerce_deposit_records');
        Schema::dropIfExists('commerce_checkout_appointments');
        Schema::dropIfExists('commerce_checkout_lines');
        Schema::dropIfExists('commerce_checkouts');
    }
};
