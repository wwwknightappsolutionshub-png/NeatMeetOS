<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('item_type', 20);
            $table->string('status', 20)->default('active');
            $table->string('brand')->nullable();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->string('unit_label')->nullable();
            $table->string('unit_size')->nullable();
            $table->integer('cost_price_cents')->nullable();
            $table->integer('retail_price_cents')->nullable();
            $table->string('tax_code', 30)->nullable();
            $table->foreignUuid('preferred_supplier_id')->nullable()->constrained('inventory_suppliers')->nullOnDelete();
            $table->string('barcode')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'item_type']);
            $table->unique(['tenant_id', 'sku']);
        });

        Schema::create('inventory_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->decimal('on_hand_quantity', 12, 3)->default(0);
            $table->decimal('reserved_quantity', 12, 3)->default(0);
            $table->decimal('reorder_point', 12, 3)->nullable();
            $table->decimal('reorder_target', 12, 3)->nullable();
            $table->timestamp('last_restocked_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'inventory_item_id', 'location_id']);
            $table->index(['tenant_id', 'location_id']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->string('movement_type', 30);
            $table->decimal('quantity_delta', 12, 3);
            $table->decimal('quantity_before', 12, 3)->nullable();
            $table->decimal('quantity_after', 12, 3)->nullable();
            $table->integer('unit_cost_cents')->nullable();
            $table->string('reference_type', 30)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignUuid('performed_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'inventory_item_id']);
            $table->index(['tenant_id', 'location_id']);
            $table->index(['tenant_id', 'movement_type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['created_at']);
        });

        Schema::create('service_inventory_consumption_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('booking_service_id')->constrained('booking_services')->cascadeOnDelete();
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->decimal('quantity_required', 12, 3);
            $table->string('consumption_mode', 20)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'booking_service_id']);
            $table->index(['tenant_id', 'inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_inventory_consumption_rules');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_levels');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_suppliers');
    }
};
