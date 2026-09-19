<?php

namespace App\Rules;

use App\Enums\RoleSlug;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Restricts a `responsible_id` value to a user holding the `suprimentos`
 * role, validated server-side regardless of what the UI's selector offers
 * (RF-14b).
 */
class ResponsibleMustBeSuprimentos implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = User::query()->with('role')->find($value);

        if (! $user || $user->role?->slug !== RoleSlug::Suprimentos->value) {
            $fail('O responsável selecionado precisa ter o perfil "suprimentos".');
        }
    }
}
