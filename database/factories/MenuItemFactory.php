<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return ['category_id' => MenuCategory::factory(), 'organization_id' => fn (array $a) => MenuCategory::findOrFail($a['category_id'])->organization_id, 'name' => fake()->words(2, true), 'price' => fake()->numberBetween(1000, 9000), 'is_available' => true];
    }
}
