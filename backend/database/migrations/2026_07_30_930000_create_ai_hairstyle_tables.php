<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Hairstyle Preview domain tables.
 * Privacy (P1): no original selfie column — composites only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_hairstyle_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('status', 32);
            $table->json('selected_preview_ids')->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->string('provider', 40)->nullable();
            $table->string('external_job_id', 191)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'client_id']);
        });

        Schema::create('ai_hairstyle_previews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('session_id')->constrained('ai_hairstyle_sessions')->cascadeOnDelete();
            $table->string('status', 32);
            $table->string('composite_image_url', 2048)->nullable();
            $table->string('style_label')->nullable();
            $table->string('style_key', 80)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('provider_meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'session_id', 'sort_order']);
            $table->index(['session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_hairstyle_previews');
        Schema::dropIfExists('ai_hairstyle_sessions');
    }
};
