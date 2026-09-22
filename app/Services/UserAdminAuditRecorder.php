<?php

namespace App\Services;

use App\Enums\UserAdminAction;
use App\Models\User;
use App\Models\UserAdminEvent;
use LogicException;

/**
 * Single writer of the administrative audit trail (`user_admin_events`,
 * RF-19..RF-22). `before`/`after` are built exclusively from the explicit
 * whitelist below — never from a model, request or attribute bag (RF-21) —
 * so `password`, `remember_token`, tokens, session ids or cookies can never
 * reach the trail (RF-22). Atomicity with the mutation is the caller's
 * responsibility: state-mutating Actions call `record()` inside their own
 * `DB::transaction`; the two `access_link_*` slugs are recorded outside
 * any transaction (D-03, RNF-10).
 */
final class UserAdminAuditRecorder
{
    /** @var list<string> */
    public const WHITELIST = ['name', 'email', 'role', 'is_active', 'obra_ids'];

    /**
     * Appends one immutable record. Throws before touching the database
     * when any key of `before`/`after` is outside the whitelist (RF-21).
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(User $actor, User $target, UserAdminAction $action, ?array $before, ?array $after): UserAdminEvent
    {
        $this->ensureWhitelisted($before, 'before');
        $this->ensureWhitelisted($after, 'after');

        return UserAdminEvent::query()->create([
            'actor_id' => $actor->getKey(),
            'target_id' => $target->getKey(),
            'action' => $action,
            'before' => $before,
            'after' => $after,
        ]);
    }

    /**
     * Whitelisted projection of a user's administrative state (RF-21):
     * `role` is the slug (never `role_id` alone) and `obra_ids` is a sorted
     * list of integers read from `obra_profile`.
     *
     * @return array{name: string, email: string, role: string|null, is_active: bool, obra_ids: list<int>}
     */
    public function snapshot(User $user): array
    {
        $obraIds = $user->obras()->pluck('obras.id')->map(fn ($id): int => (int) $id)->all();
        sort($obraIds);

        return [
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'role' => $user->role?->slug,
            'is_active' => (bool) $user->is_active,
            'obra_ids' => $obraIds,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function ensureWhitelisted(?array $payload, string $side): void
    {
        if ($payload === null) {
            return;
        }

        $unexpected = array_diff(array_keys($payload), self::WHITELIST);

        if ($unexpected !== []) {
            throw new LogicException(sprintf(
                'Chave(s) fora da whitelist de auditoria administrativa em "%s": %s.',
                $side,
                implode(', ', $unexpected),
            ));
        }
    }
}
