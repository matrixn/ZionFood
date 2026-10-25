<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationSubscription extends Model
{
    use HasFactory;

    protected $table = 'organization_subscriptions';

    protected $guarded = ['id'];

    protected $casts = ['trial_ends_at' => 'datetime'];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
