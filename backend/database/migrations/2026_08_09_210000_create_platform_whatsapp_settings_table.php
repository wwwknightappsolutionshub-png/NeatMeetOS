<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_whatsapp_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->boolean('enabled')->default(false);
            $table->string('provider', 32)->default('genius');
            $table->text('api_key')->nullable();
            $table->string('session_id')->nullable();
            $table->string('base_url')->nullable();
            $table->string('meta_phone_number_id')->nullable();
            $table->text('meta_access_token')->nullable();
            $table->string('twilio_account_sid')->nullable();
            $table->text('twilio_auth_token')->nullable();
            $table->string('twilio_from')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_whatsapp_settings');
    }
};
