<?php

namespace App\Actions\Usuarios;

use App\Actions\Usuarios\Concerns\GuardsUserAdministration;
use App\Enums\RoleSlug;
use App\Enums\UserAdminAction;
use App\Models\Role;
use App\Models\User;
use App\Services\UserAdminAuditRecorder;
use App\Support\EmailNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Creates a user on behalf of a `manage-users` actor (RF-06, RF-07).
 *
 * The password is a random 32-character string hashed by the model cast —
 * never a shared default, never shown (RF-18, RNF-02); the user only gains
 * access through the first-access invite. `obra` and `suprimentos` users
 * accept 0..N obras; `gestao` (and any other papel) may not carry obras
 * (RF-11, RF-13b, NC-03). The insert and the
 * `obra_profile` sync are committed in one transaction; only after that
 * commit is the invite dispatched (RF-29, RNF-08), so a rolled-back user
 * never receives an invite and a transport failure never rolls back the
 * user — it is reported and surfaced as `invite_sent = false`, with the
 * Gestão resend (RF-14) as the recovery path.
 *
 * Audit (RF-19, RF-20): a `user_created` record (`before = null`, `after`
 * = whitelisted snapshot) is written inside the same transaction as the
 * insert, so user and audit commit or roll back together (RNF-10). The
 * `access_link_sent` record belongs to `SendAccessLinkAction` and is
 * written outside any transaction (D-03); a failure of that record is
 * caught here like any other invite failure and becomes
 * `invite_sent = false`.
 */
class CreateUserAction
{
    use GuardsUserAdministration;

    public function __construct(
        private readonly SendAccessLinkAction $sendAccessLink,
        private readonly UserAdminAuditRecorder $recorder,
    ) {}

    /**
     * @param  array{name?: mixed, email?: mixed, role_id?: mixed, obra_ids?: mixed}  $data
     * @return array{user: User, invite_sent: bool}
     */
    public function execute(User $actor, array $data): array
    {
        $this->ensureActorManagesUsers($actor);

        $data = self::withNormalizedEmail($data);

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            ...$this->obraIdsRules($data['role_id'] ?? null),
        ], self::messages())->validate();

        $obraIds = $validated['obra_ids'] ?? [];

        $user = DB::transaction(function () use ($actor, $validated, $obraIds): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Str::password(32),
                'role_id' => $validated['role_id'],
                'is_active' => true,
                'is_demo' => false,
            ]);

            $user->obras()->sync($obraIds);

            $this->recorder->record($actor, $user, UserAdminAction::UserCreated, null, $this->recorder->snapshot($user));

            return $user;
        });

        return ['user' => $user, 'invite_sent' => $this->sendInviteAfterCommit($actor, $user)];
    }

    /**
     * Post-commit dispatch (RF-29): any failure is reported, never thrown.
     * The default `resend: false` makes the Action record `access_link_sent`
     * (RF-20); a recorder failure on that path is treated exactly like a
     * transport failure — reported and surfaced as `invite_sent = false`
     * (D-03).
     */
    private function sendInviteAfterCommit(User $actor, User $user): bool
    {
        try {
            return $this->sendAccessLink->execute($actor, $user, resend: false) === Password::RESET_LINK_SENT;
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * Canonicalizes `email` before validation (RF-02) so `unique:users,email`
     * and `Rule::unique(...)->ignore(...)` are evaluated on — and the column
     * is written with — the normalized value. A non-string `email` is left
     * untouched so the `required`/`string` rules still produce their PT-BR
     * message instead of a type error.
     *
     * @param  array{name?: mixed, email?: mixed, role_id?: mixed, obra_ids?: mixed}  $data
     * @return array{name?: mixed, email?: mixed, role_id?: mixed, obra_ids?: mixed}
     */
    public static function withNormalizedEmail(array $data): array
    {
        if (is_string($data['email'] ?? null)) {
            $data['email'] = EmailNormalizer::normalize($data['email']);
        }

        return $data;
    }

    /**
     * `obra_ids` is optional (0..N) for the `obra` and `suprimentos` papéis
     * and prohibited for `gestao` and any other papel (RF-11, RF-13b,
     * NC-03). An absent key means "keep the current associations" on update
     * (RF-11b); an empty array for `gestao` passes `prohibited`.
     *
     * @return array<string, list<string>>
     */
    public static function obraIdsRules(mixed $roleId): array
    {
        if (self::roleAcceptsObras($roleId)) {
            return [
                'obra_ids' => ['sometimes', 'array'],
                'obra_ids.*' => ['integer', 'distinct', 'exists:obras,id'],
            ];
        }

        return [
            'obra_ids' => ['prohibited'],
        ];
    }

    /**
     * Whether the papel identified by `$roleId` may hold `obra_profile`
     * rows: only `obra` and `suprimentos` (RF-11, NC-03).
     */
    public static function roleAcceptsObras(mixed $roleId): bool
    {
        return is_numeric($roleId) && Role::query()
            ->whereKey((int) $roleId)
            ->whereIn('slug', [RoleSlug::Obra->value, RoleSlug::Suprimentos->value])
            ->exists();
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Informe o nome.',
            'name.string' => 'Nome inválido.',
            'name.max' => 'O nome deve ter no máximo 255 caracteres.',
            'email.required' => 'Informe o e-mail.',
            'email.string' => 'E-mail inválido.',
            'email.email' => 'Informe um e-mail válido.',
            'email.max' => 'O e-mail deve ter no máximo 255 caracteres.',
            'email.unique' => 'Já existe um usuário com este e-mail.',
            'role_id.required' => 'Selecione o perfil.',
            'role_id.integer' => 'Perfil inválido.',
            'role_id.exists' => 'Perfil inválido.',
            'obra_ids.array' => 'Obras inválidas.',
            'obra_ids.prohibited' => 'O perfil Gestão não pode ser associado a obras.',
            'obra_ids.*.integer' => 'Obra inválida.',
            'obra_ids.*.distinct' => 'Obra repetida.',
            'obra_ids.*.exists' => 'Obra inválida.',
        ];
    }
}
