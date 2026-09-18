<?php

namespace App\Actions\Pedidos;

use App\Actions\Pedidos\Concerns\GuardsOperationalMutation;
use App\Enums\EventTypeSlug;
use App\Enums\StatusSlug;
use App\Models\EventType;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a pedido that is still in a non-terminal status. `cancelado` is
 * irreversible — no Action ever moves a pedido out of it (RF-17). Restricted
 * to `suprimentos` actors (RF-17b).
 */
class CancelPedidoAction
{
    use GuardsOperationalMutation;

    public function execute(User $actor, Pedido $pedido): Pedido
    {
        $this->ensureActorIsSuprimentos($actor);
        $this->ensurePedidoIsNotTerminal($pedido);

        return DB::transaction(function () use ($actor, $pedido) {
            $canceladoId = Status::query()->where('slug', StatusSlug::Cancelado->value)->value('id');
            $previousStatusId = $pedido->status_id;

            $pedido->update(['status_id' => $canceladoId]);

            $pedido->events()->create([
                'event_type_id' => EventType::query()->where('slug', EventTypeSlug::Cancelamento->value)->value('id'),
                'previous_value' => (string) $previousStatusId,
                'new_value' => (string) $canceladoId,
                'actor_id' => $actor->id,
            ]);

            return $pedido->fresh();
        });
    }
}
