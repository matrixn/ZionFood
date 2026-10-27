<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItemVariant extends Model
{
    use HasFactory;

    protected $table = 'menu_item_variants';

    protected $guarded = ['id'];

    protected $casts = ['is_available' => 'boolean', 'is_default' => 'boolean', 'nutrition' => 'array'];

    public function modifierGroups()
    {
        return $this->belongsToMany(ModifierGroup::class, 'menu_variant_modifier_groups', 'variant_id', 'group_id')->withPivot('organization_id');
    }
}
