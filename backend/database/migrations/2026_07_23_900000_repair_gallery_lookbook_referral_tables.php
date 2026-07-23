<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production repair: earlier 600000/700000 runs failed (uuid vs bigint user FKs)
 * or tables were dropped while still marked Ran. Recreate missing objects safely.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_referral_settings')) {
            Schema::create('platform_referral_settings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->boolean('enabled')->default(false);
                $table->string('reward_type', 40)->default('account_credit_cents');
                $table->unsignedInteger('reward_amount')->default(5000);
                $table->string('qualification_goal', 60)->default('referred_tenant_activated');
                $table->unsignedInteger('qualification_days')->nullable();
                $table->string('share_headline')->nullable();
                $table->text('share_body')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('platform_referral_invites')) {
            Schema::create('platform_referral_invites', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('referrer_tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('code', 32)->unique();
                $table->string('status', 20)->default('active');
                $table->unsignedInteger('conversions_count')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('platform_referral_conversions')) {
            Schema::create('platform_referral_conversions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('invite_id')->constrained('platform_referral_invites')->cascadeOnDelete();
                $table->foreignUuid('referrer_tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignUuid('referred_tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('qualification_goal', 60);
                $table->string('status', 20)->default('pending');
                $table->unsignedInteger('reward_amount')->nullable();
                $table->string('reward_type', 40)->nullable();
                $table->timestamp('qualified_at')->nullable();
                $table->timestamp('rewarded_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique('referred_tenant_id');
            });
        }

        if (! Schema::hasTable('tenant_owner_push_subscriptions')) {
            Schema::create('tenant_owner_push_subscriptions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('endpoint');
                $table->string('endpoint_hash', 64);
                $table->text('p256dh')->nullable();
                $table->text('auth')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
                $table->unique(['tenant_id', 'endpoint_hash']);
            });
        }

        if (! Schema::hasTable('gallery_works')) {
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
        }

        if (! Schema::hasTable('lookbook_items')) {
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
        }

        if (! Schema::hasTable('client_thread_messages')) {
            Schema::create('client_thread_messages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
                $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
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
        }

        Schema::table('client_visits', function (Blueprint $table) {
            if (! Schema::hasColumn('client_visits', 'next_visit_appointment_id')) {
                $table->foreignUuid('next_visit_appointment_id')
                    ->nullable()
                    ->constrained('appointments')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('client_visits', 'next_visit_prompted_at')) {
                $table->timestamp('next_visit_prompted_at')->nullable();
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'next_visit_reminded_72h_at')) {
                $table->timestamp('next_visit_reminded_72h_at')->nullable();
            }
            if (! Schema::hasColumn('appointments', 'next_visit_reminded_24h_at')) {
                $table->timestamp('next_visit_reminded_24h_at')->nullable();
            }
            if (! Schema::hasColumn('appointments', 'origin_visit_id')) {
                $table->foreignUuid('origin_visit_id')
                    ->nullable()
                    ->constrained('client_visits')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        // Irreversible repair migration — keep tables.
    }
};
