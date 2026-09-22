<?php

namespace Database\Factories;

use App\Enums\UserAdminAction;
use App\Models\User;
use App\Models\UserAdminEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAdminEvent>
 */
class UserAdminEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => User::factory()->gestao(),
            'target_id' => User::factory()->gestao(),
            'action' => UserAdminAction::UserUpdated,
            'before' => ['name' => fake()->name()],
            'after' => ['name' => fake()->name()],
        ];
    }
}
