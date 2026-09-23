<?php

use App\Actions\Obras\AcceptObraInvitationAction;
use App\Actions\Obras\GenerateObraInvitationAction;
use App\Actions\Obras\RevokeObraInvitationAction;
use App\Enums\ObraAdminAction;
use App\Enums\UserAdminAction;
use App\Exceptions\ObraInvitations\ObraInvitationUnavailableException;
use App\Livewire\Auth\ObraInvitationPage;
use App\Models\AccountRegistrationEvent;
use App\Models\Obra;
use App\Models\ObraAdminEvent;
use App\Models\ObraInvitation;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAdminEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * RF-32, RNF-02 — concurrent consumption of one convite. Decided by the
 * developer: an in-process simulation (no new testsuite, no `phpunit.xml`
 * change). Ten stale instances of the same convite all pass the read-side
 * check (`resolveByToken`) before the first consumption, so only the
 * conditional `UPDATE ... WHERE id = ? AND used_at IS NULL AND revoked_at
 * IS NULL AND expires_at > ?` decides who wins. Truly parallel processes
 * cannot share the `RefreshDatabase` transaction; under PostgreSQL's row
 * lock the loser of a real race re-evaluates the same predicate and also
 * matches 0 rows.
 */
const CONCURRENCY_PASSWORD = 'senha-forte-123';

beforeEach(function () {
    Role::factory()->obra()->create();

    $this->creator = User::factory()->gestao()->create();
    $this->obra = Obra::factory()->emAndamento()->create();

    $result = app(GenerateObraInvitationAction::class)->execute($this->creator, $this->obra);

    $this->token = parse_url($result['url'], PHP_URL_FRAGMENT);
    $this->invitation = $result['invitation'];
    $this->action = app(AcceptObraInvitationAction::class);
});

/**
 * @return array{users: int, obra_profile: int, registrations: int, access_changes: int, invitation_used: int}
 */
function concurrencyCounts(): array
{
    return [
        'users' => User::query()->count(),
        'obra_profile' => DB::table('obra_profile')->count(),
        'registrations' => AccountRegistrationEvent::query()->count(),
        'access_changes' => UserAdminEvent::query()->where('action', UserAdminAction::ObraAccessChanged)->count(),
        'invitation_used' => ObraAdminEvent::query()->where('action', ObraAdminAction::InvitationUsed)->count(),
    ];
}

test('10 stale new-account consumptions of one convite: exactly 1 wins', function () {
    $stale = array_map(fn () => $this->action->resolveByToken($this->token), range(1, 10));
    $before = concurrencyCounts();

    $successes = 0;
    $unavailable = 0;

    foreach ($stale as $index => $invitation) {
        try {
            $this->action->acceptAsNewAccount($invitation->id, [
                'name' => "Pessoa {$index}",
                'email' => "pessoa{$index}@example.com",
                'password' => CONCURRENCY_PASSWORD,
                'password_confirmation' => CONCURRENCY_PASSWORD,
            ], '10.0.0.1');
            $successes++;
        } catch (ObraInvitationUnavailableException) {
            $unavailable++;
        }
    }

    expect($successes)->toBe(1);
    expect($unavailable)->toBe(9);

    $after = concurrencyCounts();

    expect($after['users'] - $before['users'])->toBe(1);
    expect($after['obra_profile'] - $before['obra_profile'])->toBe(1);
    expect($after['registrations'] - $before['registrations'])->toBe(1);
    expect($after['access_changes'] - $before['access_changes'])->toBe(1);
    expect($after['invitation_used'] - $before['invitation_used'])->toBe(1);

    $winner = User::query()->where('email', 'pessoa0@example.com')->sole();

    expect($this->invitation->fresh()->used_by)->toBe($winner->id);
    expect(AccountRegistrationEvent::query()->whereNotIn('user_id', User::query()->select('id'))->count())->toBe(0);
});

