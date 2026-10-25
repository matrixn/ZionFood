<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

class RestaurantLocationFactory extends Factory
{
    protected $model = RestaurantLocation::class;

    public function definition(): array
    {
        return ['restaurant_id' => Restaurant::factory(), 'organization_id' => fn (array $a) => Restaurant::findOrFail($a['restaurant_id'])->organization_id, 'name' => 'Main', 'address' => fake()->address(), 'timezone' => 'Europe/Bucharest', 'currency' => 'RON', 'accepting_orders' => false];
    }
}
