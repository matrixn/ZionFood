<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_alert_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('enabled')->default(true);
            $table->timestamp('suspended_at')->nullable();
            $table->string('reason')->nullable();
            $table->unsignedInteger('generation')->default(1);
            $table->unsignedInteger('version')->default(1);
            $table->json('admissions');
            $table->json('deliveries');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_alert_settings');
    }
};
