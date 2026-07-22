<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->unsignedInteger('membership_price_cents')->nullable()->after('base_price_cents');
            $table->unsignedInteger('loyalty_price_cents')->nullable()->after('membership_price_cents');
        });

        // Backfill display tiers from regular price so existing catalogues show options immediately.
        // Tenants can clear a tier in admin to hide it.
        $rows = DB::table('booking_services')
            ->whereNotNull('base_price_cents')
            ->whereNull('membership_price_cents')
            ->get(['id', 'base_price_cents']);

        foreach ($rows as $row) {
            $base = (int) $row->base_price_cents;
            DB::table('booking_services')->where('id', $row->id)->update([
                'membership_price_cents' => (int) round($base * 0.85),
                'loyalty_price_cents' => (int) round($base * 0.9),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->dropColumn(['membership_price_cents', 'loyalty_price_cents']);
        });
    }
};
