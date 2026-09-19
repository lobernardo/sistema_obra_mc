<?php

namespace Database\Factories;

use App\Enums\RoleSlug;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
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
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function obra(): static
    {
        return $this->state(fn () => ['name' => 'Obra', 'slug' => RoleSlug::Obra->value]);
    }

    public function suprimentos(): static
    {
        return $this->state(fn () => ['name' => 'Suprimentos', 'slug' => RoleSlug::Suprimentos->value]);
    }

    public function gestao(): static
    {
        return $this->state(fn () => ['name' => 'Gestão', 'slug' => RoleSlug::Gestao->value]);
    }
}
