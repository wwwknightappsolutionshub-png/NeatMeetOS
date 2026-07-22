<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('workspace_status', 32)->default('complete')->after('is_platform_admin');
            $table->json('signup_meta')->nullable()->after('workspace_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['workspace_status', 'signup_meta']);
        });
    }
};
