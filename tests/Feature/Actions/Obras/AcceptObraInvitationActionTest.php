<?php

use App\Actions\Obras\AcceptObraInvitationAction;
use App\Actions\Obras\GenerateObraInvitationAction;
use App\Actions\Obras\RevokeObraInvitationAction;
use App\Enums\AccountOrigin;
use App\Enums\ObraAdminAction;
use App\Enums\ObraStatus;
use App\Enums\RoleSlug;
use App\Enums\UserAdminAction;
use App\Exceptions\ObraInvitations\ObraInvitationUnavailableException;
use App\Models\AccountRegistrationEvent;
use App\Models\Obra;
use App\Models\ObraAdminEvent;
use App\Models\ObraInvitation;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAdminEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

const ACCEPT_ACTION_PASSWORD = 'senha-forte-123';

beforeEach(function () {
    Role::factory()->obra()->create();

    $this->action = app(AcceptObraInvitationAction::class);
    $this->creator = User::factory()->gestao()->create();
    $this->obra = Obra::factory()->emAndamento()->create();
});

/**
 * Generates a real convite and returns its plaintext token (the URL
 * fragment) together with the persisted row.
 *
 * @return array{0: string, 1: ObraInvitation}
 */
function generateAcceptableInvitation(User $creator, Obra $obra): array
{
    $result = app(GenerateObraInvitationAction::class)->execute($creator, $obra);

    return [parse_url($result['url'], PHP_URL_FRAGMENT), $result['invitation']];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function acceptPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Carlos Lima',
        'email' => 'carlos@example.com',
        'password' => ACCEPT_ACTION_PASSWORD,
        'password_confirmation' => ACCEPT_ACTION_PASSWORD,
    ], $overrides);
}

function acceptTrailCounts(): array
{
    return [
        'users' => User::query()->count(),
        'obra_profile' => DB::table('obra_profile')->count(),
        'account_registration_events' => AccountRegistrationEvent::query()->count(),
        'user_admin_events' => UserAdminEvent::query()->count(),
        'invitation_used' => ObraAdminEvent::query()->where('action', ObraAdminAction::InvitationUsed)->count(),
    ];
}

test('resolveByToken returns the convite for a valid token and resolveById for its id', function () {
    [$token, $invitation] = generateAcceptableInvitation($this->creator, $this->obra);

    expect($this->action->resolveByToken($token)->is($invitation))->toBeTrue();
    expect($this->action->resolveById($invitation->id)->is($invitation))->toBeTrue();
});

test('every invalid cause throws the same exception with the same message and never the token (RF-28, RF-38)', function (Closure $scenario) {
    [$token, $invitation] = generateAcceptableInvitation($this->creator, $this->obra);

    $candidate = $scenario($token, $invitation, $this->obra);
    $stateBefore = DB::table('obra_invitations')->where('id', $invitation->id)->first();

    try {
        $this->action->resolveByToken($candidate);
        $this->fail('The convite should be unavailable.');
    } catch (ObraInvitationUnavailableException $exception) {
        expect($exception->getMessage())->toBe(ObraInvitationUnavailableException::MESSAGE);
        expect((string) $exception)->not->toContain($token);
        expect($exception->getMessage())->not->toContain((string) $invitation->id);
        expect($exception->getPrevious())->toBeNull();
    }

    expect(DB::table('obra_invitations')->where('id', $invitation->id)->first())->toEqual($stateBefore);
})->with([
    'expired' => [function (string $token, ObraInvitation $invitation) {
        test()->travel(24)->hours();

        return $token;
    }],
    'used' => [function (string $token, ObraInvitation $invitation) {
        DB::table('obra_invitations')->where('id', $invitation->id)->update(['used_at' => now(), 'used_by' => User::factory()->obra()->create()->id]);

        return $token;
    }],
    'revoked' => [function (string $token, ObraInvitation $invitation) {
        app(RevokeObraInvitationAction::class)->execute(User::factory()->gestao()->create(), $invitation);

        return $token;
    }],
    'malformed' => [fn (string $token) => strtoupper($token)],
    'unknown' => [fn () => str_repeat('a', 64)],
    'concluded obra' => [function (string $token, ObraInvitation $invitation, Obra $obra) {
        $obra->forceFill(['status' => ObraStatus::Concluido])->save();

        return $token;
    }],
    'empty' => [fn () => ''],
    'non-string' => [fn () => ['token']],
    'too long' => [fn (string $token) => $token.'0'],
]);

