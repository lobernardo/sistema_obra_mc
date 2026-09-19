<?php

namespace App\Exceptions\Pedidos;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * Thrown when any operational mutation (status, responsável, prioridade,
 * previsão, cancelamento) is attempted against a pedido whose status is
 * already terminal (`entregue` or `cancelado`) — a state that is
 * irreversible and SHALL NOT be mutated further (RF-13b).
 *
 * Renders as a structured HTTP 409 (state conflict, per CT-01) instead of a
 * fatal error — including when raised from within a Livewire component
 * action (e.g. a forged Kanban drag-and-drop against a terminal pedido,
 * UI-07), where an unrendered exception would otherwise abort the request.
 */
class PedidoTerminalStateException extends RuntimeException
{
    public static function forPedido(): self
    {
        return new self('Pedido em status terminal não pode ser alterado.');
    }

    public function render(Request $request): Response
    {
        return response($this->getMessage(), 409);
    }
}
