<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_bans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ip', 45)->index();
            $table->string('reason')->nullable();
            $table->string('source', 32); // turnstile|throttle|honeypot|login
            $table->timestamp('banned_until')->nullable()->index();
            $table->unsignedInteger('hit_count')->default(1);
            $table->timestamps();

            $table->unique(['ip']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_bans');
    }
};
