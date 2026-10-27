<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ModifierGroup extends Model
{
    use HasFactory;

    protected $table = 'modifier_groups';

    protected $guarded = ['id'];

    protected $casts = ['is_required' => 'boolean', 'allow_quantity' => 'boolean'];

    public function scopeForMenus($query, $menuIds)
    {
        $categories = DB::table('menu_categories')->whereIn('menu_id', $menuIds)->select('id');
        $items = DB::table('menu_items')->whereIn('category_id', clone $categories)->whereNull('deleted_at')->select('id');

        return $query->where(function ($q) use ($categories, $items) {
            $q->whereNotIn('id', DB::table('import_entity_mappings')->where('entity_type', 'group')->select('local_id'))
                ->orWhereIn('id', DB::table('menu_category_modifier_groups')->whereIn('category_id', clone $categories)->select('group_id'))
                ->orWhereIn('id', DB::table('menu_item_modifier_groups')->whereIn('item_id', clone $items)->select('group_id'))
                ->orWhereIn('id', DB::table('menu_variant_modifier_groups')->whereIn('variant_id', DB::table('menu_item_variants')->whereIn('item_id', clone $items)->select('id'))->select('group_id'));
        });
    }

    public function options()
    {
        return $this->hasMany(ModifierOption::class, 'group_id');
    }
}
