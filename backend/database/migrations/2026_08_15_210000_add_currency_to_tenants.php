<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('currency', 3)->default('GBP')->after('timezone');
        });

        // Best-effort backfill from a location address country when present.
        $countryCurrency = [
            'GB' => 'GBP',
            'UK' => 'GBP',
            'NG' => 'NGN',
            'US' => 'USD',
            'IE' => 'EUR',
            'DE' => 'EUR',
            'FR' => 'EUR',
            'CA' => 'CAD',
            'AU' => 'AUD',
            'ZA' => 'ZAR',
            'KE' => 'KES',
            'GH' => 'GHS',
        ];

        $locations = DB::table('locations')
            ->select('tenant_id', 'address')
            ->whereNotNull('address')
            ->orderBy('created_at')
            ->get();

        $seen = [];
        foreach ($locations as $location) {
            if (isset($seen[$location->tenant_id])) {
                continue;
            }
            $address = is_string($location->address)
                ? json_decode($location->address, true)
                : null;
            if (! is_array($address)) {
                continue;
            }
            $country = strtoupper(trim((string) ($address['country'] ?? '')));
            if ($country === '' || ! isset($countryCurrency[$country])) {
                continue;
            }
            $seen[$location->tenant_id] = true;
            DB::table('tenants')->where('id', $location->tenant_id)->update([
                'currency' => $countryCurrency[$country],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
