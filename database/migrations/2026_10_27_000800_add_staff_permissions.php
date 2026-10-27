<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_memberships', fn (Blueprint $table) => $table->json('permissions')->nullable());
        Schema::table('staff_invitations', fn (Blueprint $table) => $table->json('permissions')->nullable());
    }

    public function down(): void
    {
        Schema::table('staff_invitations', fn (Blueprint $table) => $table->dropColumn('permissions'));
        Schema::table('organization_memberships', fn (Blueprint $table) => $table->dropColumn('permissions'));
    }
};
