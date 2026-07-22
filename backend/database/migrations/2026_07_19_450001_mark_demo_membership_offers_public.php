<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('membership_plans')
            ->whereIn('name', ['Blow Dry Club', 'Colour Care Membership'])
            ->update(['is_public' => true]);

        DB::table('package_products')
            ->whereIn('name', ['6 Blow Dries Pack', 'Colour Refresh Bundle'])
            ->update(['is_public' => true]);
    }

    public function down(): void
    {
        DB::table('membership_plans')
            ->whereIn('name', ['Blow Dry Club', 'Colour Care Membership'])
            ->update(['is_public' => false]);

        DB::table('package_products')
            ->whereIn('name', ['6 Blow Dries Pack', 'Colour Refresh Bundle'])
            ->update(['is_public' => false]);
    }
};