test('resolveById throws the same exception for an unknown or unconsumable id', function () {
    expect(fn () => $this->action->resolveById(999_999))->toThrow(ObraInvitationUnavailableException::class, ObraInvitationUnavailableException::MESSAGE);

    $revoked = ObraInvitation::factory()->revoked()->create();

    expect(fn () => $this->action->resolveById($revoked->id))->toThrow(ObraInvitationUnavailableException::class, ObraInvitationUnavailableException::MESSAGE);
});

test('the exception is never reported and renders the generic 404 page', function () {
    $exception = ObraInvitationUnavailableException::make();

    expect($exception)->toBeInstanceOf(RuntimeException::class);
    expect($exception->report())->toBeTrue();

    $response = $exception->render(request());

    expect($response->getStatusCode())->toBe(404);
    expect($response->getContent())->toContain(ObraInvitationUnavailableException::MESSAGE);
});

test('a new account is created with papel obra, one association to the convite obra and the convite used (RF-29, RF-34)', function () {
    [, $invitation] = generateAcceptableInvitation($this->creator, $this->obra);
    $forgedObra = Obra::factory()->create();

    $user = $this->action->acceptAsNewAccount($invitation->id, acceptPayload([
        'obra_id' => $forgedObra->id,
        'obra_ids' => [$forgedObra->id],
        'role_id' => $this->creator->role_id,
    ]), '10.0.0.1');

    $user->refresh();
    $invitation->refresh();

    expect(User::query()->count())->toBe(2);
    expect($user->role->slug)->toBe(RoleSlug::Obra->value);
    expect($user->is_active)->toBeTrue();
    expect($user->is_demo)->toBeFalse();
    expect(DB::table('obra_profile')->count())->toBe(1);
    expect($user->obras()->pluck('obras.id')->all())->toBe([$this->obra->id]);

    expect($invitation->used_by)->toBe($user->id);
    expect($invitation->used_at)->not->toBeNull();
    expect($invitation->revoked_at)->toBeNull();

    $registration = AccountRegistrationEvent::query()->sole();
    expect($registration->user_id)->toBe($user->id);
    expect($registration->origin)->toBe(AccountOrigin::Convite);
    expect($registration->obra_invitation_id)->toBe($invitation->id);
    expect($registration->ip)->toBe('10.0.0.1');

    $access = UserAdminEvent::query()->sole();
    expect($access->action)->toBe(UserAdminAction::ObraAccessChanged);
    expect($access->actor_id)->toBe($user->id);
    expect($access->target_id)->toBe($user->id);
    expect($access->before)->toBe(['obra_ids' => []]);
    expect($access->after)->toBe(['obra_ids' => [$this->obra->id]]);

    $used = ObraAdminEvent::query()->where('action', ObraAdminAction::InvitationUsed)->sole();
    expect($used->actor_id)->toBe($user->id);
    expect($used->obra_id)->toBe($this->obra->id);
    expect($used->obra_invitation_id)->toBe($invitation->id);
});

