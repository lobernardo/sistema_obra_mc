<?php

namespace App\Actions\Usuarios;

use App\Enums\AccountOrigin;
use App\Enums\RoleSlug;
use App\Models\AccountRegistrationEvent;
use App\Models\ObraInvitation;
use App\Models\Role;
use App\Models\User;
use App\Support\EmailNormalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Account-creation core shared by the public Novo Cadastro (RF-16) and the
 * convite new-account path (RF-29).
 *
 * Only `name`, `email`, `password` and `password_confirmation` are ever read
 * (RF-17): papel, obras, `is_active` and `is_demo` are fixed here and never
 * taken from input. The e-mail is canonicalized by `EmailNormalizer` before
 * validation (RF-18), and a duplicate — caught by `unique:users,email` or,
 * under a race, by the `users_email_lower_unique` index — surfaces as the
 * RF-20 message on `email`, never as HTTP 500.
 *
 * The user insert and its `account_registration_events` row (RF-22) commit
 * or roll back together. The Action never authenticates: the calling
 * component owns the session (RF-21).
 */
class RegisterObraUserAction
{
    public const DUPLICATE_EMAIL_MESSAGE = 'Já existe uma conta com este e-mail. Entre ou use Esqueci minha senha.';

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, email: string, password: string}
     */
    public function validate(array $data): array
    {
        $data = Arr::only($data, ['name', 'email', 'password', 'password_confirmation']);

        if (is_string($data['name'] ?? null)) {
            $data['name'] = trim($data['name']);
        }

        if (is_string($data['email'] ?? null)) {
            $data['email'] = EmailNormalizer::normalize($data['email']);
        }

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ], self::messages())->validate();

        return Arr::only($validated, ['name', 'email', 'password']);
    }

    /**
     * Creates the obra user and its registration audit row. Must run inside
     * the caller's transaction so both commit or roll back together.
     *
     * @param  array{name: string, email: string, password: string}  $validated
     */
    public function createInsideTransaction(array $validated, AccountOrigin $origin, ?ObraInvitation $invitation, ?string $ip): User
    {
        $role = Role::query()->where('slug', RoleSlug::Obra->value)->firstOrFail();

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role_id' => $role->id,
            'is_active' => true,
            'is_demo' => false,
        ]);

        AccountRegistrationEvent::query()->create([
            'user_id' => $user->id,
            'origin' => $origin,
            'obra_invitation_id' => $invitation?->id,
            'ip' => $ip === null ? null : mb_substr($ip, 0, 45),
        ]);

        return $user;
    }

    /**
     * Novo Cadastro entry point (RF-16).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function execute(array $data, ?string $ip): User
    {
        $validated = $this->validate($data);

        try {
            return DB::transaction(fn (): User => $this->createInsideTransaction($validated, AccountOrigin::NovoCadastro, null, $ip));
        } catch (UniqueConstraintViolationException) {
            throw self::duplicateEmailException();
        }
    }

    /**
     * The RF-20 error, also raised by the convite path when the unique
     * index wins a race.
     */
    public static function duplicateEmailException(): ValidationException
    {
        return ValidationException::withMessages(['email' => self::DUPLICATE_EMAIL_MESSAGE]);
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Informe o nome.',
            'name.max' => 'O nome deve ter no máximo :max caracteres.',
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'email.max' => 'O e-mail deve ter no máximo :max caracteres.',
            'email.unique' => self::DUPLICATE_EMAIL_MESSAGE,
            'password.required' => 'Informe a senha.',
            'password.confirmed' => 'A confirmação não confere com a senha.',
            'password.min' => 'A senha deve ter pelo menos :min caracteres.',
        ];
    }
}
