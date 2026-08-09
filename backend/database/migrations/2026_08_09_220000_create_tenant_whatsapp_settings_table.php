<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_whatsapp_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->boolean('enabled')->default(false);
            $table->string('provider', 32)->default('genius');
            $table->string('hosted_session_id')->nullable();
            $table->string('hosted_phone_number')->nullable();
            $table->string('hosted_status', 32)->default('inactive');
            $table->text('hosted_qr_payload')->nullable();
            $table->timestamp('hosted_connected_at')->nullable();
            $table->timestamp('hosted_last_seen_at')->nullable();
            $table->timestamp('hosted_expires_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'hosted_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_whatsapp_settings');
    }
};
