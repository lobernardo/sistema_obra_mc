<?php

namespace Database\Factories;

use App\Models\EventType;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PedidoEvent>
 */
class PedidoEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pedido_id' => Pedido::factory(),
            'event_type_id' => EventType::factory()->criacaoPedido(),
            'previous_value' => null,
            'new_value' => null,
            'actor_id' => User::factory(),
        ];
    }
}
