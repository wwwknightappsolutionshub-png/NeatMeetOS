<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_checkouts', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        Schema::table('commerce_checkouts', function (Blueprint $table) {
            $table->uuid('client_id')->nullable()->change();
            $table->string('checkout_number', 30)->nullable()->after('tenant_id');
            $table->integer('deposit_credit_cents')->default(0)->after('tip_cents');
            $table->integer('amount_paid_cents')->default(0)->after('total_cents');
            $table->integer('amount_due_cents')->default(0)->after('amount_paid_cents');
            $table->text('notes')->nullable()->after('metadata');
            $table->string('source', 30)->nullable()->after('notes');

            $table->unique(['tenant_id', 'checkout_number']);
            $table->index(['tenant_id', 'checkout_number']);
        });

        Schema::table('commerce_checkout_lines', function (Blueprint $table) {
            $table->integer('discount_cents')->default(0)->after('unit_price_cents');
        });

        Schema::table('commerce_checkout_appointments', function (Blueprint $table) {
            $table->integer('imported_subtotal_cents')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('commerce_checkout_appointments', function (Blueprint $table) {
            $table->dropColumn('imported_subtotal_cents');
        });

        Schema::table('commerce_checkout_lines', function (Blueprint $table) {
            $table->dropColumn('discount_cents');
        });

        Schema::table('commerce_checkouts', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'checkout_number']);
            $table->dropIndex(['tenant_id', 'checkout_number']);
            $table->dropColumn([
                'checkout_number',
                'deposit_credit_cents',
                'amount_paid_cents',
                'amount_due_cents',
                'notes',
                'source',
            ]);
        });
    }
};
