<?php

use App\Actions\Usuarios\SendAccessLinkAction;
use App\Enums\UserAdminAction;
use App\Models\User;
use App\Models\UserAdminEvent;
use App\Notifications\FirstAccessInvite;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    Notification::fake();

    $this->actor = User::factory()->gestao()->create();
    $this->target = User::factory()->obra()->create(['email' => 'alvo@example.com']);
    $this->action = app(SendAccessLinkAction::class);
});

test('gestao sends the first-access invite to the target only and a hashed token row is stored (RF-14, RF-15)', function () {
    $originalHash = $this->target->password;

    $status = $this->action->execute($this->actor, $this->target);

    expect($status)->toBe(Password::RESET_LINK_SENT);

    Notification::assertSentTo($this->target, FirstAccessInvite::class);
    Notification::assertNotSentTo($this->actor, FirstAccessInvite::class);
    Notification::assertCount(1);

    $row = DB::table('password_reset_tokens')->where('email', 'alvo@example.com')->first();

    expect($row)->not->toBeNull();
    expect($row->token)->not->toBeEmpty();

    Notification::assertSentTo($this->target, FirstAccessInvite::class, function (FirstAccessInvite $notification) use ($row) {
        expect($notification->token)->not->toBe($row->token);
        expect(Password::broker('invites')->tokenExists($this->target, $notification->token))->toBeTrue();

        return true;
    });

    expect($this->target->fresh()->password)->toBe($originalHash);
});

test('a second send within the throttle window is refused with the throttled status and sends nothing more (TC-25)', function () {
    expect($this->action->execute($this->actor, $this->target))->toBe(Password::RESET_LINK_SENT);
    expect($this->action->execute($this->actor, $this->target))->toBe(Password::RESET_THROTTLED);

    Notification::assertSentTimes(FirstAccessInvite::class, 1);
    expect(DB::table('password_reset_tokens')->where('email', 'alvo@example.com')->count())->toBe(1);
});

test('after the throttle window a new link can be issued', function () {
    $this->action->execute($this->actor, $this->target);

    $this->travel(61)->seconds();

    expect($this->action->execute($this->actor, $this->target))->toBe(Password::RESET_LINK_SENT);

    Notification::assertSentTimes(FirstAccessInvite::class, 2);
});

test('an obra or suprimentos actor is refused and nothing is sent nor stored (RF-05)', function (string $factoryState) {
    $actor = User::factory()->{$factoryState}()->create();

    expect(fn () => $this->action->execute($actor, $this->target))->toThrow(AuthorizationException::class);

    Notification::assertNothingSent();
    expect(DB::table('password_reset_tokens')->where('email', 'alvo@example.com')->exists())->toBeFalse();
})->with(['obra', 'suprimentos']);

test('the default call records access_link_sent and resend: true records access_link_resent, selected only by the explicit flag (RF-19, RF-20, D-03)', function () {
    expect($this->action->execute($this->actor, $this->target))->toBe(Password::RESET_LINK_SENT);

    expect(UserAdminEvent::query()->pluck('action')->all())->toBe([UserAdminAction::AccessLinkSent]);

    $this->travel(61)->seconds();

    // Same target, same token state as any second send: the slug flips only
    // because the caller passes `resend: true`.
    expect($this->action->execute($this->actor, $this->target, resend: true))->toBe(Password::RESET_LINK_SENT);

    $this->travel(61)->seconds();

    // A later call without the flag on an already-invited user still records
    // `access_link_sent`: invite age and token state are never consulted.
    expect($this->action->execute($this->actor, $this->target))->toBe(Password::RESET_LINK_SENT);

    $rows = UserAdminEvent::query()->orderBy('id')->get();

    expect($rows->pluck('action')->all())->toBe([
        UserAdminAction::AccessLinkSent,
        UserAdminAction::AccessLinkResent,
        UserAdminAction::AccessLinkSent,
    ]);
    expect($rows->pluck('actor_id')->unique()->all())->toBe([$this->actor->id]);
    expect($rows->pluck('target_id')->unique()->all())->toBe([$this->target->id]);
    expect($rows->pluck('before')->unique()->all())->toBe([null]);
    expect($rows->pluck('after')->unique()->all())->toBe([null]);
});

test('a throttled or unknown-user send records no access_link_* row (RF-20)', function () {
    $this->action->execute($this->actor, $this->target);
    expect($this->action->execute($this->actor, $this->target, resend: true))->toBe(Password::RESET_THROTTLED);

    $ghost = User::factory()->obra()->make(['email' => 'fantasma@example.com']);
    $ghost->id = $this->target->id;
    expect($this->action->execute($this->actor, $ghost))->toBe(Password::INVALID_USER);

    expect(UserAdminEvent::query()->count())->toBe(1);
});

test('the access_link record is written outside any transaction and its failure propagates after the e-mail left (D-03, RNF-10)', function () {
    // RefreshDatabase wraps the test in its own transaction; the Action must
    // not open any of its own around the audit insert.
    $baseline = DB::transactionLevel();

    UserAdminEvent::creating(function () use ($baseline): void {
        expect(DB::transactionLevel())->toBe($baseline);

        throw new RuntimeException('audit indisponível');
    });

    expect(fn () => $this->action->execute($this->actor, $this->target, resend: true))
        ->toThrow(RuntimeException::class, 'audit indisponível');

    Notification::assertSentTo($this->target, FirstAccessInvite::class);
    expect(DB::table('password_reset_tokens')->where('email', 'alvo@example.com')->exists())->toBeTrue();
    expect(UserAdminEvent::query()->count())->toBe(0);
});
