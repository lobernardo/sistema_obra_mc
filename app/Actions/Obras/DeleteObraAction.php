<?php

namespace App\Actions\Obras;

use App\Actions\Obras\Concerns\GuardsObraAdministration;
use App\Enums\ObraAdminAction;
use App\Enums\UserAdminAction;
use App\Exceptions\Obras\ObraNotFoundException;
use App\Models\Obra;
use App\Models\ObraInvitation;
use App\Models\Pedido;
use App\Models\User;
use App\Services\ObraAdminAuditRecorder;
use App\Services\UserAdminAuditRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Physically deletes an obra — the only `app/` path that does so, besides
 * `demo:reset` (`obras-ativacao-exclusao` RF-14, RF-26). Only a
 * `manage-obras` actor (Gestão or Suprimentos) may call it (RF-09).
 *
 * Inside one transaction the obra's convites are locked `FOR UPDATE` first
 * and the obra row second — the same order a convite acceptance takes, so
 * the two never deadlock (RNF-01) — and the blocking dependencies are
 * re-counted under those locks: any pedido of the obra (any `is_demo`, any
 * status) or any used convite blocks the deletion (RF-15). A vanished obra
 * throws `ObraNotFoundException`, so a second concurrent deletion never
 * writes a second `obra_deleted`.
 *
 * Blocked: nothing is deleted; one `obra_delete_blocked` record
 * `{pedidos_count, used_invitations_count}` is committed in its own
 * transaction (RF-21) and a 422 on `excluir` states both counts (RF-16).
 *
 * Allowed, all in the same transaction (RNF-02):
 * - the non-used convites (pending, expired, revoked) are deleted (RF-17);
 *   `account_registration_events` is never touched;
 * - `obra_deleted` with the 5-key snapshot is recorded while the row still
 *   exists (RF-20);
 * - the obra row is deleted: `obra_profile` cascades and the earlier
 *   `obra_admin_events` rows survive with `obra_id`/`obra_invitation_id`
 *   set to NULL by the database, keeping `subject_obra_id` (RF-19);
 * - one `obra_access_changed` per affected user is written with a single
 *   batched INSERT (RF-18, CT-03).
 *
 * `pedidos`, `pedido_events`, `internal_notifications`,
 * `pedido_attachments` and the `pedido_anexos` disk are never touched
 * (RF-22). The statement count is constant in the number of users and
 * convites (RNF-03). A deadlock (SQLSTATE `40P01`) inside the deletion
 * transaction rolls everything back and becomes a 422 on `excluir`, never
 * an HTTP 500 (Q-02).
 */
class DeleteObraAction
{
    use GuardsObraAdministration;

    public const CONCURRENT_CHANGE_MESSAGE = 'Não foi possível excluir a obra agora porque ela foi alterada ao mesmo tempo por outra operação. Tente novamente.';

    private const DEADLOCK_DETECTED = '40P01';

    public function __construct(
        private readonly ObraAdminAuditRecorder $obraRecorder,
        private readonly UserAdminAuditRecorder $userRecorder,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ObraNotFoundException
     * @throws ValidationException
     */
    public function execute(User $actor, Obra $obra): void
    {
        $this->ensureActorManagesObras($actor);

        $obraId = (int) $obra->getKey();

        try {
            $blocked = DB::transaction(fn (): ?array => $this->deleteUnlessBlocked($actor, $obraId));
        } catch (QueryException $exception) {
            if ($this->isDeadlock($exception)) {
                throw ValidationException::withMessages(['excluir' => self::CONCURRENT_CHANGE_MESSAGE]);
            }

            throw $exception;
        }

        if ($blocked === null) {
            return;
        }

        DB::transaction(fn () => $this->obraRecorder->record(
            $actor,
            $blocked['obra'],
            ObraAdminAction::ObraDeleteBlocked,
            null,
            ['pedidos_count' => $blocked['pedidos_count'], 'used_invitations_count' => $blocked['used_invitations_count']],
        ));

        throw ValidationException::withMessages([
            'excluir' => sprintf(
                'Não é possível excluir a obra «%s»: ela possui %d pedido(s) e %d convite(s) utilizado(s). Desative a obra para impedir novos usos.',
                $blocked['obra']->name,
                $blocked['pedidos_count'],
                $blocked['used_invitations_count'],
            ),
        ]);
    }

    /**
     * Runs inside the deletion transaction. Returns the blocking counts
     * without writing anything, or null once the obra is gone.
     *
     * @return array{obra: Obra, pedidos_count: int, used_invitations_count: int}|null
     */
    private function deleteUnlessBlocked(User $actor, int $obraId): ?array
    {
        $invitations = ObraInvitation::query()
            ->where('obra_id', $obraId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'used_at']);

        $obra = Obra::query()->lockForUpdate()->find($obraId);

        if ($obra === null) {
            throw ObraNotFoundException::make();
        }

        $pedidosCount = Pedido::query()->where('obra_id', $obraId)->count();
        $usedInvitationsCount = $invitations->whereNotNull('used_at')->count();

        if ($pedidosCount > 0 || $usedInvitationsCount > 0) {
            return ['obra' => $obra, 'pedidos_count' => $pedidosCount, 'used_invitations_count' => $usedInvitationsCount];
        }

        $accessChanges = $this->accessChanges($obraId);

        ObraInvitation::query()->where('obra_id', $obraId)->whereNull('used_at')->delete();

        $this->obraRecorder->record($actor, $obra, ObraAdminAction::ObraDeleted, $this->obraRecorder->deletionSnapshot($obra), null);

        $obra->delete();

        $this->userRecorder->recordMany($actor, UserAdminAction::ObraAccessChanged, $accessChanges);

        return null;
    }

    /**
     * Before/after `obra_ids` of every user associated to the obra, read
     * with one query over `obra_profile` (RF-18, RNF-03).
     *
     * @return list<array{target_id: int, before: array{obra_ids: list<int>}, after: array{obra_ids: list<int>}}>
     */
    private function accessChanges(int $obraId): array
    {
        $rows = DB::table('obra_profile')
            ->whereIn('user_id', fn ($query) => $query->select('user_id')->from('obra_profile')->where('obra_id', $obraId))
            ->orderBy('user_id')
            ->orderBy('obra_id')
            ->get(['user_id', 'obra_id']);

        $changes = [];

        foreach ($rows->groupBy('user_id') as $userId => $userRows) {
            $before = $userRows->pluck('obra_id')->map(fn ($id): int => (int) $id)->all();
            $after = array_values(array_filter($before, fn (int $id): bool => $id !== $obraId));

            $changes[] = [
                'target_id' => (int) $userId,
                'before' => ['obra_ids' => $before],
                'after' => ['obra_ids' => $after],
            ];
        }

        return $changes;
    }

    private function isDeadlock(QueryException $exception): bool
    {
        return (string) ($exception->errorInfo[0] ?? $exception->getCode()) === self::DEADLOCK_DETECTED;
    }
}
