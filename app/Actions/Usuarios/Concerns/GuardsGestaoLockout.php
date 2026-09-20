<?php

namespace App\Actions\Usuarios\Concerns;

use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Lockout guards (RF-30): a Gestão actor may neither deactivate nor change
 * the papel of their own account, and no operation may leave the system
 * without at least one active `gestao` user. Surfaced as a
 * `ValidationException` so Livewire forms show the PT-BR message inline.
 */
trait GuardsGestaoLockout
{
    /**
     * @throws ValidationException
     */
    private function ensureNotSelf(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw ValidationException::withMessages([
                'target' => 'Você não pode desativar nem alterar o perfil da própria conta.',
            ]);
        }
    }

    /**
     * Refuses when `$target` is currently an active gestao and no other
     * active gestao exists — i.e. the operation would remove the last one.
     *
     * @throws ValidationException
     */
    private function ensureAnotherActiveGestaoRemains(User $target): void
    {
        if (! $this->isActiveGestao($target)) {
            return;
        }

        $anotherActiveGestaoExists = User::query()
            ->whereHas('role', fn (Builder $query) => $query->where('slug', RoleSlug::Gestao->value))
            ->where('is_active', true)
            ->whereKeyNot($target->getKey())
            ->exists();

        if (! $anotherActiveGestaoExists) {
            throw ValidationException::withMessages([
                'target' => 'É necessário manter pelo menos um usuário Gestão ativo.',
            ]);
        }
    }

    private function isActiveGestao(User $user): bool
    {
        return $user->is_active === true
            && $user->role?->slug === RoleSlug::Gestao->value;
    }
}
