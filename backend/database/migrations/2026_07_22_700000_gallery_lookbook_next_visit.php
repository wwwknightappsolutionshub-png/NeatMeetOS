<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_works', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('image_url', 2048);
            $table->string('caption')->nullable();
            $table->string('service_tag')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_published', 'sort_order']);
        });

        Schema::create('lookbook_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('image_url', 2048);
            $table->string('title')->nullable();
            $table->string('caption')->nullable();
            $table->string('category_key', 40)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->boolean('is_seeded')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'is_published', 'sort_order']);
        });

        Schema::create('client_thread_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direction', 20);
            $table->string('channel', 20)->default('in_app');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('whatsapp_deeplink', 2048)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'client_id', 'created_at']);
        });

        Schema::table('client_visits', function (Blueprint $table) {
            $table->foreignUuid('next_visit_appointment_id')
                ->nullable()
                ->after('loyalty_points_awarded')
                ->constrained('appointments')
                ->nullOnDelete();
            $table->timestamp('next_visit_prompted_at')->nullable()->after('next_visit_appointment_id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('next_visit_reminded_72h_at')->nullable()->after('internal_notes');
            $table->timestamp('next_visit_reminded_24h_at')->nullable()->after('next_visit_reminded_72h_at');
            $table->foreignUuid('origin_visit_id')
                ->nullable()
                ->after('next_visit_reminded_24h_at')
                ->constrained('client_visits')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origin_visit_id');
            $table->dropColumn(['next_visit_reminded_72h_at', 'next_visit_reminded_24h_at']);
        });

        Schema::table('client_visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('next_visit_appointment_id');
            $table->dropColumn('next_visit_prompted_at');
        });

        Schema::dropIfExists('client_thread_messages');
        Schema::dropIfExists('lookbook_items');
        Schema::dropIfExists('gallery_works');
    }
};
