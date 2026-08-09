<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_whatsapp_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_whatsapp_settings', 'signup_welcome_enabled')) {
                $table->boolean('signup_welcome_enabled')->default(true)->after('twilio_from');
            }
            if (! Schema::hasColumn('platform_whatsapp_settings', 'signup_welcome_trial_body')) {
                $table->text('signup_welcome_trial_body')->nullable()->after('signup_welcome_enabled');
            }
            if (! Schema::hasColumn('platform_whatsapp_settings', 'signup_welcome_activation_body')) {
                $table->text('signup_welcome_activation_body')->nullable()->after('signup_welcome_trial_body');
            }
            if (! Schema::hasColumn('platform_whatsapp_settings', 'signup_welcome_banner_path')) {
                $table->string('signup_welcome_banner_path')->nullable()->after('signup_welcome_activation_body');
            }
            if (! Schema::hasColumn('platform_whatsapp_settings', 'signup_welcome_banner_url')) {
                $table->string('signup_welcome_banner_url', 2048)->nullable()->after('signup_welcome_banner_path');
            }
            if (! Schema::hasColumn('platform_whatsapp_settings', 'signup_welcome_banner_mime')) {
                $table->string('signup_welcome_banner_mime', 64)->nullable()->after('signup_welcome_banner_url');
            }
            if (! Schema::hasColumn('platform_whatsapp_settings', 'signup_welcome_banner_data')) {
                $table->mediumText('signup_welcome_banner_data')->nullable()->after('signup_welcome_banner_mime');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_whatsapp_settings', function (Blueprint $table) {
            foreach ([
                'signup_welcome_banner_data',
                'signup_welcome_banner_mime',
                'signup_welcome_banner_url',
                'signup_welcome_banner_path',
                'signup_welcome_activation_body',
                'signup_welcome_trial_body',
                'signup_welcome_enabled',
            ] as $column) {
                if (Schema::hasColumn('platform_whatsapp_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
