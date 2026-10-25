<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->string('locale', 2)->default('ro'));
        Schema::table('restaurants', function (Blueprint $t) {
            $t->string('tagline', 180)->nullable();
            $t->string('primary_color', 7)->default('#635BFF');
            $t->string('logo_path')->nullable();
            $t->string('cover_path')->nullable();
            $t->string('contact_email')->nullable();
            $t->string('contact_phone', 30)->nullable();
            $t->string('website')->nullable();
        });
        Schema::create('staff_invitations', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('invited_by')->constrained('users');
            $t->foreignId('accepted_by')->nullable()->constrained('users');
            $t->string('email');
            $t->string('role');
            $t->json('location_ids');
            $t->string('token_hash', 64);
            $t->timestamp('expires_at');
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->unique(['organization_id', 'email']);
            $t->timestamps();
        });
        Schema::table('webhook_endpoints', function (Blueprint $t) {
            $t->foreignId('location_id')->nullable()->constrained('restaurant_locations');
            $t->string('name')->default('Webhook');
            $t->json('events')->nullable();
            $t->softDeletes();
        });
        Schema::table('webhook_deliveries', function (Blueprint $t) {
            $t->foreignId('outbox_message_id')->nullable()->constrained('outbox_messages');
            $t->json('payload')->nullable();
            $t->timestamp('next_attempt_at')->nullable();
            $t->timestamp('locked_until')->nullable();
            $t->uuid('claim_token')->nullable();
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->string('failure_code')->nullable();
            $t->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $t) {
            $t->dropIndex(['status', 'next_attempt_at']);
            $t->dropConstrainedForeignId('outbox_message_id');
            $t->dropColumn(['payload', 'next_attempt_at', 'locked_until', 'claim_token', 'http_status', 'failure_code']);
        });
        Schema::table('webhook_endpoints', function (Blueprint $t) {
            $t->dropConstrainedForeignId('location_id');
            $t->dropColumn(['name', 'events', 'deleted_at']);
        });
        Schema::dropIfExists('staff_invitations');
        Schema::table('restaurants', fn (Blueprint $t) => $t->dropColumn(['tagline', 'primary_color', 'logo_path', 'cover_path', 'contact_email', 'contact_phone', 'website']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('locale'));
    }
};
