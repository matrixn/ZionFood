<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationBusinessHour extends Model
{
    use HasFactory;

    protected $table = 'location_business_hours';

    protected $guarded = ['id'];
}
