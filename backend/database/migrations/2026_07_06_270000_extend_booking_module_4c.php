<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['team_member_id']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->uuid('team_member_id')->nullable()->change();
            $table->foreign('team_member_id')->references('id')->on('team_members')->nullOnDelete();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('walk_in_stage', 20)->nullable()->after('booking_source');
            $table->timestamp('arrived_at')->nullable()->after('walk_in_stage');
            $table->string('no_show_reason', 500)->nullable()->after('cancellation_reason');
            $table->string('status_correction_note', 500)->nullable()->after('no_show_reason');
            $table->uuid('rebooked_from_appointment_id')->nullable()->after('status_correction_note');
            $table->foreign('rebooked_from_appointment_id')
                ->references('id')
                ->on('appointments')
                ->nullOnDelete();
        });

        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->timestamp('contacted_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['rebooked_from_appointment_id']);
            $table->dropColumn([
                'walk_in_stage',
                'arrived_at',
                'no_show_reason',
                'status_correction_note',
                'rebooked_from_appointment_id',
            ]);
        });

        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->dropColumn('contacted_at');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['team_member_id']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->uuid('team_member_id')->nullable(false)->change();
            $table->foreign('team_member_id')->references('id')->on('team_members')->cascadeOnDelete();
        });
    }
};
