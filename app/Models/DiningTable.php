<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiningTable extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['qr_token_hash', 'qr_token_encrypted'];

    protected $casts = ['is_active' => 'boolean', 'ordering_paused' => 'boolean'];

    public function location()
    {
        return $this->belongsTo(RestaurantLocation::class, 'location_id');
    }

    public function sessions()
    {
        return $this->hasMany(DiningSession::class);
    }
}
