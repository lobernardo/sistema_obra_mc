<?php

namespace App\Actions\Pedidos;

use App\Actions\Pedidos\Concerns\GuardsOperationalMutation;
use App\Enums\EventTypeSlug;
use App\Models\EventType;
use App\Models\Pedido;
use App\Models\User;
use App\Rules\ResponsibleMustBeSuprimentos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Assigns or reassigns a pedido's responsável. Restricted to `suprimentos`
 * actors (RF-14b), a no-op when the value is unchanged (RF-14), and
 * rejected outright on a terminal pedido (RF-13b).
 */
class UpdatePedidoResponsavelAction
{
    use GuardsOperationalMutation;

    public function execute(User $actor, Pedido $pedido, ?int $responsibleId): Pedido
    {
        $this->ensureActorIsSuprimentos($actor);
        $this->ensurePedidoIsNotTerminal($pedido);

        if ($pedido->responsible_id === $responsibleId) {
            return $pedido;
        }

        Validator::make(['responsible_id' => $responsibleId], [
            'responsible_id' => ['required', 'integer', 'exists:users,id', new ResponsibleMustBeSuprimentos],
        ], [
            'responsible_id.required' => 'Selecione o responsável.',
            'responsible_id.integer' => 'Responsável inválido.',
            'responsible_id.exists' => 'Responsável inválido.',
        ])->validate();

        return DB::transaction(function () use ($actor, $pedido, $responsibleId) {
            $previousResponsibleId = $pedido->responsible_id;

            $pedido->update(['responsible_id' => $responsibleId]);

            $pedido->events()->create([
                'event_type_id' => EventType::query()->where('slug', EventTypeSlug::AlteracaoResponsavel->value)->value('id'),
                'previous_value' => $previousResponsibleId !== null ? (string) $previousResponsibleId : null,
                'new_value' => (string) $responsibleId,
                'actor_id' => $actor->id,
            ]);

            return $pedido->fresh();
        });
    }
}
