<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_reservation_payment_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignUuid('booking_service_id')->nullable()->constrained('booking_services')->nullOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('payment_method', 32);
            $table->string('status', 32)->default('pending_review');
            $table->string('proof_path')->nullable();
            $table->string('proof_original_name')->nullable();
            $table->string('proof_mime', 64)->nullable();
            $table->unsignedInteger('proof_size_bytes')->nullable();
            $table->string('public_token', 64)->unique();
            $table->foreignUuid('reviewed_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'appointment_id']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_reservation_payment_documents');
    }
};
