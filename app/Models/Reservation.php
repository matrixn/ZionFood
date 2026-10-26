<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $attributes = ['version' => 1, 'status' => 'pending'];

    protected $guarded = ['id'];

    protected $hidden = ['token_hash', 'token_encrypted', 'request_hash', 'idempotency_key'];

    protected $casts = ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'hold_expires_at' => 'immutable_datetime', 'cancellation_deadline' => 'immutable_datetime', 'version' => 'integer'];

    public function location()
    {
        return $this->belongsTo(RestaurantLocation::class, 'location_id');
    }

    public function table()
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    public function history()
    {
        return $this->hasMany(ReservationHistory::class);
    }

    public function scopeBlocking($query)
    {
        return $query->where(fn ($q) => $q->whereIn('status', ['confirmed', 'seated'])->orWhere(fn ($p) => $p->where('status', 'pending')->where('hold_expires_at', '>', now())));
    }
}
