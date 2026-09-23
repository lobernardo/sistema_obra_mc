<?php

namespace Database\Factories;

use App\Enums\AccountOrigin;
use App\Models\AccountRegistrationEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountRegistrationEvent>
 */
class AccountRegistrationEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->obra(),
            'origin' => AccountOrigin::NovoCadastro,
            'obra_invitation_id' => null,
            'ip' => fake()->ipv4(),
        ];
    }
}
