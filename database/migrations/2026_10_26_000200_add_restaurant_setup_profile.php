<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_locations', function (Blueprint $table) {
            $table->json('setup_profile')->nullable();
            $table->unsignedInteger('setup_revision')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_locations', fn (Blueprint $table) => $table->dropColumn(['setup_profile', 'setup_revision']));
    }
};