test('a weak password or a duplicate e-mail creates nothing and leaves the convite pending (RF-29)', function (array $overrides, string $field) {
    User::factory()->obra()->create(['email' => 'existente@example.com']);
    [, $invitation] = generateAcceptableInvitation($this->creator, $this->obra);
    $before = acceptTrailCounts();

    try {
        $this->action->acceptAsNewAccount($invitation->id, acceptPayload($overrides), null);
        $this->fail('Validation should fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }

    expect(acceptTrailCounts())->toBe($before);
    expect($invitation->fresh()->used_at)->toBeNull();
    expect($invitation->fresh()->used_by)->toBeNull();
})->with([
    'weak password' => [['password' => 'curta', 'password_confirmation' => 'curta'], 'password'],
    'duplicate e-mail' => [['email' => '  EXISTENTE@example.com '], 'email'],
]);

test('an existing obra account gains one association and uses the convite (RF-30)', function () {
    [, $invitation] = generateAcceptableInvitation($this->creator, $this->obra);
    $user = User::factory()->obra()->create();

    $result = $this->action->acceptAsExistingAccount($user, $invitation->id);

    expect($result['associated'])->toBeTrue();
    expect($result['user']->is($user))->toBeTrue();
    expect($user->obras()->pluck('obras.id')->all())->toBe([$this->obra->id]);
    expect($invitation->fresh()->used_by)->toBe($user->id);
    expect(UserAdminEvent::query()->where('action', UserAdminAction::ObraAccessChanged)->count())->toBe(1);
    expect(ObraAdminEvent::query()->where('action', ObraAdminAction::InvitationUsed)->sole()->actor_id)->toBe($user->id);
    expect(AccountRegistrationEvent::query()->count())->toBe(0);
});

test('an already associated obra account gains nothing but the convite is used', function () {
    [, $invitation] = generateAcceptableInvitation($this->creator, $this->obra);
    $user = User::factory()->obra()->create();
    $user->obras()->attach($this->obra->id);

    $result = $this->action->acceptAsExistingAccount($user, $invitation->id);

    expect($result['associated'])->toBeFalse();
    expect(DB::table('obra_profile')->count())->toBe(1);
    expect($invitation->fresh()->used_by)->toBe($user->id);
    expect(UserAdminEvent::query()->count())->toBe(0);
    expect(ObraAdminEvent::query()->where('action', ObraAdminAction::InvitationUsed)->count())->toBe(1);
});

test('Gestão and Suprimentos accounts are refused without consuming the convite (RF-31)', function (string $role) {
    [, $invitation] = generateAcceptableInvitation($this->creator, $this->obra);
    $user = User::factory()->{$role}()->create();
    $roleId = $user->role_id;

    try {
        $this->action->acceptAsExistingAccount($user, $invitation->id);
        $this->fail('The role should be refused.');
    } catch (ValidationException $exception) {
        expect($exception->status)->toBe(422);
        expect($exception->errors()['invitation'])->toBe([AcceptObraInvitationAction::ROLE_MISMATCH_MESSAGE]);
    }

    expect($user->fresh()->role_id)->toBe($roleId);
    expect(DB::table('obra_profile')->count())->toBe(0);
    expect($invitation->fresh()->used_at)->toBeNull();
    expect(ObraAdminEvent::query()->where('action', ObraAdminAction::InvitationUsed)->count())->toBe(0);
})->with(['gestao', 'suprimentos']);

test('a convite of an obra switched to Concluído is refused unconsumed, and switching back revalidates it (RF-33)', function () {
    [$token, $invitation] = generateAcceptableInvitation($this->creator, $this->obra);

    $this->obra->forceFill(['status' => ObraStatus::Concluido])->save();

    expect(fn () => $this->action->acceptAsNewAccount($invitation->id, acceptPayload(), null))
        ->toThrow(ObraInvitationUnavailableException::class);
    expect(fn () => $this->action->acceptAsExistingAccount(User::factory()->obra()->create(), $invitation->id))
        ->toThrow(ObraInvitationUnavailableException::class);

    expect(User::query()->where('email', 'carlos@example.com')->exists())->toBeFalse();
    expect(DB::table('obra_profile')->count())->toBe(0);
    expect($invitation->fresh()->used_at)->toBeNull();
    expect($invitation->fresh()->revoked_at)->toBeNull();

    $this->obra->forceFill(['status' => ObraStatus::EmAndamento])->save();

    expect($this->action->resolveByToken($token)->is($invitation))->toBeTrue();
    expect($this->action->acceptAsNewAccount($invitation->id, acceptPayload(), null)->email)->toBe('carlos@example.com');
});

test('a consumption that finds the convite already used rolls back and leaves no row in the three trails (RF-32)', function () {
    [, $invitation] = generateAcceptableInvitation($this->creator, $this->obra);

    $this->action->acceptAsNewAccount($invitation->id, acceptPayload(), null);
    $before = acceptTrailCounts();

    expect(fn () => $this->action->acceptAsNewAccount($invitation->id, acceptPayload(['email' => 'outra@example.com']), null))
        ->toThrow(ObraInvitationUnavailableException::class);
    expect(fn () => $this->action->acceptAsExistingAccount(User::factory()->obra()->create(), $invitation->id))
        ->toThrow(ObraInvitationUnavailableException::class);

    $after = acceptTrailCounts();
    $after['users']--;

    expect($after)->toBe($before);
    expect(User::query()->where('email', 'outra@example.com')->exists())->toBeFalse();
});

test('the conditional consumption is the first statement of the transaction (RF-32)', function () {
    [, $invitation] = generateAcceptableInvitation($this->creator, $this->obra);
    $user = User::factory()->obra()->create()->load('role');

    DB::enableQueryLog();

    $this->action->acceptAsExistingAccount($user, $invitation->id);

    expect(DB::getQueryLog()[0]['query'])->toStartWith('update "obra_invitations"');
});
