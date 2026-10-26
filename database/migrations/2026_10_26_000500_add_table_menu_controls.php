<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_tables', fn (Blueprint $t) => $t->boolean('ordering_paused')->default(false));
        Schema::table('location_fulfillment_settings', fn (Blueprint $t) => $t->unsignedSmallInteger('dine_in_cutoff_minutes')->default(30));
    }

    public function down(): void
    {
        Schema::table('dining_tables', fn (Blueprint $t) => $t->dropColumn('ordering_paused'));
        Schema::table('location_fulfillment_settings', fn (Blueprint $t) => $t->dropColumn('dine_in_cutoff_minutes'));
    }
};
