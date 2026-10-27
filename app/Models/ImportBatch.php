<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ImportBatch extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['credential'];

    protected $casts = ['settings' => 'array', 'credential' => 'encrypted'];

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->public_id ??= (string) Str::ulid());
    }
}
