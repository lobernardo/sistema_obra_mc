<?php

namespace Database\Factories;

use App\Models\Obra;
use App\Models\ObraInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Generates a random `token_hash` directly: no plaintext token ever exists
 * for a factory convite (RNF-01).
 *
 * @extends Factory<ObraInvitation>
 */
class ObraInvitationFactory extends Factory
{
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_by' => User::factory()->gestao(),
            'revoked_at' => now(),
        ]);
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'used_by' => User::factory()->obra(),
            'used_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => now()->subHours(25),
            'expires_at' => now()->subHour(),
        ]);
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'token_hash' => bin2hex(random_bytes(32)),
            'created_by' => User::factory()->gestao(),
            'expires_at' => now()->addHours(24),
        ];
    }
}
