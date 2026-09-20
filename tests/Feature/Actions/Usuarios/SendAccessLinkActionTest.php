<?php

use App\Actions\Usuarios\SendAccessLinkAction;
use App\Models\User;
use App\Notifications\FirstAccessInvite;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    Notification::fake();

    $this->actor = User::factory()->gestao()->create();
    $this->target = User::factory()->obra()->create(['email' => 'alvo@example.com']);
    $this->action = new SendAccessLinkAction;
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
