<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * `view` restricts `obra` actors to pedidos of an associated obra
 * (`obra_profile`, RF-10) or to their own pedidos "Outra" (no obra,
 * `requester_id` = user, RF-40) — mirroring `Pedido::visibleTo` — while `suprimentos`/`gestao` read unrestricted
 * (RF-08b, RF-08c). `create` delegates to the `create-pedido` ability
 * (Obra, Suprimentos and Gestão, RF-01); the obra itself is checked by
 * `CreatePedidoAction` (RF-03, RF-07). The 5 operational mutations, the
 * romaneio upload and Finalizar are `suprimentos` or `gestao` (RF-08b,
 * RF-31, RF-36); "Marcar como entregue" is `obra` with view rights (RF-27).
 */
class PedidoPolicy
{
    public function view(User $user, Pedido $pedido): bool
    {
        return match ($user->role?->slug) {
            RoleSlug::Obra->value => $pedido->obra_id === null
                ? $pedido->requester_id === $user->id
                : $user->obras()->whereKey($pedido->obra_id)->exists(),
            RoleSlug::Suprimentos->value, RoleSlug::Gestao->value => true,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return Gate::forUser($user)->allows('create-pedido');
    }

    /**
     * Observations (RF-24, RF-25): any `suprimentos` or `gestao` user, or
     * an `obra` user who may view the pedido.
     */
    public function addObservacao(User $user, Pedido $pedido): bool
    {
        return $this->operatesPedidos($user)
            || ($user->role?->slug === RoleSlug::Obra->value && $this->view($user, $pedido));
    }

    /**
     * Obra-side "Marcar como entregue" (RF-27, RF-28): only an `obra` user
     * who may view the pedido.
     */
    public function marcarEntregue(User $user, Pedido $pedido): bool
    {
        return $user->role?->slug === RoleSlug::Obra->value && $this->view($user, $pedido);
    }

    /**
     * Romaneio upload (RF-30, RF-31): `suprimentos` or `gestao`.
     */
    public function anexarRomaneio(User $user, Pedido $pedido): bool
    {
        return $this->operatesPedidos($user);
    }

    /**
     * Finalizar pedido (RF-34, RF-36): `suprimentos` or `gestao`.
     */
    public function finalizar(User $user, Pedido $pedido): bool
    {
        return $this->operatesPedidos($user);
    }

    public function setResponsavel(User $user, Pedido $pedido): bool
    {
        return $this->operatesPedidos($user);
    }

    public function setPrioridade(User $user, Pedido $pedido): bool
    {
        return $this->operatesPedidos($user);
    }

    public function setPrevisao(User $user, Pedido $pedido): bool
    {
        return $this->operatesPedidos($user);
    }

    public function updateStatus(User $user, Pedido $pedido): bool
    {
        return $this->operatesPedidos($user);
    }

    public function cancelar(User $user, Pedido $pedido): bool
    {
        return $this->operatesPedidos($user);
    }

    private function operatesPedidos(User $user): bool
    {
        return Gate::forUser($user)->allows('operate-pedidos');
    }
}
