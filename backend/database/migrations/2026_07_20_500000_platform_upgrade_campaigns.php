<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'activated_at')) {
                $table->timestamp('activated_at')->nullable()->after('status');
            }
        });

        Schema::create('platform_upgrade_campaign_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedTinyInteger('discount_percent')->default(5);
            $table->boolean('channel_email')->default(true);
            $table->boolean('channel_whatsapp')->default(true);
            $table->boolean('channel_in_app')->default(true);
            $table->timestamps();
        });

        Schema::create('platform_upgrade_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('path', 40); // basic_to_pro | pro_to_diamond
            $table->string('step', 20); // day_3 | day_7 | day_21
            $table->string('channel', 20)->default('email'); // email | whatsapp | in_app
            $table->string('subject')->nullable();
            $table->string('headline')->nullable();
            $table->text('body_html')->nullable();
            $table->text('body_text')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('image_path')->nullable();
            $table->json('features')->nullable();
            $table->json('use_cases')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['path', 'step', 'channel'], 'platform_upgrade_templates_unique');
        });

        Schema::create('platform_upgrade_sends', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path', 40);
            $table->string('step', 20);
            $table->string('channel', 20);
            $table->string('status', 20)->default('sent');
            $table->string('recipient')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'path', 'step', 'channel'], 'platform_upgrade_sends_unique');
            $table->index(['step', 'sent_at']);
        });

        Schema::create('platform_upgrade_discount_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 40)->unique();
            $table->string('token_hash', 64)->unique();
            $table->string('path', 40);
            $table->unsignedTinyInteger('percent')->default(5);
            $table->string('status', 20)->default('issued'); // issued | claimed | redeemed | expired
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('tenant_owner_notices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 64);
            $table->string('title');
            $table->text('body');
            $table->string('image_url')->nullable();
            $table->string('href')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_owner_notices');
        Schema::dropIfExists('platform_upgrade_discount_claims');
        Schema::dropIfExists('platform_upgrade_sends');
        Schema::dropIfExists('platform_upgrade_templates');
        Schema::dropIfExists('platform_upgrade_campaign_settings');

        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'activated_at')) {
                $table->dropColumn('activated_at');
            }
        });
    }
};
