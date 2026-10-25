<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_platform_admin')->default(false);
        });
        Schema::create('organizations', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('organization_memberships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('role');
            $t->unique(['organization_id', 'user_id']);
            $t->timestamps();
        });
        Schema::create('restaurants', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('organization_id')->constrained();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('description', 1000)->nullable();
            $t->unique(['id', 'organization_id']);
            $t->timestamps();
        });
        Schema::create('restaurant_locations', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('organization_id')->constrained();
            $t->unsignedBigInteger('restaurant_id');
            $t->foreign(['restaurant_id', 'organization_id'])->references(['id', 'organization_id'])->on('restaurants');
            $t->string('name');
            $t->string('address');
            $t->string('timezone')->default('Europe/Bucharest');
            $t->char('currency', 3)->default('RON');
            $t->boolean('accepting_orders')->default(false);
            $t->unique(['id', 'organization_id']);
            $t->timestamps();
        });
        Schema::create('location_staff', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->unsignedBigInteger('location_id');
            $t->foreignId('user_id')->constrained();
            $t->foreign(['location_id', 'organization_id'])->references(['id', 'organization_id'])->on('restaurant_locations');
            $t->foreign(['organization_id', 'user_id'])->references(['organization_id', 'user_id'])->on('organization_memberships');
            $t->unique(['location_id', 'user_id']);
            $t->timestamps();
        });
        Schema::create('location_business_hours', function (Blueprint $t) {
            $t->id();
            $t->foreignId('location_id')->constrained('restaurant_locations');
            $t->unsignedTinyInteger('weekday');
            $t->time('opens_at');
            $t->time('closes_at');
            $t->unique(['location_id', 'weekday']);
            $t->timestamps();
        });
        Schema::create('storefront_domains', function (Blueprint $t) {
            $t->id();
            $t->foreignId('location_id')->constrained('restaurant_locations');
            $t->string('hostname')->unique();
            $t->string('type')->default('subdomain');
            $t->string('verification_status')->default('pending');
            $t->string('ssl_status')->default('pending');
            $t->boolean('is_primary')->default(true);
            $t->timestamps();
        });
        Schema::create('menus', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('organization_id')->constrained();
            $t->unsignedBigInteger('restaurant_id');
            $t->foreign(['restaurant_id', 'organization_id'])->references(['id', 'organization_id'])->on('restaurants');
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->unique(['id', 'organization_id']);
            $t->timestamps();
        });
        Schema::create('location_menus', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->unsignedBigInteger('location_id');
            $t->unsignedBigInteger('menu_id');
            $t->foreign(['location_id', 'organization_id'])->references(['id', 'organization_id'])->on('restaurant_locations');
            $t->foreign(['menu_id', 'organization_id'])->references(['id', 'organization_id'])->on('menus');
            $t->unique(['location_id', 'menu_id']);
        });
        Schema::create('menu_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->unsignedBigInteger('menu_id');
            $t->foreign(['menu_id', 'organization_id'])->references(['id', 'organization_id'])->on('menus');
            $t->string('name');
            $t->unsignedInteger('sort_order')->default(0);
            $t->unique(['id', 'organization_id']);
            $t->timestamps();
        });
        Schema::create('menu_items', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('organization_id')->constrained();
            $t->unsignedBigInteger('category_id');
            $t->foreign(['category_id', 'organization_id'])->references(['id', 'organization_id'])->on('menu_categories');
            $t->string('name');
            $t->text('description')->nullable();
            $t->unsignedInteger('price');
            $t->unsignedInteger('tax_basis_points')->default(0);
            $t->boolean('is_available')->default(true);
            $t->string('image_path')->nullable();
            $t->softDeletes();
            $t->unique(['id', 'organization_id']);
            $t->timestamps();
        });
        Schema::create('menu_item_variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('item_id')->constrained('menu_items');
            $t->string('name');
            $t->unsignedInteger('price');
            $t->boolean('is_available')->default(true);
            $t->timestamps();
        });
        Schema::create('modifier_groups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->string('name');
            $t->unsignedTinyInteger('min_choices')->default(0);
            $t->unsignedTinyInteger('max_choices')->default(1);
            $t->unique(['id', 'organization_id']);
            $t->timestamps();
        });
        Schema::create('modifier_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('group_id')->constrained('modifier_groups');
            $t->string('name');
            $t->unsignedInteger('price')->default(0);
            $t->boolean('is_available')->default(true);
            $t->timestamps();
        });
        Schema::create('menu_item_modifier_groups', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('organization_id');
            $t->unsignedBigInteger('item_id');
            $t->unsignedBigInteger('group_id');
            $t->foreign(['item_id', 'organization_id'])->references(['id', 'organization_id'])->on('menu_items');
            $t->foreign(['group_id', 'organization_id'])->references(['id', 'organization_id'])->on('modifier_groups');
            $t->unique(['item_id', 'group_id']);
        });
        Schema::create('location_fulfillment_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('location_id')->unique()->constrained('restaurant_locations');
            $t->boolean('pickup_enabled')->default(true);
            $t->boolean('delivery_enabled')->default(false);
            $t->unsignedInteger('preparation_minutes')->default(30);
            $t->timestamps();
        });
        Schema::create('delivery_zones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('location_id')->constrained('restaurant_locations');
            $t->string('name');
            $t->string('postal_prefix');
            $t->unsignedInteger('fee')->default(0);
            $t->unsignedInteger('minimum_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->unsignedBigInteger('organization_id');
            $t->unsignedBigInteger('location_id');
            $t->foreign(['location_id', 'organization_id'])->references(['id', 'organization_id'])->on('restaurant_locations');
            $t->string('tracking_token_hash', 64)->unique();
            $t->text('tracking_token_encrypted');
            $t->string('status')->default('pending');
            $t->unsignedInteger('version')->default(1);
            $t->string('fulfillment');
            $t->string('customer_name');
            $t->string('customer_phone');
            $t->text('delivery_address')->nullable();
            $t->text('notes')->nullable();
            $t->char('currency', 3)->default('RON');
            $t->unsignedInteger('subtotal');
            $t->unsignedInteger('delivery_fee')->default(0);
            $t->unsignedInteger('total');
            $t->index(['organization_id', 'location_id', 'status', 'created_at']);
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained();
            $t->string('product_public_id');
            $t->string('name');
            $t->string('variant_name')->nullable();
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('unit_price');
            $t->unsignedInteger('tax_basis_points');
            $t->unsignedInteger('total');
            $t->timestamps();
        });
        Schema::create('order_item_modifiers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_item_id')->constrained();
            $t->string('group_name');
            $t->string('name');
            $t->unsignedInteger('price');
            $t->timestamps();
        });
        Schema::create('order_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->string('reason', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('checkout_idempotency_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('location_id')->constrained('restaurant_locations');
            $t->string('key', 100);
            $t->string('request_hash', 64);
            $t->foreignId('order_id')->nullable()->constrained();
            $t->unique(['location_id', 'key']);
            $t->timestamps();
        });
        Schema::create('outbox_messages', function (Blueprint $t) {
            $t->id();
            $t->ulid('event_id')->unique();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('location_id')->constrained('restaurant_locations');
            $t->string('type');
            $t->json('payload');
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('delivered_at')->nullable();
            $t->text('last_error')->nullable();
            $t->index(['delivered_at', 'id']);
            $t->timestamps();
        });
        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->unsignedInteger('monthly_price')->default(0);
            $t->char('currency', 3)->default('RON');
            $t->json('features');
            $t->timestamps();
        });
        Schema::create('organization_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->unique()->constrained();
            $t->foreignId('plan_id')->constrained();
            $t->string('status')->default('trial');
            $t->timestamp('trial_ends_at');
            $t->timestamps();
        });
        Schema::create('api_integrations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->string('name');
            $t->string('type')->default('wordpress');
            $t->timestamps();
        });
        Schema::create('webhook_endpoints', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->string('url');
            $t->text('secret_encrypted');
            $t->boolean('enabled')->default(false);
            $t->timestamps();
        });
        Schema::create('webhook_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('endpoint_id')->constrained('webhook_endpoints');
            $t->ulid('event_id');
            $t->string('status');
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
            $t->unique(['endpoint_id', 'event_id']);
        });
    }

    public function down(): void
    {
        foreach (['webhook_deliveries', 'webhook_endpoints', 'api_integrations', 'organization_subscriptions', 'plans', 'outbox_messages', 'checkout_idempotency_keys', 'order_status_history', 'order_item_modifiers', 'order_items', 'orders', 'delivery_zones', 'location_fulfillment_settings', 'menu_item_modifier_groups', 'modifier_options', 'modifier_groups', 'menu_item_variants', 'menu_items', 'menu_categories', 'location_menus', 'menus', 'storefront_domains', 'location_business_hours', 'location_staff', 'restaurant_locations', 'restaurants', 'organization_memberships', 'organizations'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_platform_admin'));
    }
};
