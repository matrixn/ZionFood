<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationStaff extends Model
{
    use HasFactory;

    protected $table = 'location_staff';

    protected $guarded = ['id'];
}
