<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModifierOption extends Model
{
    use HasFactory;

    protected $table = 'modifier_options';

    protected $guarded = ['id'];

    protected $casts = ['is_available' => 'boolean', 'is_default' => 'boolean'];
}
