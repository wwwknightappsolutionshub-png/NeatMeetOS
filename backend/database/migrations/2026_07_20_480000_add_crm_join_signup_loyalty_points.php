<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_loyalty_settings', function (Blueprint $table) {
            $table->unsignedInteger('crm_join_signup_points')->default(300)->after('value_cents_per_block');
        });
    }

    public function down(): void
    {
        Schema::table('membership_loyalty_settings', function (Blueprint $table) {
            $table->dropColumn('crm_join_signup_points');
        });
    }
};
