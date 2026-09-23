<?php

namespace App\Actions\Pedidos\Concerns;

use App\Enums\RoleSlug;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Actor guards for the pedido mutations open to papel `obra` (CT-04): an
 * `obra` user may act only on a pedido it may view (`PedidoPolicy::view`:
 * associated obra, or its own pedido "Outra", RF-40). Checked here so the
 * Actions refuse a forged or direct call even without the UI.
 */
trait GuardsObraPedidoMutation
{
    /**
     * @throws AuthorizationException
     */
    private function ensureActorIsObraWithView(User $actor, Pedido $pedido): void
    {
        if ($actor->role?->slug !== RoleSlug::Obra->value || Gate::forUser($actor)->denies('view', $pedido)) {
            throw new AuthorizationException('Apenas o perfil "obra" com acesso a este pedido pode executar esta ação.');
        }
    }

    /**
     * Observations (RF-24, RF-25): any `suprimentos` user, or an `obra`
     * user with view rights on the pedido.
     *
     * @throws AuthorizationException
     */
    private function ensureActorMayObserve(User $actor, Pedido $pedido): void
    {
        if ($actor->role?->slug === RoleSlug::Suprimentos->value) {
            return;
        }

        try {
            $this->ensureActorIsObraWithView($actor, $pedido);
        } catch (AuthorizationException) {
            throw new AuthorizationException('Apenas os perfis Obra e Suprimentos podem adicionar observações.');
        }
    }
}
