<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_saved_reports', function (Blueprint $table) {
            $table->json('delivery_emails')->nullable()->after('schedule_time');
        });
    }

    public function down(): void
    {
        Schema::table('analytics_saved_reports', function (Blueprint $table) {
            $table->dropColumn('delivery_emails');
        });
    }
};
