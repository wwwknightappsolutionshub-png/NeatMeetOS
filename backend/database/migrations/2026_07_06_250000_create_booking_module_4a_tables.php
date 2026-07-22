<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedInteger('base_price_cents')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_bookable_online')->default(false);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('team_member_id')->constrained('team_members')->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 30)->default('pending');
            $table->string('booking_source', 50)->default('admin');
            $table->text('client_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignUuid('created_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'starts_at']);
            $table->index(['tenant_id', 'team_member_id', 'starts_at']);
            $table->index(['tenant_id', 'workspace_id', 'starts_at']);
            $table->index(['client_id']);
        });

        Schema::create('appointment_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignUuid('booking_service_id')->nullable()->constrained('booking_services')->nullOnDelete();
            $table->string('service_name');
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedInteger('price_cents')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['appointment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_services');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('booking_services');
    }
};
