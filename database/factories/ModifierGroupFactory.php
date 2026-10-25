<?php

namespace Database\Factories;

use App\Models\ModifierGroup;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ModifierGroupFactory extends Factory
{
    protected $model = ModifierGroup::class;

    public function definition(): array
    {
        return ['organization_id' => Organization::factory(), 'name' => 'Extras', 'min_choices' => 0, 'max_choices' => 2];
    }
}
