<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One assessment per email and per normalised phone (platform lead integrity).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salon_growth_assessments', function (Blueprint $table) {
            $table->unique('email', 'salon_growth_assessments_email_unique');
            $table->unique('phone_normalized', 'salon_growth_assessments_phone_normalized_unique');
        });
    }

    public function down(): void
    {
        Schema::table('salon_growth_assessments', function (Blueprint $table) {
            $table->dropUnique('salon_growth_assessments_email_unique');
            $table->dropUnique('salon_growth_assessments_phone_normalized_unique');
        });
    }
};
