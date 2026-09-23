<?php

use App\Actions\Obras\GenerateObraInvitationAction;
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
    $this->action = app(GenerateObraInvitationAction::class);
});

test('one generation creates one pending convite expiring 24 h after its creation (RF-23, CT-02)', function (string $factoryState) {
    $actor = User::factory()->{$factoryState}()->create();
    $obra = Obra::factory()->emAndamento()->create();

    $result = $this->action->execute($actor, $obra);

    expect(ObraInvitation::query()->count())->toBe(1);

    $invitation = ObraInvitation::query()->sole();

    expect($result['invitation']->is($invitation))->toBeTrue();
    expect($invitation->obra_id)->toBe($obra->id);
    expect($invitation->created_by)->toBe($actor->id);
    expect($invitation->expires_at->equalTo($invitation->created_at->copy()->addHours(24)))->toBeTrue();
    expect($invitation->revoked_by)->toBeNull();
    expect($invitation->revoked_at)->toBeNull();
    expect($invitation->used_by)->toBeNull();
    expect($invitation->used_at)->toBeNull();
    expect($invitation->state())->toBe(ObraInvitationState::Pendente);
})->with(['gestao', 'suprimentos']);

test('an A iniciar obra also accepts convites', function () {
    $obra = Obra::factory()->aIniciar()->create();

    $this->action->execute(User::factory()->gestao()->create(), $obra);

    expect($obra->invitations()->count())->toBe(1);
});

test('the link carries the 64-hex token only in the fragment of /convite (RF-23, RF-38)', function () {
    $obra = Obra::factory()->emAndamento()->create();

    $url = $this->action->execute(User::factory()->gestao()->create(), $obra)['url'];

    $parts = parse_url($url);
    $token = $parts['fragment'] ?? '';

    expect($token)->toMatch('/^[0-9a-f]{64}$/');
    expect($parts['path'])->toBe('/convite');
    expect($parts)->not->toHaveKey('query');
    expect(str_contains($parts['path'], $token))->toBeFalse();
    expect($url)->toBe(url('/convite').'#'.$token);
    expect(ObraInvitation::query()->sole()->token_hash)->toBe(hash('sha256', $token));
});

test('the plaintext token is stored in no column of obra_invitations or obra_admin_events (RNF-01, RF-38)', function () {
    $obra = Obra::factory()->emAndamento()->create();

    $url = $this->action->execute(User::factory()->gestao()->create(), $obra)['url'];
    $token = parse_url($url, PHP_URL_FRAGMENT);

    $invitationRows = json_encode(DB::table('obra_invitations')->get());
    $auditRows = json_encode(DB::table('obra_admin_events')->get());

    expect($invitationRows)->not->toContain($token);
    expect($auditRows)->not->toContain($token);
    expect($auditRows)->not->toContain(hash('sha256', $token));
});

test('generation writes one invitation_created audit bound to the convite (RF-34)', function () {
    $actor = User::factory()->suprimentos()->create();
    $obra = Obra::factory()->emAndamento()->create();

    $invitation = $this->action->execute($actor, $obra)['invitation'];

    $event = ObraAdminEvent::query()->sole();

    expect($event->action)->toBe(ObraAdminAction::InvitationCreated);
    expect($event->actor_id)->toBe($actor->id);
    expect($event->obra_id)->toBe($obra->id);
    expect($event->obra_invitation_id)->toBe($invitation->id);
    expect($event->before)->toBeNull();
    expect($event->after)->toBeNull();
});

test('three generations for the same obra give three distinct links and hashes (RF-24)', function () {
    $actor = User::factory()->gestao()->create();
    $obra = Obra::factory()->emAndamento()->create();

    $urls = collect(range(1, 3))->map(fn () => $this->action->execute($actor, $obra)['url']);

    expect($urls->unique())->toHaveCount(3);
    expect(ObraInvitation::query()->pluck('token_hash')->unique())->toHaveCount(3);
    expect(ObraAdminEvent::query()->count())->toBe(3);
});

test('a Concluído obra is refused with 422 and nothing is written (RF-33)', function () {
    $obra = Obra::factory()->concluida()->create();

    try {
        $this->action->execute(User::factory()->gestao()->create(), $obra);

        $this->fail('A ValidationException was expected.');
    } catch (ValidationException $exception) {
        expect($exception->status)->toBe(422);
        expect($exception->errors())->toBe(['obra' => ['Não é possível gerar convite para uma obra concluída.']]);
    }

    expect(ObraInvitation::query()->count())->toBe(0);
    expect(ObraAdminEvent::query()->count())->toBe(0);
});

test('an obra actor is refused and nothing is written (RF-07)', function () {
    $obra = Obra::factory()->emAndamento()->create();

    expect(fn () => $this->action->execute(User::factory()->obra()->create(), $obra))
        ->toThrow(AuthorizationException::class);

    expect(ObraInvitation::query()->count())->toBe(0);
    expect(ObraAdminEvent::query()->count())->toBe(0);
});
