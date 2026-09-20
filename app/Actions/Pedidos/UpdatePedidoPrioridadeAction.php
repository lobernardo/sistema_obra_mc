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
 * Sets a pedido's prioridade. Restricted to `suprimentos` actors, to the 4
 * seeded priority values (RF-15), a no-op when unchanged, and rejected
 * outright on a terminal pedido (RF-13b).
 */
class UpdatePedidoPrioridadeAction
{
    use GuardsOperationalMutation;

    public function execute(User $actor, Pedido $pedido, int $priorityId): Pedido
    {
        $this->ensureActorIsSuprimentos($actor);
        $this->ensurePedidoIsNotTerminal($pedido);

        Validator::make(['priority_id' => $priorityId], [
            'priority_id' => ['required', 'integer', 'exists:priorities,id'],
        ], [
            'priority_id.required' => 'Selecione a prioridade.',
            'priority_id.integer' => 'Prioridade inválida.',
            'priority_id.exists' => 'Prioridade inválida.',
        ])->validate();

        if ($pedido->priority_id === $priorityId) {
            return $pedido;
        }

        return DB::transaction(function () use ($actor, $pedido, $priorityId) {
            $previousPriorityId = $pedido->priority_id;

            $pedido->update(['priority_id' => $priorityId]);

            $pedido->events()->create([
                'event_type_id' => EventType::query()->where('slug', EventTypeSlug::AlteracaoPrioridade->value)->value('id'),
                'previous_value' => $previousPriorityId !== null ? (string) $previousPriorityId : null,
                'new_value' => (string) $priorityId,
                'actor_id' => $actor->id,
            ]);

            return $pedido->fresh();
        });
    }
}
