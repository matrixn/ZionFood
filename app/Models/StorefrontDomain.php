<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StorefrontDomain extends Model
{
    use HasFactory;

    protected $table = 'storefront_domains';

    protected $guarded = ['id'];

    public function location()
    {
        return $this->belongsTo(RestaurantLocation::class, 'location_id');
    }
}
