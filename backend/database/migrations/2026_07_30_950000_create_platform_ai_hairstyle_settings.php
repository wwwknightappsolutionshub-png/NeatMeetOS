<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_ai_hairstyle_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 40)->default('stub');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_ai_hairstyle_settings');
    }
};
