<?php

namespace App\Models;

use App\Domain\Restaurants\LocationPreferences;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RestaurantLocation extends Model
{
    use HasFactory;

    protected $table = 'restaurant_locations';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->public_id ??= (string) Str::ulid();
        });
    }

    protected $casts = ['accepting_orders' => 'boolean', 'setup_profile' => 'array', 'setup_revision' => 'integer', 'preferences' => 'array', 'preferences_revision' => 'integer'];

    protected $hidden = ['setup_profile', 'preferences'];

    protected $appends = ['storefront_path', 'display_settings', 'ordering_settings'];

    public function getOrderingSettingsAttribute(): array
    {
        return ['block_unavailable_online_orders' => (bool) ($this->preferences['block_unavailable_online_orders'] ?? true)];
    }

    public function getDisplaySettingsAttribute(): array
    {
        return app(LocationPreferences::class)->values($this);
    }

    public function getStorefrontPathAttribute(): string
    {
        $path = '/restaurant/'.$this->restaurant->slug;

        return $this->restaurant->locations()->where('id', '!=', $this->id)->exists() ? $path.'/'.$this->public_id : $path;
    }

    public function cuisines()
    {
        return $this->belongsToMany(Cuisine::class, 'location_cuisines', 'location_id', 'cuisine_id')->withPivot('organization_id')->withTimestamps();
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function hours()
    {
        return $this->hasMany(LocationBusinessHour::class, 'location_id');
    }

    public function fulfillment()
    {
        return $this->hasOne(LocationFulfillmentSetting::class, 'location_id');
    }

    public function zones()
    {
        return $this->hasMany(DeliveryZone::class, 'location_id');
    }

    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'location_menus', 'location_id', 'menu_id')->withPivot('organization_id');
    }
}
