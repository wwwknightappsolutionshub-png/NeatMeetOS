<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salon_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('author_name', 120);
            $table->unsignedTinyInteger('rating')->default(5);
            $table->text('body');
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_published', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salon_reviews');
    }
};
