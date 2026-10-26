<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_fulfillment_settings', function (Blueprint $t) {
            $t->boolean('reservations_enabled')->default(false);
            $t->boolean('dine_in_enabled')->default(false);
        });
        Schema::create('dining_tables', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->unsignedBigInteger('organization_id');
            $t->unsignedBigInteger('location_id');
            $t->foreign(['location_id', 'organization_id'])->references(['id', 'organization_id'])->on('restaurant_locations');
            $t->string('code', 30);
            $t->string('area', 80);
            $t->unsignedSmallInteger('seats');
            $t->boolean('is_active')->default(true);
            $t->char('qr_token_hash', 64);
            $t->text('qr_token_encrypted');
            $t->unique(['location_id', 'code']);
            $t->unique(['id', 'location_id', 'organization_id']);
            $t->timestamps();
        });
        Schema::create('dining_sessions', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->unsignedBigInteger('organization_id');
            $t->unsignedBigInteger('location_id');
            $t->unsignedBigInteger('dining_table_id');
            $t->foreign(['dining_table_id', 'location_id', 'organization_id'], 'session_table_tenant_fk')->references(['id', 'location_id', 'organization_id'])->on('dining_tables');
            $t->char('token_hash', 64);
            $t->text('token_encrypted');
            $t->timestamp('expires_at');
            $t->timestamp('closed_at')->nullable();
            $t->foreignId('opened_by')->nullable()->constrained('users');
            $t->unique(['id', 'location_id', 'organization_id']);
            $t->timestamps();
        });
        Schema::create('reservations', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->unsignedBigInteger('organization_id');
            $t->unsignedBigInteger('location_id');
            $t->unsignedBigInteger('dining_table_id')->nullable();
            $t->foreign(['location_id', 'organization_id'])->references(['id', 'organization_id'])->on('restaurant_locations');
            $t->foreign(['dining_table_id', 'location_id', 'organization_id'], 'reservation_table_tenant_fk')->references(['id', 'location_id', 'organization_id'])->on('dining_tables');
            $t->string('guest_name', 150);
            $t->string('guest_phone', 35);
            $t->string('guest_email', 254)->nullable();
            $t->string('locale', 2)->default('ro');
            $t->text('notes')->nullable();
            $t->unsignedSmallInteger('party_size');
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->string('status')->default('pending');
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('hold_expires_at')->nullable();
            $t->timestamp('cancellation_deadline');
            $t->string('timezone');
            $t->string('idempotency_key', 100);
            $t->char('request_hash', 64);
            $t->char('token_hash', 64);
            $t->text('token_encrypted');
            $t->unique(['location_id', 'idempotency_key']);
            $t->index(['location_id', 'starts_at', 'ends_at']);
            $t->timestamps();
        });
        Schema::create('reservation_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reservation_id')->constrained();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->timestamps();
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->unsignedBigInteger('dining_session_id')->nullable();
            $t->foreign(['dining_session_id', 'location_id', 'organization_id'], 'order_session_tenant_fk')->references(['id', 'location_id', 'organization_id'])->on('dining_sessions');
            $t->string('table_code', 30)->nullable();
            $t->string('table_area', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropForeign('order_session_tenant_fk');
            $t->dropColumn(['dining_session_id', 'table_code', 'table_area']);
        });
        Schema::dropIfExists('reservation_history');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('dining_sessions');
        Schema::dropIfExists('dining_tables');
        Schema::table('location_fulfillment_settings', fn (Blueprint $t) => $t->dropColumn(['reservations_enabled', 'dine_in_enabled']));
    }
};
