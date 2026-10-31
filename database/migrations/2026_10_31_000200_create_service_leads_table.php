<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_leads', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->char('email_hash', 64)->unique();
            $table->text('name');
            $table->text('email');
            $table->text('phone');
            $table->string('locale', 2);
            $table->string('status', 20)->default('new')->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_leads');
    }
};
