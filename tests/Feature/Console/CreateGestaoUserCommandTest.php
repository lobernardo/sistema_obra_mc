<?php

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->gestaoRole = Role::query()->firstOrCreate(['slug' => RoleSlug::Gestao->value], ['name' => 'Gestão']);
    $this->suprimentosRole = Role::query()->firstOrCreate(['slug' => RoleSlug::Suprimentos->value], ['name' => 'Suprimentos']);

    putenv('GESTAO_BOOTSTRAP_PASSWORD');
    unset($_ENV['GESTAO_BOOTSTRAP_PASSWORD'], $_SERVER['GESTAO_BOOTSTRAP_PASSWORD']);
});

afterEach(function () {
    putenv('GESTAO_BOOTSTRAP_PASSWORD');
    unset($_ENV['GESTAO_BOOTSTRAP_PASSWORD'], $_SERVER['GESTAO_BOOTSTRAP_PASSWORD']);
});

test('creates an active, non-demo gestao user with the hashed runtime password and never echoes it (TC-18)', function () {
    $password = 'S3nh4-Inicial-'.fake()->uuid();

    $this->artisan('users:create-gestao', [
        '--name' => 'Leonardo',
        '--email' => 'owner@example.com',
        '--password' => $password,
    ])
        ->expectsOutputToContain('Usuário Gestão garantido: owner@example.com (criado)')
        ->doesntExpectOutputToContain($password)
        ->assertExitCode(0);

    $user = User::query()->where('email', 'owner@example.com')->sole();

    expect($user->name)->toBe('Leonardo');
    expect($user->role_id)->toBe($this->gestaoRole->id);
    expect($user->is_active)->toBeTrue();
    expect($user->is_demo)->toBeFalse();
    expect($user->password)->not->toBe($password);
    expect(Hash::check($password, $user->password))->toBeTrue();
});

test('running twice with the same e-mail is idempotent: 1 row, password untouched without the flag (TC-18)', function () {
    $password = 'S3nh4-Inicial-'.fake()->uuid();
    $secondPassword = 'Outra-Senha-'.fake()->uuid();

    $this->artisan('users:create-gestao', [
        '--name' => 'Leonardo',
        '--email' => 'owner@example.com',
        '--password' => $password,
    ])->assertExitCode(0);

    $originalHash = User::query()->where('email', 'owner@example.com')->value('password');

    $this->artisan('users:create-gestao', [
        '--email' => 'owner@example.com',
        '--password' => $secondPassword,
    ])
        ->expectsOutputToContain('Usuário Gestão garantido: owner@example.com (atualizado)')
        ->doesntExpectOutputToContain($secondPassword)
        ->assertExitCode(0);

    expect(User::query()->where('email', 'owner@example.com')->count())->toBe(1);
    expect(User::query()->whereHas('role', fn ($query) => $query->where('slug', RoleSlug::Gestao->value))->where('is_active', true)->where('is_demo', false)->count())->toBe(1);

    $user = User::query()->where('email', 'owner@example.com')->sole();

    expect($user->password)->toBe($originalHash);
    expect(Hash::check($password, $user->password))->toBeTrue();
    expect(Hash::check($secondPassword, $user->password))->toBeFalse();
    expect($user->name)->toBe('Leonardo');
});

test('--reset-password overwrites the password of an existing user (TC-18)', function () {
    $newPassword = 'Nova-Senha-'.fake()->uuid();
    $existing = User::factory()->suprimentos()->inactive()->create([
        'email' => 'owner@example.com',
        'name' => 'Antigo',
        'is_demo' => true,
        'password' => Hash::make('senha-antiga'),
    ]);

    $this->artisan('users:create-gestao', [
        '--email' => 'owner@example.com',
        '--password' => $newPassword,
        '--reset-password' => true,
    ])
        ->expectsOutputToContain('(atualizado)')
        ->doesntExpectOutputToContain($newPassword)
        ->assertExitCode(0);

    $user = $existing->fresh();

    expect(Hash::check($newPassword, $user->password))->toBeTrue();
    expect(Hash::check('senha-antiga', $user->password))->toBeFalse();
    expect($user->role_id)->toBe($this->gestaoRole->id);
    expect($user->is_active)->toBeTrue();
    expect($user->is_demo)->toBeFalse();
    expect($user->name)->toBe('Antigo');
    expect(User::query()->where('email', 'owner@example.com')->count())->toBe(1);
});

