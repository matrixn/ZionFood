<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Ingredient extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = ['restaurant_id' => 'integer', 'organization_id' => 'integer', 'contains_allergens' => 'boolean', 'from_frozen' => 'boolean', 'is_active' => 'boolean', 'needs_review' => 'boolean'];

    public static function normalized(string $name): string
    {
        return Str::lower(Str::ascii(Str::squish($name)));
    }

    public function items()
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_ingredients', 'ingredient_id', 'item_id');
    }

    public static function availableFor(int $restaurantId)
    {
        return static::where(fn ($q) => $q->whereNull('restaurant_id')->orWhere('restaurant_id', $restaurantId));
    }
}
