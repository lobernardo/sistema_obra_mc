<?php

namespace Database\Factories;

use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Obra>
 */
class ObraFactory extends Factory
{
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'is_active' => true,
            'is_demo' => false,
        ];
    }
}
