<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $t) {
            $t->foreignId('organization_id')->nullable()->constrained();
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $t) {
            $t->dropConstrainedForeignId('organization_id');
        });
    }
};
