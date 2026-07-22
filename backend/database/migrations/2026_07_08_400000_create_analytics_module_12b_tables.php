<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_saved_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('created_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->string('name');
            $table->string('report_type', 30);
            $table->json('filters_json')->nullable();
            $table->string('export_format', 10)->default('csv');
            $table->boolean('is_scheduled')->default(false);
            $table->string('schedule_frequency', 20)->nullable();
            $table->unsignedTinyInteger('schedule_day_of_week')->nullable();
            $table->unsignedTinyInteger('schedule_day_of_month')->nullable();
            $table->string('schedule_time', 10)->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'report_type']);
            $table->index(['tenant_id', 'archived_at']);
        });

        Schema::create('analytics_export_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('analytics_saved_report_id')->nullable()->constrained('analytics_saved_reports')->nullOnDelete();
            $table->foreignUuid('created_by_team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->string('report_type', 30);
            $table->string('export_format', 10);
            $table->string('status', 20)->default('pending');
            $table->json('filters_json')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_disk', 50)->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'report_type']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_export_jobs');
        Schema::dropIfExists('analytics_saved_reports');
    }
};
