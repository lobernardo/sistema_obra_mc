<?php

use App\Enums\ObraInvitationState;
use App\Models\Obra;
use App\Models\ObraInvitation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('each convite receives the correct derived state and label (RF-26)', function (string $factoryState, ObraInvitationState $expected, string $label) {
    $factory = ObraInvitation::factory();
    $invitation = ($factoryState === 'pending' ? $factory : $factory->{$factoryState}())->create();

    expect($invitation->fresh()->state())->toBe($expected);
    expect($invitation->fresh()->state()->label())->toBe($label);
})->with([
    'pendente' => ['pending', ObraInvitationState::Pendente, 'Pendente'],
    'utilizado' => ['used', ObraInvitationState::Utilizado, 'Utilizado'],
    'revogado' => ['revoked', ObraInvitationState::Revogado, 'Revogado'],
    'expirado' => ['expired', ObraInvitationState::Expirado, 'Expirado'],
]);

test('used takes precedence over expired and revoked takes precedence over expired (RF-26)', function () {
    $usedAndExpired = ObraInvitation::factory()->expired()->used()->create();
    $revokedAndExpired = ObraInvitation::factory()->expired()->revoked()->create();

    expect($usedAndExpired->fresh()->state())->toBe(ObraInvitationState::Utilizado);
    expect($revokedAndExpired->fresh()->state())->toBe(ObraInvitationState::Revogado);
});

test('a convite is valid 23h59min after creation and invalid 24h00min01s after (RF-27)', function () {
    $this->freezeTime();

    $invitation = ObraInvitation::factory()->create(['expires_at' => now()->addHours(24)]);

    $this->travel(23)->hours();
    $this->travel(59)->minutes();

    expect($invitation->fresh()->state())->toBe(ObraInvitationState::Pendente);
    expect($invitation->fresh()->isConsumable())->toBeTrue();
    expect(ObraInvitation::query()->consumable()->whereKey($invitation->id)->exists())->toBeTrue();

    $this->travel(1)->minutes();
    $this->travel(1)->seconds();

    expect($invitation->fresh()->state())->toBe(ObraInvitationState::Expirado);
    expect($invitation->fresh()->isConsumable())->toBeFalse();
    expect(ObraInvitation::query()->consumable()->whereKey($invitation->id)->exists())->toBeFalse();
});

test('a convite is already expired at exactly expires_at (RF-27)', function () {
    $this->freezeTime();

    $invitation = ObraInvitation::factory()->create(['expires_at' => now()->addHours(24)]);

    $this->travel(24)->hours();

    expect($invitation->fresh()->state())->toBe(ObraInvitationState::Expirado);
    expect(ObraInvitation::query()->consumable()->whereKey($invitation->id)->exists())->toBeFalse();
});

test('a pending convite of a Concluído obra is not consumable (RF-27, NC-07)', function () {
    $invitation = ObraInvitation::factory()->for(Obra::factory()->concluida())->create();

    expect($invitation->fresh()->state())->toBe(ObraInvitationState::Pendente);
    expect($invitation->fresh()->isConsumable())->toBeFalse();
    expect(ObraInvitation::query()->consumable()->whereKey($invitation->id)->exists())->toBeFalse();
});

test('a pending convite of an A iniciar obra is consumable and used/revoked ones are not (RF-27)', function () {
    $pending = ObraInvitation::factory()->for(Obra::factory()->aIniciar())->create();
    $used = ObraInvitation::factory()->used()->create();
    $revoked = ObraInvitation::factory()->revoked()->create();

    expect($pending->fresh()->isConsumable())->toBeTrue();
    expect($used->fresh()->isConsumable())->toBeFalse();
    expect($revoked->fresh()->isConsumable())->toBeFalse();
    expect(ObraInvitation::query()->consumable()->pluck('id')->all())->toBe([$pending->id]);
});

test('hashToken applies sha256 and the model never exposes the hash (RNF-01, RF-38)', function () {
    $token = 'um-token-qualquer';

    expect(ObraInvitation::hashToken($token))->toBe(hash('sha256', $token));
    expect(strlen(ObraInvitation::hashToken($token)))->toBe(64);

    $invitation = ObraInvitation::factory()->create(['token_hash' => ObraInvitation::hashToken($token)]);

    expect($invitation->fresh()->toArray())->not->toHaveKey('token_hash');
    expect(ObraInvitation::query()->where('token_hash', ObraInvitation::hashToken($token))->value('id'))->toBe($invitation->id);
});

test('only obra_id, token_hash, created_by and expires_at are mass assignable (CT-02)', function () {
    expect((new ObraInvitation)->getFillable())->toBe(['obra_id', 'token_hash', 'created_by', 'expires_at']);
    expect(ObraInvitation::UPDATED_AT)->toBeNull();
});

test('relations resolve obra, creator, revoker and user (CT-02)', function () {
    $invitation = ObraInvitation::factory()->revoked()->create();
    $used = ObraInvitation::factory()->used()->create();

    expect($invitation->obra)->toBeInstanceOf(Obra::class);
    expect($invitation->creator)->toBeInstanceOf(User::class);
    expect($invitation->revoker)->toBeInstanceOf(User::class);
    expect($used->user)->toBeInstanceOf(User::class);
    expect($invitation->obra->invitations->pluck('id')->all())->toBe([$invitation->id]);
});

test('the database rejects a convite both revoked and used (CT-02)', function () {
    $invitation = ObraInvitation::factory()->revoked()->create();
    $consumer = User::factory()->obra()->create();

    expect(fn () => DB::transaction(fn () => DB::table('obra_invitations')
        ->where('id', $invitation->id)
        ->update(['used_by' => $consumer->id, 'used_at' => now()])))
        ->toThrow(QueryException::class);

    expect($invitation->fresh()->used_at)->toBeNull();
});

test('the database rejects a duplicate token_hash (CT-02)', function () {
    $hash = ObraInvitation::hashToken('repetido');
    ObraInvitation::factory()->create(['token_hash' => $hash]);

    expect(fn () => DB::transaction(fn () => ObraInvitation::factory()->create(['token_hash' => $hash])))
        ->toThrow(QueryException::class);

    expect(ObraInvitation::query()->where('token_hash', $hash)->count())->toBe(1);
});
