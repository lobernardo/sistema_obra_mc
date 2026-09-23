<?php

use App\Actions\Obras\GenerateObraInvitationAction;
use App\Actions\Obras\RevokeObraInvitationAction;
use App\Enums\ObraAdminAction;
use App\Enums\ObraInvitationState;
use App\Models\Obra;
use App\Models\ObraAdminEvent;
use App\Models\ObraInvitation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->action = app(RevokeObraInvitationAction::class);
});

test('revoking a pending convite records revoker, time and one audit (RF-25, RF-34)', function (string $factoryState) {
    $actor = User::factory()->{$factoryState}()->create();
    $invitation = ObraInvitation::factory()->create();

    $this->freezeSecond();

    $revoked = $this->action->execute($actor, $invitation);

    expect($revoked->revoked_by)->toBe($actor->id);
    expect($revoked->revoked_at->equalTo(now()))->toBeTrue();
    expect($revoked->used_at)->toBeNull();
    expect($revoked->state())->toBe(ObraInvitationState::Revogado);

    $event = ObraAdminEvent::query()->sole();

    expect($event->action)->toBe(ObraAdminAction::InvitationRevoked);
    expect($event->actor_id)->toBe($actor->id);
    expect($event->obra_id)->toBe($invitation->obra_id);
    expect($event->obra_invitation_id)->toBe($invitation->id);
    expect($event->before)->toBeNull();
    expect($event->after)->toBeNull();
})->with(['gestao', 'suprimentos']);

test('a pending convite of a Concluído obra can still be revoked', function () {
    $invitation = ObraInvitation::factory()->for(Obra::factory()->concluida())->create();

    $this->action->execute(User::factory()->gestao()->create(), $invitation);

    expect($invitation->fresh()->state())->toBe(ObraInvitationState::Revogado);
});

test('a used, revoked or expired convite is refused with 422 and the row stays identical (RF-25)', function (string $factoryState) {
    $invitation = ObraInvitation::factory()->{$factoryState}()->create();
    $rowBefore = (array) DB::table('obra_invitations')->where('id', $invitation->id)->first();
    $auditsBefore = ObraAdminEvent::query()->count();

    try {
        $this->action->execute(User::factory()->gestao()->create(), $invitation);

        $this->fail('A ValidationException was expected.');
    } catch (ValidationException $exception) {
        expect($exception->status)->toBe(422);
        expect($exception->errors())->toBe(['invitation' => ['Somente convites pendentes podem ser revogados.']]);
    }

    expect((array) DB::table('obra_invitations')->where('id', $invitation->id)->first())->toBe($rowBefore);
    expect(ObraAdminEvent::query()->count())->toBe($auditsBefore);
})->with(['used', 'revoked', 'expired']);

test('generate + revoke yields two obra_admin_events (RF-34)', function () {
    $actor = User::factory()->gestao()->create();
    $obra = Obra::factory()->emAndamento()->create();

    $invitation = app(GenerateObraInvitationAction::class)->execute($actor, $obra)['invitation'];
    $this->action->execute($actor, $invitation);

    expect(ObraAdminEvent::query()->orderBy('id')->pluck('action')->all())->toBe([
        ObraAdminAction::InvitationCreated,
        ObraAdminAction::InvitationRevoked,
    ]);
});

test('an obra actor is refused on both Actions (RF-07)', function () {
    $obraUser = User::factory()->obra()->create();
    $obra = Obra::factory()->emAndamento()->create();
    $invitation = ObraInvitation::factory()->for($obra)->create();

    expect(fn () => app(GenerateObraInvitationAction::class)->execute($obraUser, $obra))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->action->execute($obraUser, $invitation))
        ->toThrow(AuthorizationException::class);

    expect($invitation->fresh()->revoked_at)->toBeNull();
    expect(ObraInvitation::query()->count())->toBe(1);
    expect(ObraAdminEvent::query()->count())->toBe(0);
});
