<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_gift_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('package_product_id')->index();
            $table->uuid('from_client_id')->index();
            $table->uuid('from_client_package_id')->nullable()->index();
            $table->uuid('claimed_by_client_id')->nullable()->index();
            $table->uuid('claimed_client_package_id')->nullable()->index();
            $table->string('code', 32)->unique();
            $table->string('status', 24)->default('open')->index();
            $table->decimal('quantity', 10, 3);
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('claimed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('package_product_id')->references('id')->on('package_products')->cascadeOnDelete();
            $table->foreign('from_client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->foreign('from_client_package_id')->references('id')->on('client_packages')->nullOnDelete();
            $table->foreign('claimed_by_client_id')->references('id')->on('clients')->nullOnDelete();
            $table->foreign('claimed_client_package_id')->references('id')->on('client_packages')->nullOnDelete();
        });

        Schema::create('member_push_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('client_id')->index();
            $table->text('endpoint');
            $table->string('endpoint_hash', 64)->index();
            $table->string('p256dh', 255)->nullable();
            $table->string('auth', 255)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'endpoint_hash']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_push_subscriptions');
        Schema::dropIfExists('package_gift_codes');
    }
};
