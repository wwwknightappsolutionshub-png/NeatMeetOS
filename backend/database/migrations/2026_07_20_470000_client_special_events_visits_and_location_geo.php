<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedTinyInteger('special_event_month')->nullable()->after('date_of_birth');
            $table->unsignedTinyInteger('special_event_day')->nullable()->after('special_event_month');
            $table->string('special_event_label', 80)->nullable()->after('special_event_day');
            $table->timestamp('last_visited_at')->nullable()->after('loyalty_display_status');
        });

        Schema::create('client_visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->timestamp('checked_in_at');
            $table->string('source', 40)->default('member_app');
            $table->integer('loyalty_points_awarded')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'client_id']);
            $table->index(['client_id', 'checked_in_at']);
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('timezone');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('geofence_radius_meters')->default(150)->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'geofence_radius_meters']);
        });

        Schema::dropIfExists('client_visits');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'special_event_month',
                'special_event_day',
                'special_event_label',
                'last_visited_at',
            ]);
        });
    }
};
