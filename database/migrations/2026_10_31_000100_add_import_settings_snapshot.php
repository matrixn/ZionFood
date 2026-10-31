<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_publications', fn (Blueprint $table) => $table->longText('settings_snapshot')->nullable());
    }

    public function down(): void
    {
        Schema::table('import_publications', fn (Blueprint $table) => $table->dropColumn('settings_snapshot'));
    }
};
