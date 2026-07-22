<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('is_system');
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->text('description')->nullable()->after('slug');
            $table->string('billing_interval')->default('monthly')->after('description');
            $table->json('limits')->nullable()->after('features');
            $table->unsignedInteger('display_price_cents')->nullable()->after('limits');
            $table->boolean('is_active')->default(true)->after('display_price_cents');
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('status')->default('active');
            $table->string('billing_interval')->default('monthly');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->string('provider')->nullable();
            $table->string('external_subscription_id')->nullable();
            $table->string('billing_customer_id')->nullable();
            $table->timestamps();

            $table->index(['status']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['tenant_id', 'entity_type', 'created_at']);
            $table->index(['tenant_id', 'action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'entity_type', 'created_at']);
            $table->dropIndex(['tenant_id', 'action', 'created_at']);
        });

        Schema::dropIfExists('tenant_subscriptions');

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'billing_interval',
                'limits',
                'display_price_cents',
                'is_active',
            ]);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
