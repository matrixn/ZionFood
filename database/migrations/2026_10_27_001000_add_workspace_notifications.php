<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_notifications', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('draft_id')->constrained('menu_content_drafts')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'draft_id']);
            $table->index(['user_id', 'created_at']);
        });
        // Retire only the removed manual-import drafts; published menu data is preserved.
        DB::table('menu_content_drafts')->where('kind', 'gloriafood')->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_notifications');
    }
};
