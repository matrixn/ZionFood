<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_plans', function (Blueprint $table) {
            $table->string('key', 20)->primary();
            $table->string('name', 80);
            $table->unsignedInteger('price_minor');
            $table->timestamps();
        });
        foreach (['menu' => ['Menu', 4900], 'orders' => ['Orders', 14900], 'business' => ['Business', 24900]] as $key => [$name, $price]) {
            DB::table('marketing_plans')->insert(['key' => $key, 'name' => $name, 'price_minor' => $price, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::create('marketing_pricing_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('weekly_enabled')->default(true);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error', 40)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
        DB::table('marketing_pricing_settings')->insert(['id' => 1, 'version' => 1, 'weekly_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('marketing_exchange_rates', function (Blueprint $table) {
            $table->string('currency', 3)->primary();
            $table->unsignedBigInteger('rate_scaled');
            $table->date('rate_date');
            $table->timestamp('fetched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_exchange_rates');
        Schema::dropIfExists('marketing_pricing_settings');
        Schema::dropIfExists('marketing_plans');
    }
};
