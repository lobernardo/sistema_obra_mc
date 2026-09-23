<?php

use App\Actions\Usuarios\CreateUserAction;
use App\Actions\Usuarios\SendAccessLinkAction;
use App\Actions\Usuarios\SetUserActiveAction;
use App\Actions\Usuarios\UpdateUserAction;
use App\Enums\AuthenticationEventType;
use App\Enums\RoleSlug;
use App\Enums\UserAdminAction;
use App\Livewire\Auth\AcceptInvite;
use App\Livewire\Auth\LoginForm;
use App\Models\AuthenticationEvent;
use App\Models\Obra;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAdminEvent;
use App\Notifications\FirstAccessInvite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/**
 * RF-30 (G-13), RF-19..RF-22, RF-27, RNF-09, AC-F19..AC-F22 — adversarial
 * audit suite (decision D-12). One complete administrative lifecycle is
 * driven end to end — create user → invite → first-access password →
 * login → edit (name, papel, obras) → resend link → deactivate — and the
 * resulting rows of the administrative trail are checked for actor,
 * target, action, before and after. Then the RF-22 scan: every column of
 * every row of BOTH trails (`user_admin_events`, `authentication_events`),
 * read with `DB::table()->get()` and serialized with `json_encode`, is
 * searched case-insensitively (`stripos`) for each secret the flow
 * produced, and the schema of both tables is checked against the forbidden
 * column names. Duplicating Phase E/F proofs is intended.
 */
const ADVERSARIAL_AUDIT_PLAIN_PASSWORD = 'g13-senha-definida-Xy7!kQ';

const ADVERSARIAL_AUDIT_WRONG_PASSWORD = 'g13-senha-errada-Zz9!pW';

const ADVERSARIAL_AUDIT_FORBIDDEN_COLUMNS = [
    'password', 'password_hash', 'remember_token', 'token', 'secret',
    'api_key', 'session_id', 'cookie', 'authorization',
];

/**
 * @return list<array<string, mixed>>
 */
