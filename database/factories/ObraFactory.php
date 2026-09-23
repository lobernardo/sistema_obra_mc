<?php

namespace Database\Factories;

use App\Enums\ObraStatus;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Obra>
 */
class ObraFactory extends Factory
{
    public function aIniciar(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ObraStatus::AIniciar]);
    }

    public function emAndamento(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ObraStatus::EmAndamento]);
    }

    public function concluida(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ObraStatus::Concluido]);
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
            'responsavel' => null,
            'status' => ObraStatus::EmAndamento,
            'is_demo' => false,
        ];
    }
}
