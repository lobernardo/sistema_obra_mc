<?php

namespace App\Actions\Pedidos;

use App\Actions\Pedidos\Concerns\GuardsObraPedidoMutation;
use App\Enums\EventTypeSlug;
use App\Models\EventType;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Adds a free-text observation to a pedido's history (RF-24): exactly 1
 * `observacao` event carrying the trimmed text in `new_value`. The pedido
 * row itself is never touched (status and `updated_at` unchanged), and
 * earlier events are never altered (RF-22).
 *
 * Allowed for `suprimentos` and for an `obra` user with view rights
 * (RF-25). There is deliberately no terminal-state guard: observations are
 * accepted in every status, Entregue, Cancelado and Finalizado included
 * (RF-26).
 */
class AddPedidoObservacaoAction
{
    use GuardsObraPedidoMutation;

    public const int MAX_LENGTH = 2000;

    public function execute(User $actor, Pedido $pedido, string $texto): PedidoEvent
    {
        $this->ensureActorMayObserve($actor, $pedido);

        $observacao = trim($texto);

        Validator::make(['observacao' => $observacao], [
            'observacao' => ['required', 'string', 'max:'.self::MAX_LENGTH],
        ], [
            'observacao.required' => 'Escreva a observação.',
            'observacao.max' => 'A observação deve ter no máximo 2000 caracteres.',
        ])->validate();

        return DB::transaction(fn (): PedidoEvent => PedidoEvent::query()->create([
            'pedido_id' => $pedido->id,
            'event_type_id' => EventType::query()->where('slug', EventTypeSlug::Observacao->value)->value('id'),
            'previous_value' => null,
            'new_value' => $observacao,
            'actor_id' => $actor->id,
        ]));
    }
}
