<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignUuid('preferred_team_member_id')->nullable()->after('primary_location_id')
                ->constrained('team_members')->nullOnDelete();
            $table->json('preferences')->nullable()->after('internal_flags');
            $table->string('loyalty_display_status')->nullable()->after('preferences');
        });

        Schema::create('client_formulas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('title');
            $table->text('formula_body');
            $table->string('category')->nullable();
            $table->string('service_context')->nullable();
            $table->foreignUuid('recorded_by_team_member_id')->nullable()
                ->constrained('team_members')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['client_id', 'is_active']);
        });

        Schema::create('client_photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('storage_path');
            $table->string('category')->default('reference');
            $table->string('caption')->nullable();
            $table->foreignUuid('uploaded_by_team_member_id')->nullable()
                ->constrained('team_members')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['client_id', 'is_active']);
        });

        Schema::create('client_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('title');
            $table->string('document_type')->default('reference');
            $table->string('storage_path');
            $table->text('description')->nullable();
            $table->foreignUuid('uploaded_by_team_member_id')->nullable()
                ->constrained('team_members')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['client_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_documents');
        Schema::dropIfExists('client_photos');
        Schema::dropIfExists('client_formulas');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preferred_team_member_id');
            $table->dropColumn(['preferences', 'loyalty_display_status']);
        });
    }
};
