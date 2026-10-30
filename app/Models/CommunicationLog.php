<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunicationLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'immutable_datetime', 'replayed' => 'boolean'];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function location()
    {
        return $this->belongsTo(RestaurantLocation::class, 'location_id');
    }

    public function connection()
    {
        return $this->belongsTo(WordpressConnection::class, 'wordpress_connection_id');
    }
}
