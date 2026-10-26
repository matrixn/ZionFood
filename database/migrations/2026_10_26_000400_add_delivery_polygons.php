<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_zones', function (Blueprint $t) {
            $t->json('geometry')->nullable();
            $t->unsignedSmallInteger('priority')->default(0);
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->decimal('delivery_latitude', 10, 7)->nullable();
            $t->decimal('delivery_longitude', 11, 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['delivery_latitude', 'delivery_longitude']));
        Schema::table('delivery_zones', fn (Blueprint $t) => $t->dropColumn(['geometry', 'priority']));
    }
};
