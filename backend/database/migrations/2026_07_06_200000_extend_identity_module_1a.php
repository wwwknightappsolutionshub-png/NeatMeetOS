<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('trading_name')->nullable()->after('name');
            $table->string('business_type')->nullable()->after('trading_name');
            $table->string('timezone')->default('Europe/London')->after('status');
            $table->string('contact_email')->nullable()->after('timezone');
            $table->string('contact_phone')->nullable()->after('contact_email');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->string('contact_email')->nullable()->after('address');
            $table->string('contact_phone')->nullable()->after('contact_email');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('code')->nullable()->after('name');
            $table->json('metadata')->nullable()->after('workspace_type');
            $table->unique(['location_id', 'code']);
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('user_id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone')->nullable()->after('display_name');
            $table->foreignUuid('primary_location_id')->nullable()->after('phone')
                ->constrained('locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('primary_location_id');
            $table->dropColumn(['first_name', 'last_name', 'phone']);
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropUnique(['location_id', 'code']);
            $table->dropColumn(['code', 'metadata']);
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['contact_email', 'contact_phone']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'trading_name',
                'business_type',
                'timezone',
                'contact_email',
                'contact_phone',
            ]);
        });
    }
};
