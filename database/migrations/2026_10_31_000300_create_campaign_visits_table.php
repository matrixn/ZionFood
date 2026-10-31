<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_visits', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->char('campaign_key', 64)->index();
            foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_id'] as $field) {
                $table->string($field, 200)->nullable();
            }
            $table->string('landing_path', 10);
            $table->string('locale', 2);
            $table->timestamps();
            $table->index(['created_at', 'id']);
            $table->index(['utm_source', 'utm_campaign']);
        });
        Schema::table('service_leads', fn (Blueprint $table) => $table->foreignId('campaign_visit_id')->nullable()->constrained('campaign_visits')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('service_leads', fn (Blueprint $table) => $table->dropConstrainedForeignId('campaign_visit_id'));
        Schema::dropIfExists('campaign_visits');
    }
};
