<?php

namespace App\Support;

use App\Enums\RoleSlug;
use App\Models\Pedido;
use App\Models\User;
use App\Notifications\Concerns\BuildsAppUrl;

/**
 * The pedido detail route of each papel (RF-15, UI-02): `obra` opens
 * `obra.pedidos.show`, `suprimentos` opens `suprimentos.pedidos.show` and
 * `gestao` opens `gestao.pedidos.show`. A papel outside the three has no
 * detail route.
 *
 * The absolute link used by e-mails is anchored on `APP_URL` through the
 * same rule as the authentication e-mails ({@see BuildsAppUrl}), never on
 * the host of the request that triggered the send.
 */
final class PedidoDetailRoute
{
    use BuildsAppUrl;

    private function __construct() {}

    public static function nameFor(User $user): ?string
    {
        return match ($user->role?->slug) {
            RoleSlug::Obra->value => 'obra.pedidos.show',
            RoleSlug::Suprimentos->value => 'suprimentos.pedidos.show',
            RoleSlug::Gestao->value => 'gestao.pedidos.show',
            default => null,
        };
    }

    public static function absoluteUrlFor(User $user, Pedido $pedido): ?string
    {
        $name = self::nameFor($user);

        if ($name === null) {
            return null;
        }

        return (new self)->appUrlToRoute($name, ['pedido' => $pedido]);
    }
}
