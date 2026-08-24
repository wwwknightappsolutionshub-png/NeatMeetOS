<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'membership_joined_at')) {
                $table->timestamp('membership_joined_at')->nullable()->after('last_visited_at');
            }
            if (! Schema::hasColumn('clients', 'interested_next_visit_date')) {
                $table->date('interested_next_visit_date')->nullable()->after('membership_joined_at');
            }
        });

        Schema::table('client_visits', function (Blueprint $table) {
            if (! Schema::hasColumn('client_visits', 'checked_out_at')) {
                $table->timestamp('checked_out_at')->nullable()->after('checked_in_at');
                $table->index(['tenant_id', 'checked_out_at'], 'client_visits_tenant_open_idx');
            }
        });

        if (! Schema::hasTable('client_portal_otps')) {
            Schema::create('client_portal_otps', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
                $table->string('email');
                $table->string('phone_normalized', 32);
                $table->string('code_hash');
                $table->timestamp('expires_at');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('consumed_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'client_id']);
                $table->index(['tenant_id', 'email', 'phone_normalized'], 'client_portal_otps_lookup_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_otps');

        Schema::table('client_visits', function (Blueprint $table) {
            if (Schema::hasColumn('client_visits', 'checked_out_at')) {
                $table->dropIndex('client_visits_tenant_open_idx');
                $table->dropColumn('checked_out_at');
            }
        });

        Schema::table('clients', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('clients', 'interested_next_visit_date')) {
                $cols[] = 'interested_next_visit_date';
            }
            if (Schema::hasColumn('clients', 'membership_joined_at')) {
                $cols[] = 'membership_joined_at';
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
