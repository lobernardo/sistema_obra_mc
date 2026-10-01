<?php

namespace App\Actions\Pedidos;

use App\Actions\Pedidos\Concerns\GuardsObraPedidoMutation;
use App\Enums\EventTypeSlug;
use App\Models\EventType;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use App\Services\PedidoNotificationRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Registers an "Observação / ocorrência" on a pedido's history (RF-10,
 * CT-04): exactly 1 `observacao` event carrying the trimmed free text in
 * `new_value` — falta de produto, troca, atraso, problema de entrega or any
 * other relevant information, with no category. The pedido row itself is
 * never touched (status and `updated_at` unchanged), and earlier events are
 * never altered.
 *
 * Allowed for `suprimentos` and `gestao` (via `operate-pedidos`) and for an
 * `obra` user with view rights on the pedido (RF-12); any other actor is a
 * 403. Blank or longer than `MAX_LENGTH` is a 422 (RF-11). There is
 * deliberately no terminal-state guard: observations are accepted in every
 * status, Entregue, Cancelado and Finalizado included (RF-13).
 *
 * The event is handed to `PedidoNotificationRecorder` inside the same
 * transaction, so its notifications commit or roll back with it (RF-01).
 */
class AddPedidoObservacaoAction
{
    use GuardsObraPedidoMutation;

    public const int MAX_LENGTH = 2000;

    public function __construct(private readonly PedidoNotificationRecorder $notificationRecorder) {}

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

        return DB::transaction(function () use ($actor, $pedido, $observacao): PedidoEvent {
            $event = PedidoEvent::query()->create([
                'pedido_id' => $pedido->id,
                'event_type_id' => EventType::query()->where('slug', EventTypeSlug::Observacao->value)->value('id'),
                'previous_value' => null,
                'new_value' => $observacao,
                'actor_id' => $actor->id,
            ]);

            $this->notificationRecorder->record($event);

            return $event;
        });
    }
}
