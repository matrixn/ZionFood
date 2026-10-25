<?php

namespace App\Models;

use App\Jobs\DeliverOutbox;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OutboxMessage extends Model
{
    protected static function booted(): void
    {
        static::created(function ($message) {
            DB::afterCommit(fn () => DeliverOutbox::dispatch($message->id));
        });
    }

    use HasFactory;

    protected $table = 'outbox_messages';

    protected $guarded = ['id'];

    protected $casts = ['payload' => 'array', 'delivered_at' => 'datetime'];
}
