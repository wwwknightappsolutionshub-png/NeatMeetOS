<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_checkouts', function (Blueprint $table) {
            $table->timestamp('reopened_at')->nullable()->after('completed_at');
            $table->foreignUuid('reopened_by_team_member_id')->nullable()->after('reopened_at')->constrained('team_members')->nullOnDelete();
            $table->text('reopen_reason')->nullable()->after('reopened_by_team_member_id');
            $table->timestamp('receipt_last_sent_at')->nullable()->after('reopen_reason');
            $table->string('receipt_last_delivery_method', 30)->nullable()->after('receipt_last_sent_at');
            $table->string('receipt_last_delivery_status', 30)->nullable()->after('receipt_last_delivery_method');
            $table->integer('refunded_total_cents')->default(0)->after('amount_due_cents');
            $table->integer('gift_card_redemption_cents')->default(0)->after('refunded_total_cents');
        });

        Schema::table('commerce_checkout_lines', function (Blueprint $table) {
            $table->string('discount_type', 30)->nullable()->after('discount_cents');
            $table->string('discount_reason')->nullable()->after('discount_type');
            $table->foreignUuid('discount_authorised_by_team_member_id')->nullable()->after('discount_reason')->constrained('team_members')->nullOnDelete();
            $table->decimal('returned_quantity', 12, 3)->default(0)->after('line_total_cents');
            $table->integer('returned_subtotal_cents')->default(0)->after('returned_quantity');
            $table->string('return_status', 30)->nullable()->default('not_returned')->after('returned_subtotal_cents');
        });

        Schema::create('commerce_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('commerce_checkout_id')->constrained('commerce_checkouts')->cascadeOnDelete();
            $table->string('receipt_number', 30);
            $table->string('delivery_method', 30)->nullable();
            $table->string('delivery_status', 30)->default('pending');
            $table->string('delivery_target')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('payload_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'receipt_number']);
            $table->index(['tenant_id', 'commerce_checkout_id']);
        });

        Schema::create('gift_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 40);
            $table->integer('initial_balance_cents');
            $table->integer('current_balance_cents');
            $table->string('status', 30)->default('active');
            $table->foreignUuid('issued_checkout_id')->nullable()->constrained('commerce_checkouts')->nullOnDelete();
            $table->foreignUuid('issued_to_client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('gift_card_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('gift_card_id')->constrained('gift_cards')->cascadeOnDelete();
            $table->string('type', 30);
            $table->integer('amount_cents');
            $table->foreignUuid('commerce_checkout_id')->nullable()->constrained('commerce_checkouts')->nullOnDelete();
            $table->foreignUuid('payment_refund_id')->nullable()->constrained('payment_refunds')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'gift_card_id']);
            $table->index(['commerce_checkout_id']);
        });

        Schema::table('payment_refunds', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('reason');
            $table->string('source', 30)->nullable()->after('notes');
            $table->foreignUuid('commerce_checkout_id')->nullable()->after('source')->constrained('commerce_checkouts')->nullOnDelete();

            $table->index(['tenant_id', 'commerce_checkout_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_refunds', function (Blueprint $table) {
            $table->dropForeign(['commerce_checkout_id']);
            $table->dropIndex(['tenant_id', 'commerce_checkout_id']);
            $table->dropColumn(['notes', 'source', 'commerce_checkout_id']);
        });

        Schema::dropIfExists('gift_card_transactions');
        Schema::dropIfExists('gift_cards');
        Schema::dropIfExists('commerce_receipts');

        Schema::table('commerce_checkout_lines', function (Blueprint $table) {
            $table->dropForeign(['discount_authorised_by_team_member_id']);
            $table->dropColumn([
                'discount_type',
                'discount_reason',
                'discount_authorised_by_team_member_id',
                'returned_quantity',
                'returned_subtotal_cents',
                'return_status',
            ]);
        });

        Schema::table('commerce_checkouts', function (Blueprint $table) {
            $table->dropForeign(['reopened_by_team_member_id']);
            $table->dropColumn([
                'reopened_at',
                'reopened_by_team_member_id',
                'reopen_reason',
                'receipt_last_sent_at',
                'receipt_last_delivery_method',
                'receipt_last_delivery_status',
                'refunded_total_cents',
                'gift_card_redemption_cents',
            ]);
        });
    }
};
