<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', fn (Blueprint $t) => $t->boolean('communication_logs_enabled')->default(false));
        Schema::create('wordpress_connections', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('organization_id')->constrained();
            $t->unsignedBigInteger('restaurant_id');
            $t->foreign(['restaurant_id', 'organization_id'])->references(['id', 'organization_id'])->on('restaurants');
            $t->foreignId('user_id')->constrained();
            $t->string('name', 100);
            $t->string('site_url', 255);
            $t->json('location_ids');
            $t->json('abilities');
            $t->text('webhook_secret');
            $t->boolean('active')->default(true);
            $t->unsignedSmallInteger('expires_in_days')->default(90);
            $t->timestamps();
            $t->unique(['id', 'restaurant_id', 'organization_id'], 'wp_connection_tenant');
        });
        Schema::table('personal_access_tokens', fn (Blueprint $t) => $t->foreignId('wordpress_connection_id')->nullable()->constrained());
        Schema::table('webhook_endpoints', function (Blueprint $t) {
            $t->foreignId('wordpress_connection_id')->nullable()->constrained();
            $t->unique(['wordpress_connection_id', 'location_id'], 'wp_callback_location');
        });
        Schema::create('communication_logs', function (Blueprint $t) {
            $t->id();
            $t->uuid('request_id')->index();
            $t->unsignedBigInteger('organization_id')->nullable();
            $t->unsignedBigInteger('restaurant_id')->nullable();
            $t->foreign(['restaurant_id', 'organization_id'])->references(['id', 'organization_id'])->on('restaurants');
            $t->foreignId('location_id')->nullable()->constrained('restaurant_locations');
            $t->foreignId('wordpress_connection_id')->nullable()->constrained();
            $t->string('direction', 8);
            $t->string('operation', 150);
            $t->string('method', 8);
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->string('outcome', 32);
            $t->unsignedInteger('duration_ms')->default(0);
            $t->string('destination_host', 253)->nullable();
            $t->string('event_id', 26)->nullable()->index();
            $t->unsignedTinyInteger('attempt')->default(1);
            $t->string('error_code', 64)->nullable();
            $t->boolean('replayed')->default(false);
            $t->timestamp('created_at')->useCurrent();
            $t->index(['restaurant_id', 'created_at']);
            $t->index(['direction', 'http_status', 'created_at'], 'communication_filter');
        });
        Schema::create('wordpress_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wordpress_connection_id')->constrained();
            $t->char('key_hash', 64);
            $t->char('request_hash', 64);
            $t->unsignedSmallInteger('http_status');
            $t->longText('response')->nullable();
            $t->timestamp('expires_at');
            $t->timestamps();
            $t->unique(['wordpress_connection_id', 'key_hash'], 'wp_idempotency');
        });
        Schema::create('promotions', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('organization_id')->constrained();
            $t->unsignedBigInteger('restaurant_id');
            $t->foreign(['restaurant_id', 'organization_id'])->references(['id', 'organization_id'])->on('restaurants');
            $t->foreignId('location_id')->nullable()->constrained('restaurant_locations');
            $t->string('name', 100);
            $t->string('code', 40)->nullable();
            $t->string('kind', 20);
            $t->unsignedInteger('value')->default(0);
            $t->unsignedInteger('minimum_order')->default(0);
            $t->unsignedInteger('max_discount')->nullable();
            $t->json('product_ids')->nullable();
            $t->json('fulfillments');
            $t->unsignedTinyInteger('buy_quantity')->default(1);
            $t->unsignedTinyInteger('reward_quantity')->default(1);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('usage_limit')->nullable();
            $t->unsignedInteger('used_count')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['restaurant_id', 'code']);
            $t->index(['restaurant_id', 'active']);
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->timestamp('scheduled_for')->nullable()->index();
            $t->timestamp('preparation_at')->nullable()->index();
            $t->timestamp('schedule_released_at')->nullable();
            $t->foreignId('reservation_id')->nullable()->constrained();
            $t->foreignId('wordpress_connection_id')->nullable()->constrained();
            $t->unsignedInteger('discount')->default(0);
            $t->json('promotion_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('reservation_id');
            $t->dropConstrainedForeignId('wordpress_connection_id');
            $t->dropColumn(['scheduled_for', 'preparation_at', 'schedule_released_at', 'discount', 'promotion_snapshot']);
        });
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('wordpress_requests');
        Schema::dropIfExists('communication_logs');
        Schema::table('webhook_endpoints', function (Blueprint $t) {
            $t->dropUnique('wp_callback_location');
            $t->dropConstrainedForeignId('wordpress_connection_id');
        });
        Schema::table('personal_access_tokens', fn (Blueprint $t) => $t->dropConstrainedForeignId('wordpress_connection_id'));
        Schema::dropIfExists('wordpress_connections');
        Schema::table('restaurants', fn (Blueprint $t) => $t->dropColumn('communication_logs_enabled'));
    }
};
