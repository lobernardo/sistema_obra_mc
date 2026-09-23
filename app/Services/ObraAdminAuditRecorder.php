<?php

namespace App\Services;

use App\Enums\ObraAdminAction;
use App\Models\Obra;
use App\Models\ObraAdminEvent;
use App\Models\ObraInvitation;
use App\Models\User;
use LogicException;

/**
 * Single writer of the obra/convite audit trail (`obra_admin_events`,
 * CT-07 b, RF-02, RF-34). `before`/`after` are built exclusively from the
 * explicit whitelist below — never from a model, request or attribute bag —
 * so a convite token or its hash can never reach the trail (RF-38).
 * Atomicity with the mutation is the caller's responsibility: Actions call
 * `record()` inside their own `DB::transaction`.
 */
final class ObraAdminAuditRecorder
{
    /** @var list<string> */
    public const WHITELIST = ['name', 'responsavel', 'status'];

    /**
     * Appends one immutable record. Throws before touching the database
     * when any key of `before`/`after` is outside the whitelist.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(User $actor, Obra $obra, ObraAdminAction $action, ?array $before, ?array $after, ?ObraInvitation $invitation = null): ObraAdminEvent
    {
        $this->ensureWhitelisted($before, 'before');
        $this->ensureWhitelisted($after, 'after');

        return ObraAdminEvent::query()->create([
            'actor_id' => $actor->getKey(),
            'obra_id' => $obra->getKey(),
            'obra_invitation_id' => $invitation?->getKey(),
            'action' => $action,
            'before' => $before,
            'after' => $after,
        ]);
    }

    /**
     * Whitelisted projection of an obra: `status` is the slug.
     *
     * @return array{name: string, responsavel: string|null, status: string}
     */
    public function snapshot(Obra $obra): array
    {
        return [
            'name' => (string) $obra->name,
            'responsavel' => $obra->responsavel,
            'status' => $obra->status->value,
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
                'Chave(s) fora da whitelist de auditoria de obras em "%s": %s.',
                $side,
                implode(', ', $unexpected),
            ));
        }
    }
}
