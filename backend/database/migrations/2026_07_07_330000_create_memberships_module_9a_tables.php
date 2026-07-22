<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active');
            $table->string('plan_type', 30)->default('membership');
            $table->string('billing_frequency', 30)->nullable();
            $table->integer('price_cents')->default(0);
            $table->integer('joining_fee_cents')->default(0);
            $table->integer('included_wallet_credit_cents')->default(0);
            $table->integer('included_loyalty_points')->default(0);
            $table->integer('included_entitlement_quantity')->default(0);
            $table->boolean('auto_renew')->default(true);
            $table->unsignedSmallInteger('grace_period_days')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('applies_to_all_locations')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->timestamp('archived_at')->nullable();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('membership_plan_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('membership_plan_id')->constrained('membership_plans')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['membership_plan_id', 'location_id']);
        });

        Schema::create('package_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active');
            $table->integer('price_cents');
            $table->decimal('included_quantity', 12, 3);
            $table->unsignedInteger('expiry_days')->nullable();
            $table->boolean('is_public')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->timestamp('archived_at')->nullable();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('package_product_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('package_product_id')->constrained('package_products')->cascadeOnDelete();
            $table->foreignUuid('booking_service_id')->constrained('booking_services')->cascadeOnDelete();
            $table->decimal('quantity_per_redemption', 12, 3)->nullable();
            $table->timestamps();

            $table->unique(['package_product_id', 'booking_service_id'], 'package_product_services_unique');
        });

        Schema::create('client_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('membership_plan_id')->constrained('membership_plans')->restrictOnDelete();
            $table->string('status', 30)->default('active');
            $table->string('source', 30)->default('admin');
            $table->timestamp('started_at');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->date('next_billing_date')->nullable();
            $table->date('billing_anchor_date')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('price_cents_snapshot');
            $table->integer('joining_fee_cents_snapshot')->default(0);
            $table->integer('included_wallet_credit_cents_snapshot')->default(0);
            $table->integer('included_loyalty_points_snapshot')->default(0);
            $table->integer('included_entitlement_quantity_snapshot')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'client_id', 'status']);
        });

        Schema::create('client_wallet_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('entry_type', 40);
            $table->string('direction', 10);
            $table->integer('amount_cents');
            $table->timestamp('balance_effective_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('source_type')->nullable();
            $table->uuid('source_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'client_id']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('client_loyalty_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('entry_type', 40);
            $table->string('direction', 10);
            $table->integer('points');
            $table->timestamp('effective_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('source_type')->nullable();
            $table->uuid('source_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'client_id']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('client_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('package_product_id')->constrained('package_products')->restrictOnDelete();
            $table->string('status', 30)->default('active');
            $table->string('source', 30)->default('manual');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->decimal('quantity_total', 12, 3);
            $table->decimal('quantity_remaining', 12, 3);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'client_id', 'status']);
        });

        Schema::create('client_package_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('client_package_id')->constrained('client_packages')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('booking_service_id')->nullable()->constrained('booking_services')->nullOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignUuid('checkout_id')->nullable()->constrained('commerce_checkouts')->nullOnDelete();
            $table->string('redemption_type', 40);
            $table->decimal('quantity', 12, 3);
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'client_package_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_package_redemptions');
        Schema::dropIfExists('client_packages');
        Schema::dropIfExists('client_loyalty_entries');
        Schema::dropIfExists('client_wallet_entries');
        Schema::dropIfExists('client_memberships');
        Schema::dropIfExists('package_product_services');
        Schema::dropIfExists('package_products');
        Schema::dropIfExists('membership_plan_locations');
        Schema::dropIfExists('membership_plans');
    }
};
