<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_contact_suppressions', function (Blueprint $table) {
            if (! Schema::hasColumn('marketing_contact_suppressions', 'lifted_at')) {
                $table->timestamp('lifted_at')->nullable()->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketing_contact_suppressions', function (Blueprint $table) {
            if (Schema::hasColumn('marketing_contact_suppressions', 'lifted_at')) {
                $table->dropColumn('lifted_at');
            }
        });
    }
};
