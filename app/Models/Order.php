<?php

namespace App\Models;

use App\Domain\Orders\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $attributes = ['version' => 1, 'status' => 'pending'];

    use HasFactory;

    protected $table = 'orders';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->public_id ??= (string) Str::ulid();
        });
    }

    protected $hidden = ['tracking_token_hash', 'tracking_token_encrypted'];

    protected $casts = ['status' => OrderStatus::class, 'delivery_latitude' => 'float', 'delivery_longitude' => 'float', 'scheduled_for' => 'immutable_datetime', 'preparation_at' => 'immutable_datetime', 'schedule_released_at' => 'immutable_datetime', 'promotion_snapshot' => 'array'];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function history()
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    public function location()
    {
        return $this->belongsTo(RestaurantLocation::class, 'location_id');
    }
}
