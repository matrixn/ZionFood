<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WordpressConnection extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['webhook_secret'];

    protected $casts = ['location_ids' => 'array', 'abilities' => 'array', 'active' => 'boolean', 'webhook_secret' => 'encrypted'];

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->public_id ??= (string) Str::ulid());
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
