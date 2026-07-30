<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Speed indexes for slot search + AI admin queue sort.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_availability_rules', function (Blueprint $table) {
            $table->index(
                ['tenant_id', 'team_member_id', 'location_id', 'day_of_week', 'is_active'],
                'staff_avail_rules_slot_lookup_idx'
            );
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->index(
                ['tenant_id', 'team_member_id', 'status', 'starts_at'],
                'appointments_provider_status_starts_idx'
            );
            $table->index(
                ['tenant_id', 'workspace_id', 'status', 'starts_at'],
                'appointments_workspace_status_starts_idx'
            );
        });

        Schema::table('ai_hairstyle_sessions', function (Blueprint $table) {
            $table->index(
                ['tenant_id', 'status', 'submitted_at'],
                'ai_hairstyle_sessions_status_submitted_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('staff_availability_rules', function (Blueprint $table) {
            $table->dropIndex('staff_avail_rules_slot_lookup_idx');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_provider_status_starts_idx');
            $table->dropIndex('appointments_workspace_status_starts_idx');
        });

        Schema::table('ai_hairstyle_sessions', function (Blueprint $table) {
            $table->dropIndex('ai_hairstyle_sessions_status_submitted_idx');
        });
    }
};
