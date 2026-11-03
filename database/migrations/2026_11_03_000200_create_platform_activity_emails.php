<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_activity_emails', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->char('event_key', 64);
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 60);
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('subject_id');
            $table->text('details')->nullable();
            $table->timestamp('queued_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['event_key', 'recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_activity_emails');
    }
};
