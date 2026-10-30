<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Restaurant extends Model
{
    use HasFactory;

    protected $table = 'restaurants';

    protected $guarded = ['id'];

    protected $casts = ['communication_logs_enabled' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->public_id ??= (string) Str::ulid();
        });
    }

    public function locations()
    {
        return $this->hasMany(RestaurantLocation::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function menus()
    {
        return $this->hasMany(Menu::class);
    }
}
