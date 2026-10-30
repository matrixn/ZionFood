<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Promotion extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean', 'product_ids' => 'array', 'fulfillments' => 'array', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->public_id ??= (string) Str::ulid());
    }
}
