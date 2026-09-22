<?php

namespace App\Console\Commands;

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use App\Support\EmailNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Bootstraps the first real Gestão account (RF-32, CT-08). Idempotent
 * upsert keyed by e-mail: a new user is created with role `gestao`,
 * `is_active = true`, `is_demo = false` and the runtime-supplied password
 * (hashed by the model cast); an existing user is re-asserted as an active
 * non-demo gestao and keeps its password unless `--reset-password` is
 * passed. The password comes only from `--password=` or, when absent, from
 * the `GESTAO_BOOTSTRAP_PASSWORD` environment variable read at execution
 * time — there is no default in code, and it is never echoed.
 *
 * The `--email=` value is canonicalized through `EmailNormalizer` before
 * anything else (RF-03), so the lookup, the persisted column and the
 * reported address are the same value and two runs differing only in case
 * update one row instead of creating a second account.
 */
class CreateGestaoUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:create-gestao
        {--name= : Nome do usuário (obrigatório apenas ao criar)}
        {--email= : E-mail do usuário Gestão}
        {--password= : Senha inicial (ou defina GESTAO_BOOTSTRAP_PASSWORD no ambiente)}
        {--reset-password : Sobrescreve a senha de um usuário já existente}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Garante um usuário Gestão ativo (cria ou atualiza pelo e-mail), sem senha padrão no código.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $emailOption = $this->option('email');
        $email = is_string($emailOption) ? EmailNormalizer::normalize($emailOption) : $emailOption;
        $name = $this->option('name');
        $password = $this->resolvePassword();

        $validator = Validator::make(['email' => $email], [
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            $this->error('Informe um e-mail válido em --email=.');
            $this->printUsage();

            return self::INVALID;
        }

        if ($password === null || $password === '') {
            $this->error('Informe a senha em --password= ou na variável de ambiente GESTAO_BOOTSTRAP_PASSWORD.');
            $this->printUsage();

            return self::INVALID;
        }

        $gestaoRoleId = Role::query()->where('slug', RoleSlug::Gestao->value)->value('id');

        if ($gestaoRoleId === null) {
            $this->error('O perfil "gestao" não existe na tabela roles. Execute as migrations/seeders antes.');

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);

        if (! $user->exists && ($name === null || trim((string) $name) === '')) {
            $this->error('Informe o nome em --name= para criar um novo usuário.');
            $this->printUsage();

            return self::INVALID;
        }

        $created = ! $user->exists;

        DB::transaction(function () use ($user, $name, $password, $gestaoRoleId, $created): void {
            $user->role_id = $gestaoRoleId;
            $user->is_active = true;
            $user->is_demo = false;

            if ($name !== null && trim((string) $name) !== '') {
                $user->name = $name;
            }

            if ($created || $this->option('reset-password')) {
                $user->password = $password;
            }

            $user->save();
        });

        $this->info(sprintf('Usuário Gestão garantido: %s (%s)', $email, $created ? 'criado' : 'atualizado'));

        return self::SUCCESS;
    }

    /**
     * The password is never defaulted in code: `--password=` wins, otherwise
     * the runtime environment variable is consulted (read here, never in
     * `config/`, so it is not cached with the configuration).
     */
    private function resolvePassword(): ?string
    {
        $option = $this->option('password');

        if (is_string($option) && $option !== '') {
            return $option;
        }

        $fromEnvironment = env('GESTAO_BOOTSTRAP_PASSWORD');

        return is_string($fromEnvironment) && $fromEnvironment !== '' ? $fromEnvironment : null;
    }

    private function printUsage(): void
    {
        $this->line('Uso: php artisan users:create-gestao --name="Nome" --email=email@dominio --password=... [--reset-password]');
    }
}
