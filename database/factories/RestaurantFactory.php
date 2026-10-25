<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

class RestaurantFactory extends Factory
{
    protected $model = Restaurant::class;

    public function definition(): array
    {
        return ['organization_id' => Organization::factory(), 'name' => fake()->company(), 'slug' => fake()->unique()->slug()];
    }
}
