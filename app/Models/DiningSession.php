<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiningSession extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['token_hash', 'token_encrypted'];

    protected $casts = ['expires_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime'];

    public function table()
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }
}
