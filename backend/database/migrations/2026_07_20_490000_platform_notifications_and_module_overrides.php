<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 64);
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['type']);
        });

        Schema::create('platform_notification_reads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('platform_notification_id')
                ->constrained('platform_notifications')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['platform_notification_id', 'user_id'], 'platform_notif_reads_unique');
        });

        Schema::create('tenant_module_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('module_key', 64);
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(['tenant_id', 'module_key']);
            $table->index(['module_key']);
        });

        // Expand plan feature catalogues for payments + notifications (additive).
        foreach (['basic', 'pro', 'diamond'] as $slug) {
            $row = DB::table('subscription_plans')->where('slug', $slug)->first();
            if ($row === null) {
                continue;
            }
            $features = json_decode((string) $row->features, true);
            if (! is_array($features)) {
                $features = [];
            }
            $features['payments'] = $features['payments'] ?? true;
            $features['notifications'] = $features['notifications'] ?? ($slug !== 'basic');
            if ($slug === 'basic') {
                $features['notifications'] = $features['notifications'] ?? false;
            }
            DB::table('subscription_plans')->where('id', $row->id)->update([
                'features' => json_encode($features),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_module_overrides');
        Schema::dropIfExists('platform_notification_reads');
        Schema::dropIfExists('platform_notifications');
    }
};
