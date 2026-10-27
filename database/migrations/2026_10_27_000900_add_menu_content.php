<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->json('content')->nullable();
            $table->unsignedInteger('content_revision')->default(0);
        });
        Schema::create('menu_content_drafts', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('location_id')->constrained('restaurant_locations');
            $table->foreignId('item_id')->constrained('menu_items');
            $table->foreignId('requested_by')->constrained('users');
            $table->string('kind', 30);
            $table->string('status', 30)->default('queued');
            $table->char('source_hash', 64);
            $table->longText('input');
            $table->longText('proposal')->nullable();
            $table->longText('report')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'location_id', 'status']);
            $table->foreign(['location_id', 'organization_id'])->references(['id', 'organization_id'])->on('restaurant_locations');
            $table->foreign(['item_id', 'organization_id'])->references(['id', 'organization_id'])->on('menu_items');
        });
        Schema::create('menu_ai_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('provider')->default('groq');
            $table->string('model')->default('openai/gpt-oss-20b');
            $table->text('api_key')->nullable();
            $table->unsignedInteger('daily_limit')->default(100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_ai_settings');
        Schema::dropIfExists('menu_content_drafts');
        Schema::table('menu_items', fn (Blueprint $table) => $table->dropColumn(['content', 'content_revision']));
    }
};
