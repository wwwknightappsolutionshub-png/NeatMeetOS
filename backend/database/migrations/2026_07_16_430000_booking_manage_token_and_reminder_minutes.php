<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('public_manage_token', 64)->nullable()->after('booking_reference');
            $table->unique(['tenant_id', 'public_manage_token']);
        });

        Schema::table('notification_automation_settings', function (Blueprint $table) {
            $table->unsignedInteger('default_booking_reminder_minutes')
                ->nullable()
                ->default(45)
                ->after('default_booking_reminder_hours');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'public_manage_token']);
            $table->dropColumn('public_manage_token');
        });

        Schema::table('notification_automation_settings', function (Blueprint $table) {
            $table->dropColumn('default_booking_reminder_minutes');
        });
    }
};
