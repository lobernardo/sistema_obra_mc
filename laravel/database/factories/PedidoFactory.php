<?php

namespace Database\Factories;

use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pedido>
 */
class PedidoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => sprintf('PED-%06d', fake()->unique()->numberBetween(1, 999999)),
            'obra_id' => Obra::factory(),
            'requester_id' => User::factory()->obra(),
            'needed_at' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'items_description' => fake()->sentence(),
            'status_id' => Status::factory()->solicitado(),
        ];
    }

    /**
     * Attach the requester to the pedido's obra, matching the domain
     * invariant that a requester normally has `obra_profile` access to the
     * obra they requested from.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Pedido $pedido): void {
            if (! $pedido->obra->users()->whereKey($pedido->requester_id)->exists()) {
                $pedido->obra->users()->attach($pedido->requester_id);
            }
        });
    }
}
