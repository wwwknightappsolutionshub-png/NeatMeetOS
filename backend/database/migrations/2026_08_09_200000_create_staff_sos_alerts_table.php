<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_sos_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('appointment_id')->nullable()->index();
            $table->string('kind', 40); // new_booking | approaching
            $table->string('status', 20)->default('active'); // active | acknowledged | resolved
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->uuid('acknowledged_by_team_member_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_sos_alerts');
    }
};
