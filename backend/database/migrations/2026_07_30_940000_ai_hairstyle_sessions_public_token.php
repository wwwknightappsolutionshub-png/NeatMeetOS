<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_hairstyle_sessions', function (Blueprint $table) {
            $table->string('public_token', 64)->nullable()->after('id');
            $table->unique(['tenant_id', 'public_token']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_hairstyle_sessions', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'public_token']);
            $table->dropColumn('public_token');
        });
    }
};