function adversarialAuditRowsOf(string $table): array
{
    return DB::table($table)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @param  list<string>  $secrets
 * @return list<string> descriptions of every hit, empty when the trail is clean
 */
function adversarialAuditSecretHits(array $rows, array $secrets): array
{
    $hits = [];

    foreach ($rows as $row) {
        foreach ($row as $column => $value) {
            $serialized = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            foreach ($secrets as $label => $secret) {
                if ($secret !== '' && stripos($serialized, $secret) !== false) {
                    $hits[] = "{$label} found in column {$column} of row {$row['id']}";
                }
            }
        }
    }

    return $hits;
}

beforeEach(function () {
    Notification::fake();

    $this->actor = User::factory()->gestao()->create();
    $this->obraRole = Role::query()->firstOrCreate(['slug' => RoleSlug::Obra->value], ['name' => 'Obra']);
    $this->suprimentosRole = Role::query()->firstOrCreate(['slug' => RoleSlug::Suprimentos->value], ['name' => 'Suprimentos']);
    $this->obra = Obra::factory()->create();
});

test('G-13 the full create → invite → define password → login → edit → resend → deactivate flow writes administrative rows with correct actor, target, action, before and after, and no secret reaches either trail (RF-19..RF-22, RF-27, AC-F19..AC-F22)', function () {
    $secrets = ['plain password' => ADVERSARIAL_AUDIT_PLAIN_PASSWORD, 'wrong password' => ADVERSARIAL_AUDIT_WRONG_PASSWORD];

    // 1. Create (user_created + access_link_sent) — capture the raw token.
    $result = app(CreateUserAction::class)->execute($this->actor, [
        'name' => 'Alvo Inicial',
        'email' => 'g13-alvo@example.com',
        'role_id' => $this->obraRole->id,
        'obra_ids' => [$this->obra->id],
    ]);

    expect($result['invite_sent'])->toBeTrue();

    /** @var User $target */
    $target = $result['user']->fresh();

    $rawInviteToken = null;
    Notification::assertSentTo($target, FirstAccessInvite::class, function (FirstAccessInvite $notification) use (&$rawInviteToken): bool {
        $rawInviteToken = $notification->token;

        return true;
    });

    expect($rawInviteToken)->toBeString()->not->toBe('');
    $secrets['raw invite token'] = $rawInviteToken;
    $secrets['stored invite token'] = (string) DB::table('password_reset_tokens')->where('email', $target->email)->value('token');
    $secrets['random initial password hash'] = $target->password;
    expect($secrets['stored invite token'])->not->toBe('');

    // 2. First access: the invited user defines the known plaintext password.
    Livewire::test(AcceptInvite::class, ['token' => $rawInviteToken])
        ->set('email', $target->email)
        ->set('password', ADVERSARIAL_AUDIT_PLAIN_PASSWORD)
        ->set('password_confirmation', ADVERSARIAL_AUDIT_PLAIN_PASSWORD)
        ->call('acceptInvite')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    $target = $target->fresh();
    $secrets['defined password hash'] = $target->password;
    $secrets['remember_token'] = (string) $target->remember_token;
    expect($secrets['remember_token'])->not->toBe('');

    // 3. Login (one refused attempt, then success) — capture the session id.
    Livewire::test(LoginForm::class)
        ->set('email', $target->email)
        ->set('password', ADVERSARIAL_AUDIT_WRONG_PASSWORD)
        ->call('authenticate')
        ->assertHasErrors(['email']);

    Livewire::test(LoginForm::class)
        ->set('email', $target->email)
        ->set('password', ADVERSARIAL_AUDIT_PLAIN_PASSWORD)
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    expect(Auth::id())->toBe($target->id);
    $secrets['session id'] = session()->getId();
    expect($secrets['session id'])->not->toBe('');

    // 4. Edit: name + papel obra → suprimentos (associations kept, RF-11b).
    app(UpdateUserAction::class)->execute($this->actor, $target->fresh(), [
        'name' => 'Alvo Renomeado',
        'email' => $target->email,
        'role_id' => $this->suprimentosRole->id,
    ]);

    // 5. Resend the access link (access_link_resent) — capture the new token hash.
    $this->travel(61)->seconds();
    expect(app(SendAccessLinkAction::class)->execute($this->actor, $target->fresh(), resend: true))->toBe(Password::RESET_LINK_SENT);
    $secrets['stored resent token'] = (string) DB::table('password_reset_tokens')->where('email', $target->email)->value('token');
    expect($secrets['stored resent token'])->not->toBe('')->not->toBe($secrets['stored invite token']);

    // 6. Deactivate (user_deactivated).
    app(SetUserActiveAction::class)->execute($this->actor, $target->fresh(), false);
    expect($target->fresh()->is_active)->toBeFalse();

    // --- Administrative trail: actor / target / action / before / after ---
    $rows = UserAdminEvent::query()->where('target_id', $target->id)->orderBy('id')->get();

    expect($rows->pluck('action')->map(fn (UserAdminAction $action): string => $action->value)->all())->toBe([
        'user_created',
        'access_link_sent',
        'user_updated',
        'role_changed',
        'access_link_resent',
        'user_deactivated',
    ]);
    expect($rows->pluck('actor_id')->unique()->all())->toBe([$this->actor->id]);
    expect($rows->pluck('target_id')->unique()->all())->toBe([$target->id]);
    expect(UserAdminEvent::query()->count())->toBe(6);

    $byAction = $rows->keyBy(fn (UserAdminEvent $row): string => $row->action->value);

    expect($byAction['user_created']->before)->toBeNull();
    expect($byAction['user_created']->after)->toBe([
        'name' => 'Alvo Inicial',
        'email' => 'g13-alvo@example.com',
        'role' => 'obra',
        'is_active' => true,
        'obra_ids' => [$this->obra->id],
    ]);
    expect($byAction['access_link_sent']->before)->toBeNull();
    expect($byAction['access_link_sent']->after)->toBeNull();
    expect($byAction['user_updated']->before)->toBe(['name' => 'Alvo Inicial']);
    expect($byAction['user_updated']->after)->toBe(['name' => 'Alvo Renomeado']);
    expect($byAction['role_changed']->before)->toBe(['role' => 'obra']);
    expect($byAction['role_changed']->after)->toBe(['role' => 'suprimentos']);
    expect($byAction->has('obra_access_changed'))->toBeFalse();
    expect($target->fresh()->obras()->pluck('obras.id')->all())->toBe([$this->obra->id]);
    expect($byAction['access_link_resent']->before)->toBeNull();
    expect($byAction['access_link_resent']->after)->toBeNull();
    expect($byAction['user_deactivated']->before)->toBe(['is_active' => true]);
    expect($byAction['user_deactivated']->after)->toBe(['is_active' => false]);

    foreach ($rows as $row) {
        expect($row->created_at)->not->toBeNull();
    }

    // --- Authentication trail populated by the same flow ---
    $authEvents = AuthenticationEvent::query()->where('user_id', $target->id)->orderBy('id')->get();

    expect($authEvents->pluck('event')->map(fn (AuthenticationEventType $event): string => $event->value)->all())
        ->toBe(['password_defined', 'login_failed', 'login_success']);
    expect($authEvents->pluck('email')->unique()->all())->toBe(['g13-alvo@example.com']);

    // --- RF-22 scan over every column of every row of both trails ---
    foreach ($secrets as $label => $secret) {
        expect($secret)->toBeString()->not->toBe('', "secret '{$label}' was not captured");
    }

    $adminRows = adversarialAuditRowsOf('user_admin_events');
    $authRows = adversarialAuditRowsOf('authentication_events');

    expect($adminRows)->toHaveCount(6);
    expect(count($authRows))->toBeGreaterThanOrEqual(3);

    expect(adversarialAuditSecretHits($adminRows, $secrets))->toBe([]);
    expect(adversarialAuditSecretHits($authRows, $secrets))->toBe([]);

    // Sanity check of the scanner itself: a planted secret is detected.
    expect(adversarialAuditSecretHits([['id' => 0, 'after' => json_encode(['x' => ADVERSARIAL_AUDIT_PLAIN_PASSWORD])]], $secrets))->not->toBe([]);

    // --- No forbidden column name in either audit schema (RF-22) ---
    foreach (['user_admin_events', 'authentication_events'] as $table) {
        $columns = array_map('strtolower', Schema::getColumnListing($table));

        expect(array_intersect($columns, ADVERSARIAL_AUDIT_FORBIDDEN_COLUMNS))->toBe([]);

        foreach ($columns as $column) {
            foreach (ADVERSARIAL_AUDIT_FORBIDDEN_COLUMNS as $forbidden) {
                expect($column)->not->toBe($forbidden);
            }
        }
    }
});
