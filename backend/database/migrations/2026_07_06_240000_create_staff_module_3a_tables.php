<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('team_member_id')->unique()->constrained('team_members')->cascadeOnDelete();
            $table->boolean('is_bookable')->default(false);
            $table->boolean('show_in_online_booking')->default(false);
            $table->boolean('accepts_walk_ins')->default(false);
            $table->string('booking_display_name')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignUuid('default_workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->unsignedSmallInteger('min_lead_time_minutes')->nullable();
            $table->unsignedSmallInteger('buffer_minutes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_bookable']);
        });

        Schema::create('staff_operating_locations', function (Blueprint $table) {
            $table->foreignUuid('team_member_id')->constrained('team_members')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->primary(['team_member_id', 'location_id']);
        });

        Schema::create('staff_availability_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('team_member_id')->constrained('team_members')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'team_member_id', 'is_active']);
        });

        Schema::create('staff_absences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('team_member_id')->constrained('team_members')->cascadeOnDelete();
            $table->string('category', 50);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->text('note')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['tenant_id', 'team_member_id', 'status']);
            $table->index(['team_member_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_absences');
        Schema::dropIfExists('staff_availability_rules');
        Schema::dropIfExists('staff_operating_locations');
        Schema::dropIfExists('staff_profiles');
    }
};
