<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuAiSetting extends Model
{
    protected $fillable = ['id', 'enabled', 'provider', 'model', 'api_key', 'daily_limit'];

    protected $hidden = ['api_key'];

    protected $casts = ['enabled' => 'boolean', 'api_key' => 'encrypted', 'daily_limit' => 'integer'];
}
