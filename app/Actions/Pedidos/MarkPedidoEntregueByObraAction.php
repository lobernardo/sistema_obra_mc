<?php

namespace App\Actions\Pedidos;

use App\Actions\Pedidos\Concerns\GuardsObraPedidoMutation;
use App\Actions\Pedidos\Concerns\GuardsOperationalMutation;
use App\Enums\EventTypeSlug;
use App\Enums\StatusSlug;
use App\Exceptions\Pedidos\PedidoTerminalStateException;
use App\Models\EventType;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Obra-side "Marcar como entregue" (RF-27, RF-29, CT-04): an `obra` user
 * with view rights on the pedido (associated obra, or its own pedido
 * "Outra") moves it from any active status to `entregue`, writing exactly 1
 * `entrega` event with the obra user as actor — the same event type
 * `entreguesHoje` counts.
 *
 * Terminal pedidos (Entregue, Cancelado, Finalizado) answer 409 (RF-28).
 * The status is re-read under `lockForUpdate` inside the transaction and
 * checked again, so a stale instance that lost a race (e.g. a concurrent
 * Suprimentos cancellation) also answers 409 and writes nothing (RNF-02).
 */
class MarkPedidoEntregueByObraAction
{
    use GuardsObraPedidoMutation;
    use GuardsOperationalMutation;

    /**
     * @throws AuthorizationException
     * @throws PedidoTerminalStateException
     */
    public function execute(User $actor, Pedido $pedido): Pedido
    {
        $this->ensureActorIsObraWithView($actor, $pedido);
        $this->ensurePedidoIsNotTerminal($pedido);

        return DB::transaction(function () use ($actor, $pedido): Pedido {
            $locked = Pedido::query()->whereKey($pedido->id)->lockForUpdate()->with('status')->firstOrFail();

            $this->ensurePedidoIsNotTerminal($locked);

            $entregueId = Status::query()->where('slug', StatusSlug::Entregue->value)->value('id');
            $previousStatusId = $locked->status_id;

            $locked->update(['status_id' => $entregueId]);

            $locked->events()->create([
                'event_type_id' => EventType::query()->where('slug', EventTypeSlug::Entrega->value)->value('id'),
                'previous_value' => (string) $previousStatusId,
                'new_value' => (string) $entregueId,
                'actor_id' => $actor->id,
            ]);

            return $locked->fresh();
        });
    }
}
