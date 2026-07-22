<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->integer('price_cents');
            $table->boolean('show_on_booking_carousel')->default(true);
            $table->integer('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['tenant_id', 'inventory_item_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'show_on_booking_carousel', 'sort_order']);
        });

        Schema::create('ecommerce_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->string('order_number');
            $table->string('status', 30)->default('pending_pickup');
            $table->string('payment_method', 30)->default('cash_in_salon');
            $table->string('payment_status', 30)->default('unpaid');
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('notes')->nullable();
            $table->integer('subtotal_cents');
            $table->integer('total_cents');
            $table->string('public_token', 64);
            $table->timestamps();

            $table->unique(['tenant_id', 'order_number']);
            $table->unique(['tenant_id', 'public_token']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'location_id']);
        });

        Schema::create('ecommerce_order_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('ecommerce_orders')->cascadeOnDelete();
            $table->foreignUuid('ecommerce_product_id')->constrained('ecommerce_products')->cascadeOnDelete();
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('title_snapshot');
            $table->decimal('quantity', 12, 3);
            $table->integer('unit_price_cents');
            $table->integer('line_total_cents');
            $table->timestamps();

            $table->index(['tenant_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_order_lines');
        Schema::dropIfExists('ecommerce_orders');
        Schema::dropIfExists('ecommerce_products');
    }
};
