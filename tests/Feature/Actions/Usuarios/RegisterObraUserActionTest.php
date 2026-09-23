<?php

use App\Actions\Usuarios\RegisterObraUserAction;
use App\Enums\AccountOrigin;
use App\Enums\RoleSlug;
use App\Models\AccountRegistrationEvent;
use App\Models\Obra;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

const REGISTER_ACTION_PASSWORD = 'senha-forte-123';

beforeEach(function () {
    $this->obraRole = Role::factory()->obra()->create();
    $this->gestaoRole = Role::factory()->gestao()->create();
    $this->action = app(RegisterObraUserAction::class);
});

/**
 * Runs `$callback` and returns the errors of the ValidationException it must
 * throw.
 *
 * @return array<string, list<string>>
 */
function registrationValidationErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        expect($exception->status)->toBe(422);

        return $exception->errors();
    }

    throw new RuntimeException('A ValidationException was expected.');
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registrationPayload(array $overrides = []): array
{
    return [
        'name' => 'Ana Souza',
        'email' => 'ana@example.com',
        'password' => REGISTER_ACTION_PASSWORD,
        'password_confirmation' => REGISTER_ACTION_PASSWORD,
        ...$overrides,
    ];
}

test('a valid submission creates one active, non-demo obra user with zero obras and a hashed password (RF-16)', function () {
    $user = $this->action->execute(registrationPayload(), '203.0.113.10');

    expect(User::query()->count())->toBe(1);
    expect($user->role->slug)->toBe(RoleSlug::Obra->value);
    expect($user->is_active)->toBeTrue();
    expect($user->is_demo)->toBeFalse();
    expect($user->name)->toBe('Ana Souza');
    expect($user->obras()->count())->toBe(0);
    expect(DB::table('obra_profile')->count())->toBe(0);
    expect($user->getRawOriginal('password'))->not->toBe(REGISTER_ACTION_PASSWORD);
    expect(Hash::check(REGISTER_ACTION_PASSWORD, $user->fresh()->password))->toBeTrue();
});

test('the e-mail is normalized before validation and persistence (RF-18)', function () {
    $user = $this->action->execute(registrationPayload(['email' => '  Ana@Example.COM ']), null);

    expect($user->fresh()->email)->toBe('ana@example.com');
});

test('a case variant of an existing e-mail is refused with the exact RF-20 text and creates no user', function () {
    $this->action->execute(registrationPayload(['email' => '  Ana@Example.COM ']), null);

    $errors = registrationValidationErrors(fn () => $this->action->execute(registrationPayload(['email' => 'ANA@example.com']), null));

    expect($errors['email'])->toBe(['Já existe uma conta com este e-mail. Entre ou use Esqueci minha senha.']);
    expect(User::query()->count())->toBe(1);
    expect(AccountRegistrationEvent::query()->count())->toBe(1);
});

test('forged papel, obras, is_active and is_demo are ignored (RF-17)', function () {
    $obra = Obra::factory()->create();

    $user = $this->action->execute(registrationPayload([
        'role_id' => $this->gestaoRole->id,
        'role' => RoleSlug::Gestao->value,
        'obra_ids' => [$obra->id],
        'obra_id' => $obra->id,
        'is_active' => false,
        'is_demo' => true,
    ]), null);

    $user->refresh();

    expect($user->role_id)->toBe($this->obraRole->id);
    expect($user->is_active)->toBeTrue();
    expect($user->is_demo)->toBeFalse();
    expect(DB::table('obra_profile')->count())->toBe(0);
});

test('validate returns only name, email and password', function () {
    $validated = $this->action->validate(registrationPayload(['role_id' => $this->gestaoRole->id, 'name' => '  Ana  ']));

    expect(array_keys($validated))->toEqualCanonicalizing(['name', 'email', 'password']);
    expect($validated['name'])->toBe('Ana');
});

