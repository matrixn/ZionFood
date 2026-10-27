<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WorkspaceNotification extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['read_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn ($record) => $record->public_id ??= (string) Str::ulid());
    }

    public function draft()
    {
        return $this->belongsTo(MenuContentDraft::class, 'draft_id');
    }
}
