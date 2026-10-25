<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookEndpoint extends Model
{
    use HasFactory;

    protected $table = 'webhook_endpoints';

    protected $guarded = ['id'];

    protected $hidden = ['secret_encrypted'];
}
