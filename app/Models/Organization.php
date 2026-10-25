<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory;

    protected $table = 'organizations';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->public_id ??= (string) Str::ulid();
        });
    }

    public function memberships()
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function restaurants()
    {
        return $this->hasMany(Restaurant::class);
    }
}
