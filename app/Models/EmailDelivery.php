<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailDelivery extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['recipients', 'recipient_hashes', 'subject', 'context'];

    protected $casts = ['recipients' => 'encrypted:array', 'recipient_hashes' => 'array', 'subject' => 'encrypted', 'context' => 'encrypted:array', 'available_at' => 'datetime', 'lease_until' => 'datetime', 'expires_at' => 'datetime', 'accepted_at' => 'datetime', 'attempts' => 'integer'];

    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }
}
