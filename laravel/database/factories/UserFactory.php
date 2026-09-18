<?php

namespace Database\Factories;

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role_id' => Role::factory(),
            'is_active' => true,
            'is_demo' => false,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function obra(): static
    {
        return $this->withRole(RoleSlug::Obra, 'Obra');
    }

    public function suprimentos(): static
    {
        return $this->withRole(RoleSlug::Suprimentos, 'Suprimentos');
    }

    public function gestao(): static
    {
        return $this->withRole(RoleSlug::Gestao, 'Gestão');
    }

    private function withRole(RoleSlug $slug, string $name): static
    {
        return $this->state(fn () => [
            'role_id' => Role::query()->firstOrCreate(
                ['slug' => $slug->value],
                ['name' => $name],
            )->id,
        ]);
    }
}
