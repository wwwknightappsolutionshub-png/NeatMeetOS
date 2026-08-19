<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('money_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('category', 40);
            $table->unsignedInteger('amount_cents');
            $table->date('occurred_on');
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'kind', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('money_entries');
    }
};
