<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Align public plan display prices with Landing Page V2 positioning.
 * Slugs remain basic / pro / diamond (pro marketed as Advanced).
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            'basic' => ['name' => 'Basic', 'display_price_cents' => 5900],
            'pro' => ['name' => 'Advanced', 'display_price_cents' => 9900],
            'diamond' => ['name' => 'Diamond', 'display_price_cents' => 17900],
        ];

        foreach ($rows as $slug => $data) {
            DB::table('subscription_plans')
                ->where('slug', $slug)
                ->update([
                    'name' => $data['name'],
                    'display_price_cents' => $data['display_price_cents'],
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        $rows = [
            'basic' => ['name' => 'Basic', 'display_price_cents' => 4900],
            'pro' => ['name' => 'Pro', 'display_price_cents' => 12900],
            'diamond' => ['name' => 'Diamond', 'display_price_cents' => 29900],
        ];

        foreach ($rows as $slug => $data) {
            DB::table('subscription_plans')
                ->where('slug', $slug)
                ->update([
                    'name' => $data['name'],
                    'display_price_cents' => $data['display_price_cents'],
                    'updated_at' => now(),
                ]);
        }
    }
};
