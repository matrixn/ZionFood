<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MenuContentDraft extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['input'];

    protected $casts = ['input' => 'encrypted:array', 'proposal' => 'encrypted:array', 'report' => 'encrypted:array', 'reviewed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn ($record) => $record->public_id ??= (string) Str::ulid());
    }
}
