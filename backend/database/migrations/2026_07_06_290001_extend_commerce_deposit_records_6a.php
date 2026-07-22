<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_deposit_records', function (Blueprint $table) {
            $table->foreignUuid('refunded_payment_transaction_id')->nullable()->after('payment_transaction_id')
                ->constrained('payment_transactions')->nullOnDelete();
            $table->timestamp('collected_at')->nullable()->after('lifecycle_state');
            $table->timestamp('refunded_at')->nullable()->after('collected_at');
            $table->string('failure_code', 50)->nullable()->after('refunded_at');
            $table->text('failure_message')->nullable()->after('failure_code');
            $table->text('manual_notes')->nullable()->after('failure_message');
        });
    }

    public function down(): void
    {
        Schema::table('commerce_deposit_records', function (Blueprint $table) {
            $table->dropForeign(['refunded_payment_transaction_id']);
            $table->dropColumn([
                'refunded_payment_transaction_id',
                'collected_at',
                'refunded_at',
                'failure_code',
                'failure_message',
                'manual_notes',
            ]);
        });
    }
};