test('10 stale existing-account consumptions of one convite: exactly 1 wins', function () {
    $users = User::factory()->obra()->count(10)->create();
    $stale = array_map(fn () => $this->action->resolveByToken($this->token), range(1, 10));
    $before = concurrencyCounts();

    $successes = 0;
    $unavailable = 0;

    foreach ($users as $index => $user) {
        try {
            $this->action->acceptAsExistingAccount($user, $stale[$index]->id);
            $successes++;
        } catch (ObraInvitationUnavailableException) {
            $unavailable++;
        }
    }

    expect($successes)->toBe(1);
    expect($unavailable)->toBe(9);

    $after = concurrencyCounts();

    expect($after['users'])->toBe($before['users']);
    expect($after['obra_profile'] - $before['obra_profile'])->toBe(1);
    expect($after['access_changes'] - $before['access_changes'])->toBe(1);
    expect($after['invitation_used'] - $before['invitation_used'])->toBe(1);
    expect($this->invitation->fresh()->used_by)->toBe($users->first()->id);
});

test('10 stale convite pages resolved before the first submit: exactly 1 account is created', function () {
    $pages = array_map(
        fn () => Livewire::test(ObraInvitationPage::class)->call('lookup', $this->token)->assertSet('invitationId', $this->invitation->id),
        range(1, 10),
    );

    $before = concurrencyCounts();
    $home = 0;
    $unavailable = 0;

    foreach ($pages as $index => $page) {
        Auth::guard('web')->logout();

        $page->set('name', "Pessoa {$index}")
            ->set('email', "pagina{$index}@example.com")
            ->set('password', CONCURRENCY_PASSWORD)
            ->set('password_confirmation', CONCURRENCY_PASSWORD)
            ->call('register');

        $redirect = $page->effects['redirect'] ?? null;

        if ($redirect === route('home')) {
            $home++;
        } elseif ($redirect === route('obra-invitation.unavailable')) {
            $unavailable++;
        }
    }

    expect($home)->toBe(1);
    expect($unavailable)->toBe(9);

    $after = concurrencyCounts();

    expect($after['users'] - $before['users'])->toBe(1);
    expect($after['obra_profile'] - $before['obra_profile'])->toBe(1);
    expect($after['invitation_used'] - $before['invitation_used'])->toBe(1);
});

test('revocation first, consumption second: only the revocation wins', function () {
    $staleForConsume = $this->action->resolveByToken($this->token);
    $staleForRevoke = ObraInvitation::query()->findOrFail($this->invitation->id);

    app(RevokeObraInvitationAction::class)->execute($this->creator, $staleForRevoke);

    expect(fn () => $this->action->acceptAsExistingAccount(User::factory()->obra()->create(), $staleForConsume->id))
        ->toThrow(ObraInvitationUnavailableException::class);

    $fresh = $this->invitation->fresh();

    expect($fresh->revoked_at)->not->toBeNull();
    expect($fresh->used_at)->toBeNull();
    expect(DB::table('obra_profile')->count())->toBe(0);
    expect(ObraAdminEvent::query()->where('action', ObraAdminAction::InvitationUsed)->count())->toBe(0);
    expect(ObraAdminEvent::query()->where('action', ObraAdminAction::InvitationRevoked)->count())->toBe(1);
});

test('consumption first, revocation second: only the consumption wins', function () {
    $staleForConsume = $this->action->resolveByToken($this->token);
    $staleForRevoke = ObraInvitation::query()->findOrFail($this->invitation->id);
    $user = User::factory()->obra()->create();

    $this->action->acceptAsExistingAccount($user, $staleForConsume->id);

    expect(fn () => app(RevokeObraInvitationAction::class)->execute($this->creator, $staleForRevoke))
        ->toThrow(ValidationException::class);

    $fresh = $this->invitation->fresh();

    expect($fresh->used_by)->toBe($user->id);
    expect($fresh->revoked_at)->toBeNull();
    expect(ObraAdminEvent::query()->where('action', ObraAdminAction::InvitationRevoked)->count())->toBe(0);
    expect(ObraAdminEvent::query()->where('action', ObraAdminAction::InvitationUsed)->count())->toBe(1);
});
