<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_loyalty_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->boolean('is_loyalty_redemption_enabled')->default(false);
            $table->unsignedInteger('points_per_redemption_block')->default(100);
            $table->unsignedInteger('value_cents_per_block')->default(1000);
            $table->timestamps();
        });

        Schema::table('client_package_redemptions', function (Blueprint $table) {
            $table->string('state', 30)->default('redeemed')->after('redemption_type');
            $table->foreignUuid('appointment_service_line_id')->nullable()->after('appointment_id')
                ->constrained('appointment_services')->nullOnDelete();
            $table->foreignUuid('checkout_line_id')->nullable()->after('checkout_id')
                ->constrained('commerce_checkout_lines')->nullOnDelete();
            $table->timestamp('reserved_at')->nullable()->after('notes');
            $table->timestamp('redeemed_at')->nullable()->after('reserved_at');
            $table->timestamp('restored_at')->nullable()->after('redeemed_at');
            $table->timestamp('released_at')->nullable()->after('restored_at');
            $table->string('restoration_reason')->nullable()->after('released_at');
            $table->integer('unit_value_cents')->nullable()->after('restoration_reason');
            $table->integer('covered_amount_cents')->nullable()->after('unit_value_cents');

            $table->index(['tenant_id', 'state']);
            $table->index(['appointment_service_line_id']);
        });

        Schema::table('client_wallet_entries', function (Blueprint $table) {
            $table->foreignUuid('checkout_id')->nullable()->after('source_id')
                ->constrained('commerce_checkouts')->nullOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->after('checkout_id')
                ->constrained('appointments')->nullOnDelete();
            $table->string('reference_type')->nullable()->after('appointment_id');
            $table->uuid('reference_id')->nullable()->after('reference_type');
            $table->foreignUuid('restores_entry_id')->nullable()->after('reference_id')
                ->constrained('client_wallet_entries')->nullOnDelete();
        });

        Schema::table('client_loyalty_entries', function (Blueprint $table) {
            $table->foreignUuid('checkout_id')->nullable()->after('source_id')
                ->constrained('commerce_checkouts')->nullOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->after('checkout_id')
                ->constrained('appointments')->nullOnDelete();
            $table->string('reference_type')->nullable()->after('appointment_id');
            $table->uuid('reference_id')->nullable()->after('reference_type');
            $table->foreignUuid('restores_entry_id')->nullable()->after('reference_id')
                ->constrained('client_loyalty_entries')->nullOnDelete();
            $table->integer('monetary_value_cents')->nullable()->after('restores_entry_id');
        });

        Schema::table('commerce_checkouts', function (Blueprint $table) {
            $table->integer('wallet_credit_cents')->default(0)->after('gift_card_redemption_cents');
            $table->integer('loyalty_discount_cents')->default(0)->after('wallet_credit_cents');
            $table->unsignedInteger('loyalty_points_redeemed')->default(0)->after('loyalty_discount_cents');
            $table->integer('package_covered_cents')->default(0)->after('loyalty_points_redeemed');
        });

        Schema::table('commerce_checkout_lines', function (Blueprint $table) {
            $table->string('membership_application_type', 30)->nullable()->after('return_status');
            $table->foreignUuid('client_package_id')->nullable()->after('membership_application_type')
                ->constrained('client_packages')->nullOnDelete();
            $table->foreignUuid('client_package_redemption_id')->nullable()->after('client_package_id')
                ->constrained('client_package_redemptions')->nullOnDelete();
            $table->decimal('covered_quantity', 12, 3)->nullable()->after('client_package_redemption_id');
            $table->integer('covered_amount_cents')->default(0)->after('covered_quantity');
        });

        Schema::table('appointment_services', function (Blueprint $table) {
            $table->string('entitlement_state', 30)->nullable()->after('entitlement_source');
            $table->foreignUuid('client_package_id')->nullable()->after('entitlement_state')
                ->constrained('client_packages')->nullOnDelete();
            $table->foreignUuid('client_package_redemption_id')->nullable()->after('client_package_id')
                ->constrained('client_package_redemptions')->nullOnDelete();
            $table->decimal('covered_quantity', 12, 3)->nullable()->after('client_package_redemption_id');
            $table->integer('covered_amount_cents')->default(0)->after('covered_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_services', function (Blueprint $table) {
            $table->dropForeign(['client_package_redemption_id']);
            $table->dropForeign(['client_package_id']);
            $table->dropColumn([
                'entitlement_state', 'client_package_id', 'client_package_redemption_id',
                'covered_quantity', 'covered_amount_cents',
            ]);
        });

        Schema::table('commerce_checkout_lines', function (Blueprint $table) {
            $table->dropForeign(['client_package_redemption_id']);
            $table->dropForeign(['client_package_id']);
            $table->dropColumn([
                'membership_application_type', 'client_package_id', 'client_package_redemption_id',
                'covered_quantity', 'covered_amount_cents',
            ]);
        });

        Schema::table('commerce_checkouts', function (Blueprint $table) {
            $table->dropColumn([
                'wallet_credit_cents', 'loyalty_discount_cents',
                'loyalty_points_redeemed', 'package_covered_cents',
            ]);
        });

        Schema::table('client_loyalty_entries', function (Blueprint $table) {
            $table->dropForeign(['restores_entry_id']);
            $table->dropForeign(['appointment_id']);
            $table->dropForeign(['checkout_id']);
            $table->dropColumn([
                'checkout_id', 'appointment_id', 'reference_type', 'reference_id',
                'restores_entry_id', 'monetary_value_cents',
            ]);
        });

        Schema::table('client_wallet_entries', function (Blueprint $table) {
            $table->dropForeign(['restores_entry_id']);
            $table->dropForeign(['appointment_id']);
            $table->dropForeign(['checkout_id']);
            $table->dropColumn([
                'checkout_id', 'appointment_id', 'reference_type', 'reference_id', 'restores_entry_id',
            ]);
        });

        Schema::table('client_package_redemptions', function (Blueprint $table) {
            $table->dropForeign(['checkout_line_id']);
            $table->dropForeign(['appointment_service_line_id']);
            $table->dropColumn([
                'state', 'appointment_service_line_id', 'checkout_line_id',
                'reserved_at', 'redeemed_at', 'restored_at', 'released_at',
                'restoration_reason', 'unit_value_cents', 'covered_amount_cents',
            ]);
        });

        Schema::dropIfExists('membership_loyalty_settings');
    }
};
