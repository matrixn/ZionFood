<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffInvitation extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected $casts = ['permissions' => 'array', 'location_ids' => 'array', 'expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
}
