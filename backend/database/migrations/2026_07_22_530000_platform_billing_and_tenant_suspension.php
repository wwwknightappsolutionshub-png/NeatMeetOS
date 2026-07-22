<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('tenant_subscription_id')->nullable()->index();
            $table->uuid('subscription_plan_id')->nullable()->index();
            $table->string('invoice_number', 40)->unique();
            $table->string('status', 32)->default('open')->index();
            $table->string('currency', 3)->default('GBP');
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('amount_paid_cents')->default(0);
            $table->string('billing_interval', 20)->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->string('failure_reason')->nullable();
            $table->json('line_items_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('tenant_subscription_id')->references('id')->on('tenant_subscriptions')->nullOnDelete();
            $table->foreign('subscription_plan_id')->references('id')->on('subscription_plans')->nullOnDelete();
        });

        Schema::create('platform_invoice_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('platform_invoice_id')->index();
            $table->uuid('tenant_id')->index();
            $table->string('status', 32);
            $table->string('provider', 40)->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->json('response_json')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->foreign('platform_invoice_id')->references('id')->on('platform_invoices')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('activated_at');
            }
            if (! Schema::hasColumn('tenants', 'suspension_reason')) {
                $table->string('suspension_reason')->nullable()->after('suspended_at');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_invoice_attempts');
        Schema::dropIfExists('platform_invoices');

        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'suspension_reason')) {
                $table->dropColumn('suspension_reason');
            }
            if (Schema::hasColumn('tenants', 'suspended_at')) {
                $table->dropColumn('suspended_at');
            }
        });
    }
};
