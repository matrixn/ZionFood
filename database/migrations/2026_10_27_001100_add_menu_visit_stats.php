<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_visit_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->foreign(['location_id', 'organization_id'])->references(['id', 'organization_id'])->on('restaurant_locations')->cascadeOnDelete();
            $table->date('day');
            $table->unsignedBigInteger('visits')->default(0);
            $table->timestamps();
            $table->unique(['location_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_visit_stats');
    }
};
