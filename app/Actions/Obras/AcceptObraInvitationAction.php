<?php

namespace App\Actions\Obras;

use App\Actions\Usuarios\RegisterObraUserAction;
use App\Enums\AccountOrigin;
use App\Enums\ObraAdminAction;
use App\Enums\RoleSlug;
use App\Enums\UserAdminAction;
use App\Exceptions\ObraInvitations\ObraInvitationUnavailableException;
use App\Models\ObraInvitation;
use App\Models\User;
use App\Services\ObraAdminAuditRecorder;
use App\Services\UserAdminAuditRecorder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Resolves and consumes a convite (RF-27..RF-34).
 *
 * The plaintext token is only ever seen by `resolveByToken()`, which checks
 * its format, hashes it once and forgets it; everything downstream works on
 * the convite **id** (RF-38). Every invalid cause raises the same
 * context-free `ObraInvitationUnavailableException` (RF-28).
 *
 * Atomicity (RF-32, RNF-02): inside each transaction the **first**
 * statement is the conditional consumption
 * `UPDATE ... WHERE id = ? AND <consumable>`, which re-checks RF-27 in SQL.
 * Zero affected rows throw, so the transaction rolls back and no user,
 * association or audit row is left behind. Under PostgreSQL's row lock a
 * concurrent consumer (or revocation) waits and then matches 0 rows, so
 * exactly one of them wins.
 */
class AcceptObraInvitationAction
{
    public const ROLE_MISMATCH_MESSAGE = 'Convites de obra só se aplicam a contas do perfil Obra.';

    public function __construct(
        private readonly RegisterObraUserAction $registerObraUser,
        private readonly ObraAdminAuditRecorder $obraRecorder,
        private readonly UserAdminAuditRecorder $userRecorder,
    ) {}

    /**
     * @throws ObraInvitationUnavailableException
     */
    public function resolveByToken(#[\SensitiveParameter] mixed $token): ObraInvitation
    {
        if (! is_string($token) || preg_match('/^[0-9a-f]{64}$/D', $token) !== 1) {
            throw ObraInvitationUnavailableException::make();
        }

        $tokenHash = ObraInvitation::hashToken($token);

        $invitation = ObraInvitation::query()->with('obra')->where('token_hash', $tokenHash)->first();

        if ($invitation === null || ! $invitation->isConsumable()) {
            throw ObraInvitationUnavailableException::make();
        }

        return $invitation;
    }

    /**
     * Re-resolution by id, used by the post-login return (RF-30) and before
     * rendering the confirmation state.
     *
     * @throws ObraInvitationUnavailableException
     */
    public function resolveById(int $invitationId): ObraInvitation
    {
        $invitation = ObraInvitation::query()->with('obra')->find($invitationId);

        if ($invitation === null || ! $invitation->isConsumable()) {
            throw ObraInvitationUnavailableException::make();
        }

        return $invitation;
    }

    /**
     * New-account path (RF-29): creates an `obra` user bound to the
     * convite's obra — never to an obra taken from `$data`.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     * @throws ObraInvitationUnavailableException
     */
    public function acceptAsNewAccount(int $invitationId, array $data, ?string $ip): User
    {
        $validated = $this->registerObraUser->validate($data);

        try {
            return DB::transaction(function () use ($invitationId, $validated, $ip): User {
                $now = now();

                $this->consume($invitationId, ['used_at' => $now]);

                $invitation = ObraInvitation::query()->with('obra')->findOrFail($invitationId);

                $user = $this->registerObraUser->createInsideTransaction($validated, AccountOrigin::Convite, $invitation, $ip);

                ObraInvitation::query()->whereKey($invitationId)->update(['used_by' => $user->getKey()]);

                $this->associate($user, $invitation);

                $this->obraRecorder->record($user, $invitation->obra, ObraAdminAction::InvitationUsed, null, null, $invitation);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw RegisterObraUserAction::duplicateEmailException();
        }
    }

    /**
     * Existing-account path (RF-30, RF-31): only `obra` accounts may accept;
     * the association is added only when absent.
     *
     * @return array{user: User, associated: bool}
     *
     * @throws ValidationException
     * @throws ObraInvitationUnavailableException
     */
    public function acceptAsExistingAccount(User $user, int $invitationId): array
    {
        if ($user->role?->slug !== RoleSlug::Obra->value) {
            throw ValidationException::withMessages([
                'invitation' => self::ROLE_MISMATCH_MESSAGE,
            ]);
        }

        return DB::transaction(function () use ($user, $invitationId): array {
            $this->consume($invitationId, [
                'used_at' => now(),
                'used_by' => $user->getKey(),
            ]);

            $invitation = ObraInvitation::query()->with('obra')->findOrFail($invitationId);

            $associated = ! $user->obras()->whereKey($invitation->obra_id)->exists();

            if ($associated) {
                $this->associate($user, $invitation);
            }

            $this->obraRecorder->record($user, $invitation->obra, ObraAdminAction::InvitationUsed, null, null, $invitation);

            return ['user' => $user->fresh(), 'associated' => $associated];
        });
    }

    /**
     * The conditional consumption: must be the first statement of the
     * caller's transaction.
     *
     * @param  array<string, mixed>  $values
     *
     * @throws ObraInvitationUnavailableException
     */
    private function consume(int $invitationId, array $values): void
    {
        $affected = ObraInvitation::query()
            ->whereKey($invitationId)
            ->consumable()
            ->update($values);

        if ($affected === 0) {
            throw ObraInvitationUnavailableException::make();
        }
    }

    /**
     * Attaches the convite's obra and records `obra_access_changed` with the
     * accepting user as actor and target (RF-12).
     */
    private function associate(User $user, ObraInvitation $invitation): void
    {
        $before = $this->userRecorder->snapshot($user)['obra_ids'];

        $user->obras()->attach($invitation->obra_id);

        $after = $this->userRecorder->snapshot($user)['obra_ids'];

        $this->userRecorder->record(
            $user,
            $user,
            UserAdminAction::ObraAccessChanged,
            ['obra_ids' => $before],
            ['obra_ids' => $after],
        );
    }
}
