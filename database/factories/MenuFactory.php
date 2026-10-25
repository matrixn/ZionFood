<?php

namespace Database\Factories;

use App\Models\Menu;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuFactory extends Factory
{
    protected $model = Menu::class;

    public function definition(): array
    {
        return ['restaurant_id' => Restaurant::factory(), 'organization_id' => fn (array $a) => Restaurant::findOrFail($a['restaurant_id'])->organization_id, 'name' => 'Main menu'];
    }
}
