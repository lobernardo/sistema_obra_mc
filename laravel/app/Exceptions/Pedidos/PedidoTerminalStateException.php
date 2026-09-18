<?php

namespace App\Exceptions\Pedidos;

use RuntimeException;

/**
 * Thrown when any operational mutation (status, responsável, prioridade,
 * previsão, cancelamento) is attempted against a pedido whose status is
 * already terminal (`entregue` or `cancelado`) — a state that is
 * irreversible and SHALL NOT be mutated further (RF-13b).
 */
class PedidoTerminalStateException extends RuntimeException
{
    public static function forPedido(): self
    {
        return new self('Pedido em status terminal não pode ser alterado.');
    }
}