test('each invalid field yields 422 on that field and no user', function (array $overrides, string $field) {
    $errors = registrationValidationErrors(fn () => $this->action->execute(registrationPayload($overrides), null));

    expect($errors)->toHaveKey($field);
    expect(User::query()->count())->toBe(0);
    expect(AccountRegistrationEvent::query()->count())->toBe(0);
})->with([
    'name missing' => [['name' => ''], 'name'],
    'name too long' => [['name' => str_repeat('a', 256)], 'name'],
    'email missing' => [['email' => ''], 'email'],
    'email invalid' => [['email' => 'nao-e-email'], 'email'],
    'email too long' => [['email' => str_repeat('a', 250).'@example.com'], 'email'],
    'password missing' => [['password' => '', 'password_confirmation' => ''], 'password'],
    'password too short' => [['password' => 'curta', 'password_confirmation' => 'curta'], 'password'],
    'password not confirmed' => [['password_confirmation' => 'outra-senha-456'], 'password'],
]);

/**
 * RF-18 race: `unique:users,email` passes, then a concurrent request commits
 * the same e-mail before our INSERT reaches `users_email_lower_unique`. The
 * rival row (and its own role, since the test's roles are uncommitted) is
 * written on a second autocommit connection right before our INSERT.
 */
test('a concurrent signup reaching the unique index surfaces as ValidationException, never 500', function () {
    config(['database.connections.pgsql_race' => config('database.connections.'.config('database.default'))]);
    $rival = DB::connection('pgsql_race');
    $injected = false;
    $rivalRoleId = null;

    DB::connection()->beforeExecuting(function (string $query) use (&$injected, &$rivalRoleId, $rival): void {
        if ($injected || ! str_starts_with($query, 'insert into "users"')) {
            return;
        }

        $injected = true;

        $rivalRoleId = $rival->table('roles')->insertGetId([
            'name' => 'Rival',
            'slug' => 'rival-race-role',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rival->table('users')->insert([
            'role_id' => $rivalRoleId,
            'name' => 'Rival',
            'email' => 'ana@example.com',
            'password' => 'x',
            'is_active' => true,
            'is_demo' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    try {
        $errors = registrationValidationErrors(fn () => $this->action->execute(registrationPayload(['email' => 'Ana@Example.com']), null));

        expect($injected)->toBeTrue();
        expect($errors['email'])->toBe(['Já existe uma conta com este e-mail. Entre ou use Esqueci minha senha.']);
        expect(User::query()->where('email', 'ana@example.com')->count())->toBe(1);
        expect(User::query()->count())->toBe(1);
        expect(AccountRegistrationEvent::query()->count())->toBe(0);
    } finally {
        $rival->table('users')->where('email', 'ana@example.com')->delete();
        $rival->table('roles')->where('slug', 'rival-race-role')->delete();
        DB::purge('pgsql_race');
    }
});

test('one signup writes exactly one novo_cadastro registration record without the password (RF-22)', function () {
    $user = $this->action->execute(registrationPayload(), '203.0.113.10');

    expect(AccountRegistrationEvent::query()->count())->toBe(1);

    $event = AccountRegistrationEvent::query()->sole();

    expect($event->user_id)->toBe($user->id);
    expect($event->origin)->toBe(AccountOrigin::NovoCadastro);
    expect($event->obra_invitation_id)->toBeNull();
    expect($event->ip)->toBe('203.0.113.10');
    expect($event->created_at)->not->toBeNull();

    $row = json_encode(DB::table('account_registration_events')->where('id', $event->id)->first(), JSON_THROW_ON_ERROR);

    expect($row)
        ->not->toContain(REGISTER_ACTION_PASSWORD)
        ->not->toContain(json_encode($user->getRawOriginal('password'), JSON_THROW_ON_ERROR))
        ->not->toContain('ana@example.com');
});

test('the recorded IP is truncated to 45 characters', function () {
    $this->action->execute(registrationPayload(), str_repeat('1', 60));

    expect(AccountRegistrationEvent::query()->sole()->ip)->toBe(str_repeat('1', 45));
});

test('a failing registration audit insert rolls back the user (RF-22)', function () {
    AccountRegistrationEvent::creating(function (): void {
        throw new RuntimeException('auditoria indisponível');
    });

    expect(fn () => $this->action->execute(registrationPayload(), null))->toThrow(RuntimeException::class);

    expect(User::query()->count())->toBe(0);
    expect(AccountRegistrationEvent::query()->count())->toBe(0);
});
