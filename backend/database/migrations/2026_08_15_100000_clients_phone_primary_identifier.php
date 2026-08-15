<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('first_name')->nullable()->change();
            $table->string('phone_normalized', 40)->nullable()->after('phone');
        });

        $clients = DB::table('clients')->select('id', 'tenant_id', 'phone', 'created_at')->orderBy('created_at')->get();
        $seen = [];

        foreach ($clients as $client) {
            $raw = trim((string) ($client->phone ?? ''));
            if ($raw === '') {
                continue;
            }

            $normalized = preg_replace('/[^\d+]/', '', $raw) ?? '';
            if (str_starts_with($normalized, '00')) {
                $normalized = '+'.substr($normalized, 2);
            }
            if ($normalized === '') {
                continue;
            }

            $key = $client->tenant_id.'|'.$normalized;
            if (isset($seen[$key])) {
                // Keep the earliest client as the unique owner of this phone.
                continue;
            }

            $seen[$key] = true;
            DB::table('clients')->where('id', $client->id)->update([
                'phone_normalized' => $normalized,
            ]);
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->unique(['tenant_id', 'phone_normalized'], 'clients_tenant_phone_normalized_unique');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique('clients_tenant_phone_normalized_unique');
            $table->dropColumn('phone_normalized');
        });

        // first_name may contain nulls; coerce before restoring NOT NULL.
        DB::table('clients')->whereNull('first_name')->update(['first_name' => '']);

        Schema::table('clients', function (Blueprint $table) {
            $table->string('first_name')->nullable(false)->change();
        });
    }
};
