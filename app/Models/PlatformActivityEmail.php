<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformActivityEmail extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['details'];

    protected $casts = ['details' => 'encrypted:array', 'queued_at' => 'datetime'];
}
