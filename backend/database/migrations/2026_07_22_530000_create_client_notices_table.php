<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_notices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('client_id')->index();
            $table->uuid('marketing_message_id')->nullable()->index();
            $table->string('type', 80)->default('marketing.in_app');
            $table->string('title');
            $table->text('body');
            $table->string('href')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->index(['tenant_id', 'client_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_notices');
    }
};
