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
 * (Obra and Suprimentos, RF-01); the obra itself is checked by
 * `CreatePedidoAction` (RF-03, RF-07). The 5 operational mutations are `suprimentos`
 * only (RF-08b) — `gestao` is never authorized to write (RF-20).
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
     * Observations (RF-24, RF-25): any `suprimentos` user, or an `obra`
     * user who may view the pedido. `gestao` never writes (RF-20).
     */
    public function addObservacao(User $user, Pedido $pedido): bool
    {
        return $this->isSuprimentos($user)
            || ($user->role?->slug === RoleSlug::Obra->value && $this->view($user, $pedido));
    }

    public function setResponsavel(User $user, Pedido $pedido): bool
    {
        return $this->isSuprimentos($user);
    }

    public function setPrioridade(User $user, Pedido $pedido): bool
    {
        return $this->isSuprimentos($user);
    }

    public function setPrevisao(User $user, Pedido $pedido): bool
    {
        return $this->isSuprimentos($user);
    }

    public function updateStatus(User $user, Pedido $pedido): bool
    {
        return $this->isSuprimentos($user);
    }

    public function cancelar(User $user, Pedido $pedido): bool
    {
        return $this->isSuprimentos($user);
    }

    private function isSuprimentos(User $user): bool
    {
        return $user->role?->slug === RoleSlug::Suprimentos->value;
    }
}
