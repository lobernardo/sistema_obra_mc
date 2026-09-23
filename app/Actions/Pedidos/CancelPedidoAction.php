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
 *
 * The status is re-read under `lockForUpdate` and checked again inside the
 * transaction, so a stale instance that lost a race (e.g. the Obra marked
 * the pedido Entregue meanwhile) answers 409 instead of writing a second
 * terminal event (RNF-02).
 */
class CancelPedidoAction
{
    use GuardsOperationalMutation;

    public function execute(User $actor, Pedido $pedido): Pedido
    {
        $this->ensureActorIsSuprimentos($actor);
        $this->ensurePedidoIsNotTerminal($pedido);

        return DB::transaction(function () use ($actor, $pedido) {
            $locked = Pedido::query()->whereKey($pedido->id)->lockForUpdate()->with('status')->firstOrFail();

            $this->ensurePedidoIsNotTerminal($locked);

            $canceladoId = Status::query()->where('slug', StatusSlug::Cancelado->value)->value('id');
            $previousStatusId = $locked->status_id;

            $locked->update(['status_id' => $canceladoId]);

            $locked->events()->create([
                'event_type_id' => EventType::query()->where('slug', EventTypeSlug::Cancelamento->value)->value('id'),
                'previous_value' => (string) $previousStatusId,
                'new_value' => (string) $canceladoId,
                'actor_id' => $actor->id,
            ]);

            return $locked->fresh();
        });
    }
}
