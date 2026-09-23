<?php

namespace Database\Factories;

use App\Enums\ObraAdminAction;
use App\Models\Obra;
use App\Models\ObraAdminEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ObraAdminEvent>
 */
class ObraAdminEventFactory extends Factory
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
            'obra_id' => Obra::factory(),
            'obra_invitation_id' => null,
            'action' => ObraAdminAction::ObraUpdated,
            'before' => ['name' => fake()->company()],
            'after' => ['name' => fake()->company()],
        ];
    }
}
