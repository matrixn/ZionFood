<?php

namespace App\Models;

use App\Jobs\DeliverWebhook;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WebhookDelivery extends Model
{
    public function endpoint()
    {
        return $this->belongsTo(WebhookEndpoint::class, 'endpoint_id')->withTrashed();
    }

    protected $hidden = ['claim_token', 'payload'];

    protected $casts = ['payload' => 'array', 'next_attempt_at' => 'datetime', 'locked_until' => 'datetime', 'delivered_at' => 'datetime'];

    protected static function booted(): void
    {
        static::created(fn ($delivery) => DB::afterCommit(fn () => DeliverWebhook::dispatch($delivery->id)));
    }

    use HasFactory;

    protected $table = 'webhook_deliveries';

    protected $guarded = ['id'];
}
