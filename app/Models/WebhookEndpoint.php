<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WebhookEndpoint extends Model
{
    use SoftDeletes;

    protected $casts = ['events' => 'array', 'enabled' => 'boolean'];

    use HasFactory;

    protected $table = 'webhook_endpoints';

    protected $guarded = ['id'];

    protected $hidden = ['secret_encrypted'];
}
