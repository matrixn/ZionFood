<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_deliveries', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->string('notification_id', 64)->nullable()->unique();
            $t->string('source', 40)->index();
            $t->string('state', 30)->index();
            $t->string('mailer', 60)->nullable()->index();
            $t->string('initiator_kind', 20)->default('system')->index();
            $t->foreignId('initiator_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('recipients');
            $t->json('recipient_hashes');
            $t->text('subject')->nullable();
            $t->text('context')->nullable();
            $t->unsignedInteger('attempts')->default(0);
            $t->string('reason', 60)->nullable();
            $t->string('provider_email_id', 255)->nullable();
            $t->timestamp('available_at')->nullable()->index();
            $t->timestamp('lease_until')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamps();
        });
        Schema::create('mail_provider_limits', function (Blueprint $t) {
            $t->string('provider', 30)->primary();
            $t->date('utc_day');
            $t->unsignedInteger('daily_limit')->default(100);
            $t->unsignedInteger('reserved')->default(0);
            $t->string('pause_reason', 40)->nullable();
            $t->timestamp('paused_until')->nullable();
            $t->unsignedInteger('revision')->default(1);
            $t->json('usage')->nullable();
            $t->timestamp('usage_checked_at')->nullable();
            $t->string('usage_error', 40)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_deliveries');
        Schema::dropIfExists('mail_provider_limits');
    }
};
