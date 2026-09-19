<?php

namespace App\Actions\Pedidos;

use App\Enums\EventTypeSlug;
use App\Models\EventType;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use App\Services\PedidoCodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Creates a pedido on behalf of an `obra` requester.
 *
 * `obra_id` is validated against the requester's `obra_profile` membership
 * server-side (RF-11c), independently of whatever the UI's obra selector
 * offers. The pedido insert and its `criacao_pedido` history event are
 * written in a single transaction: if the event insert fails, the pedido
 * insert rolls back with it (RF-18).
 */
class CreatePedidoAction
{
    public function __construct(
        private readonly PedidoCodeGenerator $codeGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $requester, array $data): Pedido
    {
        $validated = Validator::make($data, [
            'obra_id' => ['required', 'integer'],
            'needed_at' => ['required', 'date'],
            'items_description' => ['required', 'string'],
        ])->validate();

        $isAssociated = $requester->obras()->whereKey($validated['obra_id'])->exists();

        if (! $isAssociated) {
            throw ValidationException::withMessages([
                'obra_id' => 'A obra informada não está associada ao solicitante.',
            ]);
        }

        return DB::transaction(function () use ($requester, $validated) {
            $status = Status::query()->orderBy('sort_order')->firstOrFail();

            $pedido = Pedido::query()->create([
                'code' => $this->codeGenerator->generate(),
                'obra_id' => $validated['obra_id'],
                'requester_id' => $requester->id,
                'needed_at' => $validated['needed_at'],
                'items_description' => $validated['items_description'],
                'status_id' => $status->id,
            ]);

            $pedido->events()->create([
                'event_type_id' => EventType::query()->where('slug', EventTypeSlug::CriacaoPedido->value)->value('id'),
                'actor_id' => $requester->id,
            ]);

            return $pedido;
        });
    }
}
