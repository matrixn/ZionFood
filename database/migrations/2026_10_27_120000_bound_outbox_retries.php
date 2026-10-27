<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->unsignedTinyInteger('delivery_attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->index(['delivered_at', 'failed_at', 'next_attempt_at'], 'outbox_due_index');
        });
        $this->backfill();
    }

    public function backfill(): void
    {
        // Preserve lifetime attempts and event data; quarantine already exhausted legacy rows.
        DB::table('outbox_messages')->whereNull('delivered_at')->update([
            'delivery_attempts' => DB::raw('CASE WHEN attempts > 5 THEN 5 ELSE attempts END'),
        ]);
        DB::table('outbox_messages')->whereNull('delivered_at')->where('attempts', '>=', 5)->update(['failed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->dropIndex('outbox_due_index');
            $table->dropColumn(['delivery_attempts', 'next_attempt_at', 'failed_at']);
        });
    }
};
