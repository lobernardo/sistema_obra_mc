<?php

namespace App\Actions\Pedidos;

use App\Actions\Pedidos\Concerns\GuardsOperationalMutation;
use App\Enums\EventTypeSlug;
use App\Enums\StatusSlug;
use App\Exceptions\Pedidos\PedidoTerminalStateException;
use App\Models\EventType;
use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Models\Status;
use App\Models\User;
use App\Services\PedidoAttachmentStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Finalizar pedido" by Suprimentos (RF-33..RF-38, CT-04): the only path to
 * `finalizado` (RF-36), from any active status or from Entregue — the only
 * transition out of a terminal status (RF-37).
 *
 * Inside the transaction the pedido is re-read under `lockForUpdate` and its
 * status checked again, so the second of two concurrent finalizations sees
 * `finalizado` and answers 409 without a second `finalizacao` event (RF-38).
 * A valid romaneio is a `romaneio` row of this pedido whose file is present
 * in attachment storage (RF-34); without one the request is refused with the
 * exact RF-35 message and nothing changes. Otherwise the status becomes
 * `finalizado` and exactly 1 `finalizacao` event is written; earlier events
 * are kept (RF-22).
 */
class FinalizePedidoAction
{
    use GuardsOperationalMutation;

    public const string ERROR_KEY = 'finalizar';

    public const string MISSING_ROMANEIO_MESSAGE = 'Não foi possível finalizar o pedido. Anexe o romaneio antes de finalizar.';

    public function __construct(private readonly PedidoAttachmentStorage $attachmentStorage) {}

    /**
     * @throws AuthorizationException
     * @throws PedidoTerminalStateException
     * @throws ValidationException
     */
    public function execute(User $actor, Pedido $pedido): Pedido
    {
        $this->ensureActorIsSuprimentos($actor);
        $this->ensurePedidoIsFinalizable($pedido);

        return DB::transaction(function () use ($actor, $pedido): Pedido {
            $locked = Pedido::query()->whereKey($pedido->id)->lockForUpdate()->with('status')->firstOrFail();

            $this->ensurePedidoIsFinalizable($locked);

            $hasValidRomaneio = $locked->romaneios()->get()
                ->contains(fn (PedidoAttachment $romaneio): bool => $this->attachmentStorage->exists($romaneio->path));

            if (! $hasValidRomaneio) {
                throw ValidationException::withMessages([self::ERROR_KEY => self::MISSING_ROMANEIO_MESSAGE]);
            }

            $finalizadoId = Status::query()->where('slug', StatusSlug::Finalizado->value)->value('id');
            $previousStatusId = $locked->status_id;

            $locked->update(['status_id' => $finalizadoId]);

            $locked->events()->create([
                'event_type_id' => EventType::query()->where('slug', EventTypeSlug::Finalizacao->value)->value('id'),
                'previous_value' => (string) $previousStatusId,
                'new_value' => (string) $finalizadoId,
                'actor_id' => $actor->id,
            ]);

            return $locked->fresh();
        });
    }
}
