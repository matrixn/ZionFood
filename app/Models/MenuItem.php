<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MenuItem extends Model
{
    use HasFactory;

    protected $table = 'menu_items';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->public_id ??= (string) Str::ulid();
        });
    }

    use SoftDeletes;

    protected $casts = ['is_available' => 'boolean', 'contains_allergens' => 'boolean', 'from_frozen' => 'boolean'];

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'menu_item_ingredients', 'item_id', 'ingredient_id')->withTrashed()->withPivot('organization_id', 'position')->orderByPivot('position');
    }

    public function category()
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
    }

    public function variants()
    {
        return $this->hasMany(MenuItemVariant::class, 'item_id');
    }

    public function modifierGroups()
    {
        return $this->belongsToMany(ModifierGroup::class, 'menu_item_modifier_groups', 'item_id', 'group_id')->withPivot('organization_id');
    }
}
