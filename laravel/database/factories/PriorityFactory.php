<?php

namespace Database\Factories;

use App\Enums\PrioritySlug;
use App\Models\Priority;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Priority>
 */
class PriorityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = Str::slug(fake()->unique()->words(3, true));

        return [
            'name' => ucfirst($slug),
            'slug' => $slug,
            'sort_order' => fake()->unique()->numberBetween(100, 10000),
            'is_active' => true,
        ];
    }

    private function named(PrioritySlug $slug, string $name, int $sortOrder): static
    {
        return $this->state(fn () => [
            'name' => $name,
            'slug' => $slug->value,
            'sort_order' => $sortOrder,
        ]);
    }

    public function baixa(): static
    {
        return $this->named(PrioritySlug::Baixa, 'Baixa', 1);
    }

    public function normal(): static
    {
        return $this->named(PrioritySlug::Normal, 'Normal', 2);
    }

    public function alta(): static
    {
        return $this->named(PrioritySlug::Alta, 'Alta', 3);
    }

    public function urgente(): static
    {
        return $this->named(PrioritySlug::Urgente, 'Urgente', 4);
    }
}
