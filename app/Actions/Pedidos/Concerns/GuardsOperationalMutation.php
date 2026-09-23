<?php

namespace App\Actions\Pedidos\Concerns;

use App\Enums\RoleSlug;
use App\Enums\StatusSlug;
use App\Exceptions\Pedidos\PedidoTerminalStateException;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Shared guards for the 5 operational pedido mutations (responsável,
 * prioridade, previsão, status, cancelamento): only `suprimentos` may act
 * (RF-08b), and no operational mutation may reach a pedido that is already
 * in a terminal status (RF-13b) — checked here so no Action can forget it.
 */
trait GuardsOperationalMutation
{
    /**
     * @throws AuthorizationException
     */
    private function ensureActorIsSuprimentos(User $actor): void
    {
        if ($actor->role?->slug !== RoleSlug::Suprimentos->value) {
            throw new AuthorizationException('Apenas o perfil "suprimentos" pode executar esta ação.');
        }
    }

    /**
     * @throws PedidoTerminalStateException
     */
    private function ensurePedidoIsNotTerminal(Pedido $pedido): void
    {
        $statusSlug = StatusSlug::from($pedido->status->slug);

        if ($statusSlug->isTerminal()) {
            throw PedidoTerminalStateException::forPedido();
        }
    }

    /**
     * Romaneio upload and Finalizar (RF-32, RF-37): the pedido must be in an
     * active status or Entregue — the only exemption from Entregue's
     * terminality. Cancelado and Finalizado answer 409.
     *
     * @throws PedidoTerminalStateException
     */
    private function ensurePedidoIsFinalizable(Pedido $pedido): void
    {
        if (! in_array(StatusSlug::from($pedido->status->slug), StatusSlug::finalizableFrom(), true)) {
            throw PedidoTerminalStateException::forPedido();
        }
    }
}
