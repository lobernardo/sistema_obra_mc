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
use Illuminate\Validation\ValidationException;

/**
 * Moves a pedido across the active workflow, or into `entregue` (RF-13).
 * `cancelado` is never a valid target here — that is `CancelPedidoAction`'s
 * distinct code path (RF-17). `finalizado` is never a valid target either:
 * it is reachable only through `FinalizePedidoAction`, which requires a
 * romaneio (RF-36) — a generic move (Kanban drop, "Mover para", detail
 * select) to it is a 422 "Transição de status inválida.", or a 409 when
 * the pedido is already terminal. Restricted to `suprimentos` actors and
 * rejected outright when the pedido's current status is already terminal
 * (RF-13b), regardless of how the target was produced (e.g. a forged
 * drag-and-drop payload — UI-07).
 */
class UpdatePedidoStatusAction
{
    use GuardsOperationalMutation;

    public function execute(User $actor, Pedido $pedido, int $targetStatusId): Pedido
    {
        $this->ensureActorIsSuprimentos($actor);
        $this->ensurePedidoIsNotTerminal($pedido);

        $targetStatus = Status::query()->findOrFail($targetStatusId);
        $targetSlug = StatusSlug::from($targetStatus->slug);
        $currentSlug = StatusSlug::from($pedido->status->slug);

        $allowedTargets = [...StatusSlug::activeNonFinal(), StatusSlug::Entregue];

        if ($targetSlug === $currentSlug || ! in_array($targetSlug, $allowedTargets, true)) {
            throw ValidationException::withMessages([
                'status_id' => 'Transição de status inválida.',
            ]);
        }

        $eventTypeSlug = $targetSlug === StatusSlug::Entregue
            ? EventTypeSlug::Entrega
            : EventTypeSlug::MudancaStatus;

        return DB::transaction(function () use ($actor, $pedido, $targetStatus, $eventTypeSlug) {
            $previousStatusId = $pedido->status_id;

            $pedido->update(['status_id' => $targetStatus->id]);

            $pedido->events()->create([
                'event_type_id' => EventType::query()->where('slug', $eventTypeSlug->value)->value('id'),
                'previous_value' => (string) $previousStatusId,
                'new_value' => (string) $targetStatus->id,
                'actor_id' => $actor->id,
            ]);

            return $pedido->fresh();
        });
    }
}
