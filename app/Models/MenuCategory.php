<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuCategory extends Model
{
    use HasFactory;

    protected $table = 'menu_categories';

    protected $guarded = ['id'];

    public function items()
    {
        return $this->hasMany(MenuItem::class, 'category_id');
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }
}