test('the password can come from GESTAO_BOOTSTRAP_PASSWORD at runtime when --password is absent', function () {
    $password = 'Env-Senha-'.fake()->uuid();
    putenv('GESTAO_BOOTSTRAP_PASSWORD='.$password);
    $_ENV['GESTAO_BOOTSTRAP_PASSWORD'] = $password;

    $this->artisan('users:create-gestao', [
        '--name' => 'Leonardo',
        '--email' => 'owner@example.com',
    ])
        ->doesntExpectOutputToContain($password)
        ->assertExitCode(0);

    $user = User::query()->where('email', 'owner@example.com')->sole();

    expect(Hash::check($password, $user->password))->toBeTrue();
});

test('fails without creating anything when the password is missing (TC-18)', function () {
    $this->artisan('users:create-gestao', [
        '--name' => 'Leonardo',
        '--email' => 'owner@example.com',
    ])
        ->expectsOutputToContain('GESTAO_BOOTSTRAP_PASSWORD')
        ->assertExitCode(2);

    expect(User::query()->where('email', 'owner@example.com')->exists())->toBeFalse();
});

test('fails without creating anything when the e-mail is missing or invalid (TC-18)', function (array $options) {
    $usersBefore = User::query()->count();

    $this->artisan('users:create-gestao', [
        '--name' => 'Leonardo',
        '--password' => 'qualquer-senha-'.fake()->uuid(),
        ...$options,
    ])
        ->expectsOutputToContain('e-mail válido')
        ->assertExitCode(2);

    expect(User::query()->count())->toBe($usersBefore);
})->with([
    'missing' => [[]],
    'invalid' => [['--email' => 'not-an-email']],
]);

test('fails without creating anything when a new user has no name', function () {
    $this->artisan('users:create-gestao', [
        '--email' => 'owner@example.com',
        '--password' => 'qualquer-senha-'.fake()->uuid(),
    ])
        ->expectsOutputToContain('--name=')
        ->assertExitCode(2);

    expect(User::query()->where('email', 'owner@example.com')->exists())->toBeFalse();
});

test('the command source contains no literal password value or default', function () {
    $source = file_get_contents(app_path('Console/Commands/CreateGestaoUser.php'));

    expect($source)->toContain('{--password= :');
    expect($source)->not->toMatch('/\{--password=[^\s:]/');
    expect($source)->not->toContain("'password' => '");
    expect($source)->not->toContain('password_hash(');
    expect($source)->not->toContain('Hash::make');
    expect($source)->not->toMatch('/\$password\s*=\s*[\'"]/');
});

test('the --email value is normalized, so two case variants converge on one row (RF-03)', function () {
    $password = 'S3nh4-Inicial-'.fake()->uuid();

    $this->artisan('users:create-gestao', [
        '--name' => 'Marcelo',
        '--email' => '  Gestor@Example.com ',
        '--password' => $password,
    ])
        ->expectsOutputToContain('Usuário Gestão garantido: gestor@example.com (criado)')
        ->assertExitCode(0);

    expect(User::query()->where('email', 'gestor@example.com')->count())->toBe(1);
    expect(User::query()->where('email', 'Gestor@Example.com')->exists())->toBeFalse();

    $this->artisan('users:create-gestao', [
        '--email' => 'gestor@example.com',
        '--password' => $password,
    ])
        ->expectsOutputToContain('Usuário Gestão garantido: gestor@example.com (atualizado)')
        ->doesntExpectOutputToContain('(criado)')
        ->assertExitCode(0);

    expect(User::query()->whereRaw('lower(email) = ?', ['gestor@example.com'])->count())->toBe(1);
    expect(User::query()->where('email', 'gestor@example.com')->sole()->name)->toBe('Marcelo');
});

test('a mixed-case second run updates instead of creating, in either order (RF-03)', function () {
    $password = 'S3nh4-Inicial-'.fake()->uuid();

    $this->artisan('users:create-gestao', [
        '--name' => 'Marcelo',
        '--email' => 'gestor@example.com',
        '--password' => $password,
    ])->assertExitCode(0);

    $this->artisan('users:create-gestao', [
        '--email' => 'GESTOR@EXAMPLE.COM',
        '--password' => $password,
    ])
        ->expectsOutputToContain('(atualizado)')
        ->assertExitCode(0);

    expect(User::query()->whereRaw('lower(email) = ?', ['gestor@example.com'])->count())->toBe(1);
});
