<?php

namespace App\Actions\Obras;

use App\Actions\Obras\Concerns\GuardsObraAdministration;
use App\Enums\ObraAdminAction;
use App\Exceptions\Obras\ObraNotFoundException;
use App\Models\Obra;
use App\Models\User;
use App\Services\ObraAdminAuditRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Desativar / Reativar an obra (`obras-ativacao-exclusao` RF-05). Only a
 * `manage-obras` actor (Gestão or Suprimentos) may call it (RF-09).
 *
 * Inside one transaction the obra is re-read `FOR UPDATE`; when a
 * concurrent request already deleted it, `ObraNotFoundException` is thrown
 * (RNF-01). Asking for the current value is a no-op: nothing is written and
 * no audit is recorded (RF-07). Otherwise only `obras.is_active` (and
 * `updated_at`) changes — Status, nome and responsável stay as they are
 * (RF-02) — and exactly one `obra_deactivated` / `obra_reactivated` record
 * with `{is_active}` before/after is appended in the same transaction
 * (RF-08, CT-02, RNF-02).
 *
 * Convites, associations and pedidos are never touched (RF-06): pending
 * convites of an inactive obra only stop being consumable while it stays
 * inactive.
 */
class SetObraActiveAction
{
    use GuardsObraAdministration;

    public function __construct(private readonly ObraAdminAuditRecorder $recorder) {}

    /**
     * @throws AuthorizationException
     * @throws ObraNotFoundException
     */
    public function execute(User $actor, Obra $obra, bool $active): Obra
    {
        $this->ensureActorManagesObras($actor);

        return DB::transaction(function () use ($actor, $obra, $active): Obra {
            $locked = Obra::query()->lockForUpdate()->find($obra->getKey());

            if ($locked === null) {
                throw ObraNotFoundException::make();
            }

            $wasActive = $locked->isActive();

            if ($wasActive === $active) {
                return $locked;
            }

            $locked->update(['is_active' => $active]);

            $this->recorder->record(
                $actor,
                $locked,
                $active ? ObraAdminAction::ObraReactivated : ObraAdminAction::ObraDeactivated,
                ['is_active' => $wasActive],
                ['is_active' => $active],
            );

            return $locked->fresh();
        });
    }
}
