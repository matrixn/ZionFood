<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_campaigns', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('restaurant_locations')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('mode', 16);
            $table->boolean('active');
            $table->timestamp('active_since')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->json('buy_products');
            $table->unsignedSmallInteger('buy_quantity');
            $table->ulid('reward_product');
            $table->string('reward_name');
            $table->unsignedSmallInteger('reward_quantity');
            $table->timestamps();
            $table->index(['location_id', 'active', 'mode']);
        });
        Schema::create('reward_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('restaurant_locations')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('created_at');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('reward_card_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::create('reward_balances', function (Blueprint $table) {
            $table->foreignId('campaign_id')->constrained('reward_campaigns')->cascadeOnDelete();
            $table->foreignId('card_id')->constrained('reward_cards')->cascadeOnDelete();
            $table->unsignedBigInteger('earned')->default(0);
            $table->unsignedBigInteger('redeemed')->default(0);
            $table->primary(['campaign_id', 'card_id']);
        });
        Schema::create('reward_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('restaurant_locations')->cascadeOnDelete();
            $table->foreignId('card_id')->nullable()->constrained('reward_cards')->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('reward_campaigns')->cascadeOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->index(['location_id', 'expires_at']);
        });
        Schema::create('reward_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('reward_campaigns')->cascadeOnDelete();
            $table->foreignId('card_id')->constrained('reward_cards')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference', 72);
            $table->string('kind', 12);
            $table->unsignedSmallInteger('quantity');
            $table->timestamp('created_at');
            $table->unique(['campaign_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_events');
        Schema::dropIfExists('reward_codes');
        Schema::dropIfExists('reward_balances');
        Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('reward_card_id'));
        Schema::dropIfExists('reward_cards');
        Schema::dropIfExists('reward_campaigns');
    }
};
