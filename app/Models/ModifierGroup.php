<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModifierGroup extends Model
{
    use HasFactory;

    protected $table = 'modifier_groups';

    protected $guarded = ['id'];

    public function options()
    {
        return $this->hasMany(ModifierOption::class, 'group_id');
    }
}
