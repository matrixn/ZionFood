<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_resets', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('location_id')->constrained('restaurant_locations');
            $table->foreignId('user_id')->constrained();
            $table->json('summary');
            $table->timestamps();
        });
        Schema::table('import_batches', fn (Blueprint $table) => $table->foreignId('reset_id')->nullable()->constrained('import_resets'));
    }

    public function down(): void
    {
        Schema::table('import_batches', fn (Blueprint $table) => $table->dropConstrainedForeignId('reset_id'));
        Schema::dropIfExists('import_resets');
    }
};
