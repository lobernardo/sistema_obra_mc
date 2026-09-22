<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RF-07 / CT-04: `users:email-case-report` inspects both `users` and
 * `password_reset_tokens`, names each table, prints the colliding addresses
 * and the affected row count, exits `0` only when neither table needs
 * normalization, and never writes — not to the database and not to a log
 * channel (RF-10).
 *
 * Output is captured through `Artisan::call()` + `Artisan::output()` rather
 * than `expectsOutputToContain()`: the report deliberately prints an address
 * and its normalized form on the same line, and the Mockery-based helper
 * lets the first registered substring absorb every line that also matches a
 * later one, which would silently stop asserting the second address.
 *
 * A `users` collision can only be planted with `users_email_lower_unique`
 * temporarily dropped — the Fase 0 migration is already applied to the test
 * database and the index is exactly what makes such a row impossible from now
 * on. The diagnostic exists for the legacy data that predates the index, which
 * is what these cases reproduce.
 */

/**
 * Runs the diagnostic and returns its exit code together with everything it
 * printed.
 *
 * @return array{code: int, output: string}
 */
function runEmailCaseReport(): array
{
    $code = Artisan::call('users:email-case-report');

    return ['code' => $code, 'output' => Artisan::output()];
}

/**
 * Full snapshot of the two inspected tables, used to prove the command is
 * read-only.
 *
 * @return array{users: list<array<string, mixed>>, password_reset_tokens: list<array<string, mixed>>}
 */
function emailCaseReportSnapshot(): array
{
    return [
        'users' => array_map(
            fn (object $row): array => (array) $row,
            DB::select('select id, email, name, is_active, role_id from users order by id'),
        ),
        'password_reset_tokens' => array_map(
            fn (object $row): array => (array) $row,
            DB::select('select email, token, created_at from password_reset_tokens order by email'),
        ),
    ];
}

/**
 * Inserts a token row directly, bypassing the broker, so the stored casing
 * is exactly what the test plants.
 */
function plantResetToken(string $email): void
{
    DB::table('password_reset_tokens')->insert([
        'email' => $email,
        'token' => hash('sha256', 'token-'.$email),
        'created_at' => now(),
    ]);
}

/**
 * Rewrites a user e-mail through the query builder, bypassing the Action and
 * its normalization, to plant a pre-Fase-0 row.
 */
function plantUserEmail(User $user, string $email): void
{
    DB::table('users')->where('id', $user->getKey())->update(['email' => $email]);
}

/**
 * Drops the functional unique index for the duration of one case, so a
 * pre-Fase-0 collision can be planted in `users`.
 */
function withoutEmailLowerUniqueIndex(Closure $callback): void
{
    DB::statement('drop index if exists users_email_lower_unique');

    $callback();
}

beforeEach(function () {
    Log::spy();
});

test('a clean dataset exits 0 and reports no collision (RF-07, CT-04)', function () {
    User::factory()->gestao()->create(['email' => 'gestora@example.com']);
    User::factory()->obra()->create(['email' => 'obra@example.com']);
    plantResetToken('obra@example.com');

    $before = emailCaseReportSnapshot();

    ['code' => $code, 'output' => $output] = runEmailCaseReport();

    expect($code)->toBe(0);
    expect($output)
        ->toContain('Nenhuma colisão encontrada.')
        ->toContain('Nenhuma linha precisa de normalização.')
        ->toContain('Tabela: users')
        ->toContain('Tabela: password_reset_tokens');

    expect(emailCaseReportSnapshot())->toBe($before);
});

test('a collision planted in users exits non-zero, prints both addresses, names the table and the row count (RF-07)', function () {
    $first = User::factory()->obra()->create(['email' => 'marcelo@example.com']);
    $second = User::factory()->obra()->create(['email' => 'outro@example.com']);

    withoutEmailLowerUniqueIndex(fn () => plantUserEmail($second, 'MARCELO@example.com'));

    $before = emailCaseReportSnapshot();

    ['code' => $code, 'output' => $output] = runEmailCaseReport();

    expect($code)->not->toBe(0);
    expect($output)
        ->toContain('Tabela: users')
        ->toContain('marcelo@example.com')
        ->toContain('MARCELO@example.com')
        ->toContain('users: 1 grupo(s) colidem sob lower(email).')
        ->not->toContain('Nenhuma colisão encontrada.');

    expect(emailCaseReportSnapshot())->toBe($before);
    expect(DB::table('users')->where('id', $first->getKey())->value('email'))->toBe('marcelo@example.com');
    expect(DB::table('users')->where('id', $second->getKey())->value('email'))->toBe('MARCELO@example.com');
});

test('a collision planted only in password_reset_tokens exits non-zero and names that table (RF-07)', function () {
    User::factory()->obra()->create(['email' => 'ana@example.com']);
    plantResetToken('ana@example.com');
    plantResetToken('Ana@Example.com');

    $before = emailCaseReportSnapshot();

    ['code' => $code, 'output' => $output] = runEmailCaseReport();

    expect($code)->not->toBe(0);
    expect($output)
        ->toContain('Tabela: password_reset_tokens')
        ->toContain('password_reset_tokens: 1 grupo(s) colidem sob lower(email).')
        ->toContain('users: nenhuma colisão sob lower(email).')
        ->toContain('Ana@Example.com')
        ->not->toContain('Nenhuma colisão encontrada.');

    expect(emailCaseReportSnapshot())->toBe($before);
    expect(DB::table('password_reset_tokens')->count())->toBe(2);
});

test('a non-canonical row without any collision is reported and still exits non-zero (CT-04)', function () {
    $user = User::factory()->obra()->create(['email' => 'ana@example.com']);
    plantUserEmail($user, 'Ana@Example.com');

    $before = emailCaseReportSnapshot();

    ['code' => $code, 'output' => $output] = runEmailCaseReport();

    expect($code)->not->toBe(0);
    expect($output)
        ->toContain('Nenhuma colisão encontrada.')
        ->toContain('users: 1 linha(s) fora da forma canônica.')
        ->toContain('E-mails fora da forma canônica: 1 linha(s).')
        ->not->toContain('Nenhuma linha precisa de normalização.');

    expect(emailCaseReportSnapshot())->toBe($before);
});

test('the report never sends an e-mail address to a log channel (RF-10)', function () {
    $user = User::factory()->obra()->create(['email' => 'ana@example.com']);
    plantUserEmail($user, 'Ana@Example.com');
    plantResetToken('ana@example.com');
    plantResetToken('ANA@example.com');

    ['code' => $code, 'output' => $output] = runEmailCaseReport();

    expect($code)->not->toBe(0);
    expect($output)->toContain('ANA@example.com');

    Log::shouldNotHaveReceived('log');
    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('warning');
    Log::shouldNotHaveReceived('error');
    Log::shouldNotHaveReceived('debug');
    Log::shouldNotHaveReceived('notice');
    Log::shouldNotHaveReceived('critical');
});

test('the command source writes only to stdout and issues no data-modifying statement (RF-07, RF-10)', function () {
    $source = file_get_contents(app_path('Console/Commands/EmailCaseReport.php'));

    foreach (['Log::', 'logger(', 'report(', 'error_log('] as $forbidden) {
        $this->assertStringNotContainsString($forbidden, $source, "O comando de diagnóstico não pode usar {$forbidden}.");
    }

    foreach (['->update(', '->insert(', '->delete(', 'DB::statement', 'DB::unprepared', 'save()'] as $forbidden) {
        $this->assertStringNotContainsString($forbidden, $source, "O comando de diagnóstico é somente leitura: {$forbidden} é proibido.");
    }
});
