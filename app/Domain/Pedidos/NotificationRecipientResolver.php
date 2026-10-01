<?php

namespace App\Domain\Pedidos;

use App\Enums\RoleSlug;
use App\Models\PedidoEvent;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Single rule for who receives the internal notification of a history
 * event (RF-03..RF-08, RF-09, RNF-09). Read-only: it never writes.
 *
 * One `SELECT DISTINCT users.id` over `users ⋈ roles`, reading the event's
 * actor and the **current** row of its pedido in the same statement — run
 * inside the Action's transaction, it sees the new responsible of an
 * `alteracao_responsavel` (RF-04b). Every candidate is active and is not
 * the actor (RF-05, RF-06); DISTINCT keeps one id per user even when more
 * than one branch matches (RF-04a):
 *
 * - `gestao`: always (RF-03);
 * - pedido with obra: `obra` associated in `obra_profile`; `suprimentos`
 *   associated **or** equal to `responsible_id` (RF-04);
 * - pedido "Outra" (`obra_id` null): `obra` only when it is the requester;
 *   every `suprimentos` (RF-08).
 *
 * A papel outside the three never matches.
 *
 * The `obra` branch mirrors `PedidoPolicy::view` and `Pedido::visibleTo`
 * (RF-07): changing what an `obra` user may view requires changing this
 * rule too. The `suprimentos` scope is deliberately **narrower** than its
 * visibility — Suprimentos still opens every pedido, but is only notified
 * about the obras it is associated with or the pedidos it is responsible
 * for (RF-04).
 */
class NotificationRecipientResolver
{
    /**
     * @return list<int>
     */
    public function recipientIdsFor(PedidoEvent $event): array
    {
        return DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->crossJoin('pedido_events')
            ->join('pedidos', 'pedidos.id', '=', 'pedido_events.pedido_id')
            ->where('pedido_events.id', $event->getKey())
            ->where('users.is_active', true)
            ->whereColumn('users.id', '<>', 'pedido_events.actor_id')
            ->where(fn (Builder $recipient) => $recipient
                ->where('roles.slug', RoleSlug::Gestao->value)
                ->orWhere(fn (Builder $withObra) => $withObra
                    ->whereNotNull('pedidos.obra_id')
                    ->where(fn (Builder $scoped) => $scoped
                        ->where(fn (Builder $obraRole) => $obraRole
                            ->where('roles.slug', RoleSlug::Obra->value)
                            ->whereExists($this->associatedToPedidoObra()))
                        ->orWhere(fn (Builder $suprimentos) => $suprimentos
                            ->where('roles.slug', RoleSlug::Suprimentos->value)
                            ->where(fn (Builder $either) => $either
                                ->whereExists($this->associatedToPedidoObra())
                                ->orWhereColumn('users.id', 'pedidos.responsible_id')))))
                ->orWhere(fn (Builder $outra) => $outra
                    ->whereNull('pedidos.obra_id')
                    ->where(fn (Builder $scoped) => $scoped
                        ->where(fn (Builder $obraRole) => $obraRole
                            ->where('roles.slug', RoleSlug::Obra->value)
                            ->whereColumn('users.id', 'pedidos.requester_id'))
                        ->orWhere('roles.slug', RoleSlug::Suprimentos->value))))
            ->distinct()
            ->orderBy('users.id')
            ->pluck('users.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * `obra_profile` links the candidate user to the pedido's obra.
     */
    private function associatedToPedidoObra(): Closure
    {
        return fn (Builder $association) => $association
            ->selectRaw('1')
            ->from('obra_profile')
            ->whereColumn('obra_profile.obra_id', 'pedidos.obra_id')
            ->whereColumn('obra_profile.user_id', 'users.id');
    }
}
