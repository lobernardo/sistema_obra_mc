<?php

namespace Database\Factories;

use App\Enums\AuthenticationEventType;
use App\Models\AuthenticationEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuthenticationEvent>
 */
class AuthenticationEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event' => AuthenticationEventType::LoginSuccess,
            'user_id' => User::factory()->obra(),
            'email' => fn (array $attributes): string => $attributes['user_id'] === null
                ? fake()->unique()->safeEmail()
                : User::query()->findOrFail($attributes['user_id'])->email,
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
