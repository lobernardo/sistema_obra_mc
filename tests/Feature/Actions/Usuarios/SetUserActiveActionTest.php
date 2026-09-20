<?php

use App\Actions\Usuarios\SetUserActiveAction;
use App\Livewire\Auth\LoginForm;
use App\Livewire\Gestao\PedidoDetalhe as GestaoPedidoDetalhe;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\Status;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->actor = User::factory()->gestao()->create();
    $this->action = new SetUserActiveAction;
});

test('deactivation flips is_active only and deletes no related row (TC-07, TC-09)', function () {
    EventType::factory()->criacaoPedido()->create();
    $target = User::factory()->obra()->create(['name' => 'Joana Desativada']);
    $responsible = User::factory()->suprimentos()->create();
    $obra = Obra::factory()->create();
    $target->obras()->attach($obra->id);

    $solicitado = Status::factory()->solicitado()->create();
    $requested = Pedido::factory()->create(['obra_id' => $obra->id, 'requester_id' => $target->id, 'status_id' => $solicitado->id]);
    $responsibleFor = Pedido::factory()->create(['responsible_id' => $responsible->id, 'status_id' => $solicitado->id]);
    $event = $requested->events()->create([
        'event_type_id' => EventType::query()->first()->id,
        'actor_id' => $target->id,
    ]);

    DB::table('sessions')->insert([
        'id' => 'session-of-target',
        'user_id' => $target->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'pest',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->timestamp,
    ]);

    $updated = $this->action->execute($this->actor, $target, false);

    expect($updated->is_active)->toBeFalse();
    expect(User::query()->whereKey($target->id)->exists())->toBeTrue();
    expect(Pedido::query()->where('requester_id', $target->id)->count())->toBe(1);
    expect(Pedido::query()->whereKey($responsibleFor->id)->value('responsible_id'))->toBe($responsible->id);
    expect(PedidoEvent::query()->whereKey($event->id)->value('actor_id'))->toBe($target->id);
    expect(DB::table('obra_profile')->where('user_id', $target->id)->count())->toBe(1);
    expect(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(1);

    $this->action->execute($this->actor, $responsible, false);

    expect(Pedido::query()->whereKey($responsibleFor->id)->value('responsible_id'))->toBe($responsible->id);
});

test('the history timeline still renders the name of a deactivated actor (TC-09)', function () {
    EventType::factory()->criacaoPedido()->create();
    $target = User::factory()->obra()->create(['name' => 'Joana Desativada']);
    $pedido = Pedido::factory()->create(['requester_id' => $target->id]);
    $pedido->events()->create([
        'event_type_id' => EventType::query()->first()->id,
        'actor_id' => $target->id,
    ]);

    $this->action->execute($this->actor, $target, false);

    $this->actingAs($this->actor);

    Livewire::test(GestaoPedidoDetalhe::class, ['pedido' => $pedido])
        ->assertSee('Joana Desativada');
});

test('reactivation sets is_active and the user can log in again (RF-11)', function () {
    $target = User::factory()->obra()->inactive()->create([
        'email' => 'volta@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    $updated = $this->action->execute($this->actor, $target, true);

    expect($updated->is_active)->toBeTrue();

    Livewire::test(LoginForm::class)
        ->set('email', 'volta@example.com')
        ->set('password', 'correct-password')
        ->call('authenticate')
        ->assertRedirect(route('home'));

    expect(Auth::id())->toBe($target->id);
});

test('gestao may reactivate its own account without hitting the lockout guards', function () {
    $updated = $this->action->execute($this->actor, $this->actor, true);

    expect($updated->is_active)->toBeTrue();
});

test('an obra or suprimentos actor is refused and the target stays active (RF-05)', function (string $factoryState) {
    $actor = User::factory()->{$factoryState}()->create();
    $target = User::factory()->obra()->create();

    expect(fn () => $this->action->execute($actor, $target, false))
        ->toThrow(AuthorizationException::class);

    expect($target->fresh()->is_active)->toBeTrue();
})->with(['obra', 'suprimentos']);
