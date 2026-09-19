<?php

namespace App\Actions\Pedidos;

use App\Actions\Pedidos\Concerns\GuardsOperationalMutation;
use App\Enums\EventTypeSlug;
use App\Models\EventType;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Sets a pedido's previsão de entrega. Restricted to `suprimentos` actors, a
 * no-op when the date is unchanged (RF-16), and rejected outright on a
 * terminal pedido (RF-13b).
 */
class UpdatePedidoPrevisaoAction
{
    use GuardsOperationalMutation;

    public function execute(User $actor, Pedido $pedido, string $expectedDeliveryAt): Pedido
    {
        $this->ensureActorIsSuprimentos($actor);
        $this->ensurePedidoIsNotTerminal($pedido);

        Validator::make(['expected_delivery_at' => $expectedDeliveryAt], [
            'expected_delivery_at' => ['required', 'date'],
        ])->validate();

        $previousExpectedDeliveryAt = $pedido->expected_delivery_at?->toDateString();

        if ($previousExpectedDeliveryAt === $expectedDeliveryAt) {
            return $pedido;
        }

        return DB::transaction(function () use ($actor, $pedido, $expectedDeliveryAt, $previousExpectedDeliveryAt) {
            $pedido->update(['expected_delivery_at' => $expectedDeliveryAt]);

            $pedido->events()->create([
                'event_type_id' => EventType::query()->where('slug', EventTypeSlug::AlteracaoPrevisao->value)->value('id'),
                'previous_value' => $previousExpectedDeliveryAt,
                'new_value' => $expectedDeliveryAt,
                'actor_id' => $actor->id,
            ]);

            return $pedido->fresh();
        });
    }
}
