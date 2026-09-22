<?php

use App\Actions\Usuarios\UpdateUserAction;
use App\Enums\RoleSlug;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->actor = User::factory()->gestao()->create();
    $this->obraRole = Role::query()->firstOrCreate(['slug' => RoleSlug::Obra->value], ['name' => 'Obra']);
    $this->suprimentosRole = Role::query()->firstOrCreate(['slug' => RoleSlug::Suprimentos->value], ['name' => 'Suprimentos']);
    $this->gestaoRole = Role::query()->where('slug', RoleSlug::Gestao->value)->firstOrFail();
    $this->action = app(UpdateUserAction::class);
});

/**
 * @param  list<int>  $obraIds
 * @return array{name: string, email: string, role_id: int, obra_ids?: list<int>}
 */
function payloadFor(User $target, array $overrides = []): array
{
    $payload = [
        'name' => $target->name,
        'email' => $target->email,
        'role_id' => $target->role_id,
    ];

    if ($target->role?->slug === RoleSlug::Obra->value) {
        $payload['obra_ids'] = $target->obras()->pluck('obras.id')->all();
    }

    return [...$payload, ...$overrides];
}

test('gestao updates nome and e-mail; pedidos and events of the target stay untouched (TC-06)', function () {
    EventType::factory()->criacaoPedido()->create();
    $target = User::factory()->obra()->create(['name' => 'Antes', 'email' => 'antes@example.com']);
    $obra = Obra::factory()->create();
    $target->obras()->attach($obra->id);
    $pedido = Pedido::factory()->create(['obra_id' => $obra->id, 'requester_id' => $target->id]);
    $event = $pedido->events()->create([
        'event_type_id' => EventType::query()->first()->id,
        'actor_id' => $target->id,
    ]);
    $originalUpdatedAt = $target->updated_at;

    $this->travel(1)->minutes();

    $updated = $this->action->execute($this->actor, $target, payloadFor($target, [
        'name' => 'Depois',
        'email' => 'depois@example.com',
    ]));

    expect($updated->name)->toBe('Depois');
    expect($updated->email)->toBe('depois@example.com');
    expect($updated->role_id)->toBe($this->obraRole->id);
    expect($updated->updated_at->gt($originalUpdatedAt))->toBeTrue();
    expect(Pedido::query()->whereKey($pedido->id)->value('requester_id'))->toBe($target->id);
    expect(PedidoEvent::query()->whereKey($event->id)->value('actor_id'))->toBe($target->id);
});

test('replacing obras {A,B} with {B,C} leaves the pivot exactly {B,C} and PedidoPolicy::view follows (TC-05)', function () {
    [$obraA, $obraB, $obraC] = Obra::factory()->count(3)->create();
    $target = User::factory()->obra()->create();
    $target->obras()->attach([$obraA->id, $obraB->id]);
    $solicitado = Status::factory()->solicitado()->create();
    $pedidoA = Pedido::factory()->create(['obra_id' => $obraA->id, 'status_id' => $solicitado->id]);
    $pedidoC = Pedido::factory()->create(['obra_id' => $obraC->id, 'status_id' => $solicitado->id]);

    expect($target->can('view', $pedidoA))->toBeTrue();
    expect($target->can('view', $pedidoC))->toBeFalse();

    $updated = $this->action->execute($this->actor, $target, payloadFor($target, [
        'obra_ids' => [$obraB->id, $obraC->id],
    ]));

    expect($updated->obras()->pluck('obras.id')->all())->toEqualCanonicalizing([$obraB->id, $obraC->id]);
    expect(DB::table('obra_profile')->where('user_id', $target->id)->count())->toBe(2);
    expect($updated->can('view', $pedidoC))->toBeTrue();
    expect($updated->can('view', $pedidoA))->toBeFalse();
});

test('an obra user cannot be left with zero obras; the pivot is preserved (TC-23)', function (array $obraIds) {
    $obra = Obra::factory()->create();
    $target = User::factory()->obra()->create();
    $target->obras()->attach($obra->id);

    $payload = payloadFor($target);
    unset($payload['obra_ids']);

    try {
        $this->action->execute($this->actor, $target, [...$payload, ...$obraIds]);

        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('obra_ids');
        expect($exception->errors()['obra_ids'][0])->toBe('Selecione pelo menos uma obra para o perfil Obra.');
    }

    expect($target->fresh()->obras()->pluck('obras.id')->all())->toBe([$obra->id]);
})->with([
    'missing key' => [[]],
    'empty array' => [['obra_ids' => []]],
]);

test('changing the papel from obra to suprimentos or gestao detaches every obra (TC-24)', function (string $roleProperty) {
    $target = User::factory()->obra()->create();
    $target->obras()->attach(Obra::factory()->count(2)->create()->pluck('id')->all());

    $payload = payloadFor($target, ['role_id' => $this->{$roleProperty}->id]);
    unset($payload['obra_ids']);

    $updated = $this->action->execute($this->actor, $target, $payload);

    expect($updated->role_id)->toBe($this->{$roleProperty}->id);
    expect(DB::table('obra_profile')->where('user_id', $target->id)->count())->toBe(0);
})->with(['suprimentosRole', 'gestaoRole']);

test('changing the papel to obra requires at least one obra', function () {
    $target = User::factory()->suprimentos()->create();

    expect(fn () => $this->action->execute($this->actor, $target, payloadFor($target, [
        'role_id' => $this->obraRole->id,
    ])))->toThrow(ValidationException::class);

    expect($target->fresh()->role_id)->toBe($this->suprimentosRole->id);
});

test('an e-mail change only updates the column: no notification, old reset token preserved (TC-24)', function () {
    Notification::fake();

    $target = User::factory()->suprimentos()->create(['email' => 'old@example.com']);

    DB::table('password_reset_tokens')->insert([
        'email' => 'old@example.com',
        'token' => 'hashed-token',
        'created_at' => now(),
    ]);

    $updated = $this->action->execute($this->actor, $target, payloadFor($target, ['email' => 'new@example.com']));

    expect($updated->email)->toBe('new@example.com');
    expect(DB::table('password_reset_tokens')->where('email', 'old@example.com')->exists())->toBeTrue();

    Notification::assertNothingSent();
});

test('the e-mail unique rule ignores the target itself but not other users', function () {
    User::factory()->obra()->create(['email' => 'taken@example.com']);
    $target = User::factory()->suprimentos()->create(['email' => 'mine@example.com']);

    $unchanged = $this->action->execute($this->actor, $target, payloadFor($target, ['name' => 'Renomeado']));
    expect($unchanged->email)->toBe('mine@example.com');

    try {
        $this->action->execute($this->actor, $target, payloadFor($target, ['email' => 'taken@example.com']));

        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['email'][0])->toBe('Já existe um usuário com este e-mail.');
    }

    expect($target->fresh()->email)->toBe('mine@example.com');
});

test('an obra or suprimentos actor is refused and nothing changes (RF-05)', function (string $factoryState) {
    $actor = User::factory()->{$factoryState}()->create();
    $target = User::factory()->suprimentos()->create(['name' => 'Original']);

    expect(fn () => $this->action->execute($actor, $target, payloadFor($target, ['name' => 'Alterado'])))
        ->toThrow(AuthorizationException::class);

    expect($target->fresh()->name)->toBe('Original');
})->with(['obra', 'suprimentos']);
