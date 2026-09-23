<?php

namespace App\Actions\Usuarios\Concerns;

use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Shared target guard for the association Actions (RF-11, NC-03): only
 * users of papel `obra` or `suprimentos` may hold `obra_profile` rows.
 */
trait GuardsObraAssociationTarget
{
    /**
     * @throws ValidationException
     */
    private function ensureTargetAcceptsObras(User $target): void
    {
        if (! in_array($target->role?->slug, [RoleSlug::Obra->value, RoleSlug::Suprimentos->value], true)) {
            throw ValidationException::withMessages([
                'user_id' => 'Somente usuários dos perfis Obra e Suprimentos podem ter obras associadas.',
            ]);
        }
    }
}
