<?php

namespace App\Actions\Usuarios;

use App\Actions\Obras\Concerns\GuardsObraAdministration;
use App\Actions\Usuarios\Concerns\GuardsObraAssociationTarget;
use App\Enums\UserAdminAction;
use App\Models\Obra;
use App\Models\User;
use App\Services\UserAdminAuditRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Removes one obra association of a user (RF-13, CT-06).
 *
 * Only a `manage-obras` actor (Gestão or Suprimentos) may call it — not
 * `manage-users` (RF-07) — and only an `obra` or `suprimentos` target is
 * accepted (RF-11, NC-03). An obra that is not associated → PT-BR 422.
 * Otherwise only that `obra_profile` row is deleted, together with one
 * `obra_access_changed` audit (RF-12) in the same transaction; no `pedidos`
 * or `pedido_events` row is touched, including pedidos the user requested
 * in that obra.
 *
 * Self-association is intended (RF-11 v1.3, F-13): a Suprimentos actor may
 * edit its own associations or those of another Suprimentos user — they
 * grant creation eligibility in slice 2 (`solicitacao-historico-finalizacao`
 * RF-02) — so `actor == target` is never blocked here.
 */
class DetachUserObraAction
{
    use GuardsObraAdministration, GuardsObraAssociationTarget;

    public function __construct(private readonly UserAdminAuditRecorder $recorder) {}

    /**
     * @param  array{obra_id?: mixed}  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, User $target, array $data): User
    {
        $this->ensureActorManagesObras($actor);
        $this->ensureTargetAcceptsObras($target);

        $validated = Validator::make($data, [
            'obra_id' => ['required', 'integer', 'exists:obras,id'],
        ], [
            'obra_id.required' => 'Selecione a obra.',
            'obra_id.integer' => 'Obra inválida.',
            'obra_id.exists' => 'Obra inválida.',
        ])->validate();

        $obraId = (int) $validated['obra_id'];

        if (! $target->obras()->whereKey($obraId)->exists()) {
            throw ValidationException::withMessages([
                'obra_id' => sprintf('A obra «%s» não está associada a este usuário.', Obra::query()->whereKey($obraId)->value('name')),
            ]);
        }

        DB::transaction(function () use ($actor, $target, $obraId): void {
            $before = $this->recorder->snapshot($target)['obra_ids'];

            $target->obras()->detach($obraId);

            $after = $this->recorder->snapshot($target)['obra_ids'];

            $this->recorder->record(
                $actor,
                $target,
                UserAdminAction::ObraAccessChanged,
                ['obra_ids' => $before],
                ['obra_ids' => $after],
            );
        });

        return $target->fresh();
    }
}
