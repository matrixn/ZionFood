<?php

namespace Database\Factories;

use App\Models\Menu;
use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuCategoryFactory extends Factory
{
    protected $model = MenuCategory::class;

    public function definition(): array
    {
        return ['menu_id' => Menu::factory(), 'organization_id' => fn (array $a) => Menu::findOrFail($a['menu_id'])->organization_id, 'name' => fake()->word()];
    }
}
