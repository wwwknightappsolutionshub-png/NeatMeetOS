<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->boolean('deposit_required')->default(false)->after('is_bookable_online');
            $table->unsignedInteger('deposit_amount_cents')->nullable()->after('deposit_required');
            $table->unsignedSmallInteger('min_lead_time_hours')->nullable()->after('deposit_amount_cents');
            $table->unsignedSmallInteger('cancellation_window_hours')->nullable()->after('min_lead_time_hours');
        });

        Schema::create('appointment_recurrence_series', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('pattern', 30)->default('weekly');
            $table->unsignedTinyInteger('interval_weeks')->default(1);
            $table->dateTime('anchor_starts_at');
            $table->date('end_date')->nullable();
            $table->unsignedSmallInteger('occurrence_count')->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('team_member_id')->constrained('team_members')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->json('service_template');
            $table->text('client_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignUuid('created_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignUuid('recurrence_series_id')->nullable()->after('cancellation_reason')
                ->constrained('appointment_recurrence_series')->nullOnDelete();
            $table->unsignedSmallInteger('occurrence_index')->nullable()->after('recurrence_series_id');
            $table->string('booking_reference', 32)->nullable()->after('occurrence_index');
            $table->string('deposit_status', 30)->default('not_required')->after('booking_reference');
            $table->unsignedInteger('deposit_required_cents')->nullable()->after('deposit_status');
            $table->json('deposit_rule_snapshot')->nullable()->after('deposit_required_cents');

            $table->unique(['tenant_id', 'booking_reference']);
            $table->index(['recurrence_series_id']);
        });

        Schema::table('appointment_services', function (Blueprint $table) {
            $table->uuid('package_entitlement_id')->nullable()->after('sort_order');
            $table->string('entitlement_source', 50)->nullable()->after('package_entitlement_id');
        });

        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignUuid('team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->foreignUuid('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->string('workspace_type_preference', 50)->nullable();
            $table->dateTime('preferred_starts_at')->nullable();
            $table->dateTime('preferred_ends_at')->nullable();
            $table->text('availability_notes')->nullable();
            $table->string('status', 20)->default('waiting');
            $table->text('notes')->nullable();
            $table->foreignUuid('fulfilled_appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignUuid('created_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('waitlist_services', function (Blueprint $table) {
            $table->foreignUuid('waitlist_entry_id')->constrained('waitlist_entries')->cascadeOnDelete();
            $table->foreignUuid('booking_service_id')->constrained('booking_services')->cascadeOnDelete();
            $table->string('service_name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->primary(['waitlist_entry_id', 'booking_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_services');
        Schema::dropIfExists('waitlist_entries');

        Schema::table('appointment_services', function (Blueprint $table) {
            $table->dropColumn(['package_entitlement_id', 'entitlement_source']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['recurrence_series_id']);
            $table->dropUnique(['tenant_id', 'booking_reference']);
            $table->dropColumn([
                'recurrence_series_id',
                'occurrence_index',
                'booking_reference',
                'deposit_status',
                'deposit_required_cents',
                'deposit_rule_snapshot',
            ]);
        });

        Schema::dropIfExists('appointment_recurrence_series');

        Schema::table('booking_services', function (Blueprint $table) {
            $table->dropColumn([
                'deposit_required',
                'deposit_amount_cents',
                'min_lead_time_hours',
                'cancellation_window_hours',
            ]);
        });
    }
};
