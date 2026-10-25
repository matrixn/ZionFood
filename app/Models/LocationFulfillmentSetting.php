<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationFulfillmentSetting extends Model
{
    use HasFactory;

    protected $table = 'location_fulfillment_settings';

    protected $guarded = ['id'];

    protected $casts = ['pickup_enabled' => 'boolean', 'delivery_enabled' => 'boolean'];
}
