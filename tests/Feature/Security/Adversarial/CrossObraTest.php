<?php

use App\Actions\Pedidos\CreatePedidoAction;
use App\Livewire\Obra\PedidoDetalhe;
use App\Livewire\Pedidos\NovaSolicitacao;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\Status;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * RF-30 (G-01..G-04, G-14), AC-F01, AC-F02, AC-F07..AC-F10, AC-F17, AC-F18
 * — adversarial cross-obra suite (decision D-12). User A is associated
 * with obra A only; pedido B belongs to obra B. Every scenario attacks the
 * backend surface a forged request would reach (route, Livewire mount,
 * Livewire action call, Action called directly) and asserts exclusively on
 * HTTP status, exception class or database state — never on the presence
 * or absence of a menu item.
 *
 * Fixture: `Status`/`EventType` lookups are created once per test so the
 * happy path (pedido A) stays reachable and proves the denial is scoped,
 * not a blanket failure.
 */
beforeEach(function () {
    $this->status = Status::factory()->solicitado()->create();
    EventType::factory()->criacaoPedido()->create();

    $this->obraA = Obra::factory()->create();
    $this->obraB = Obra::factory()->create();

    $this->userA = User::factory()->obra()->create();
    $this->userA->obras()->attach($this->obraA->id);

    $this->pedidoA = Pedido::factory()->create([
        'obra_id' => $this->obraA->id,
        'requester_id' => $this->userA->id,
        'status_id' => $this->status->id,
    ]);
    $this->pedidoB = Pedido::factory()->create([
        'obra_id' => $this->obraB->id,
        'status_id' => $this->status->id,
    ]);
});

/**
 * @return array{last_value: int|string, is_called: bool}
 */
function adversarialPedidoSequenceState(): array
{
    return (array) DB::selectOne('select last_value, is_called from pedido_code_sequence');
}

test('G-01 obra A listing /obra/pedidos never contains pedido B (RF-01, RF-02, AC-F01)', function () {
    $this->actingAs($this->userA)
        ->get(route('obra.pedidos.index'))
        ->assertOk()
        ->assertSee($this->pedidoA->code)
        ->assertDontSee($this->pedidoB->code);

    expect(Pedido::query()->visibleTo($this->userA)->pluck('id')->all())->toBe([$this->pedidoA->id]);
});

test('G-02 obra A GET /obra/pedidos/{pedidoB} is refused with 403, never 404 nor 200 (RF-03, AC-F07)', function () {
    $this->actingAs($this->userA)
        ->get(route('obra.pedidos.show', $this->pedidoB))
        ->assertForbidden();

    $this->actingAs($this->userA)
        ->get(route('obra.pedidos.show', $this->pedidoA))
        ->assertOk();
});

test('G-03 obra A mounting Obra\PedidoDetalhe with the forged id B throws AuthorizationException (RF-03, AC-F08, AC-F09)', function () {
    $this->actingAs($this->userA);
    $this->withoutExceptionHandling();

    expect(fn () => Livewire::test(PedidoDetalhe::class, ['pedido' => $this->pedidoB]))
        ->toThrow(AuthorizationException::class);

    Livewire::test(PedidoDetalhe::class, ['pedido' => $this->pedidoA])->assertOk();
});

test('G-04 obra A submitting NovaSolicitacao with a forged obra_id B fails on obra_id and inserts nothing (RF-11c, AC-F02, AC-F09)', function () {
    $pedidoCount = Pedido::query()->count();
    $eventCount = PedidoEvent::query()->count();
    $sequence = adversarialPedidoSequenceState();

    $this->actingAs($this->userA);

    Livewire::test(NovaSolicitacao::class)
        ->set('obra_selection', (string) $this->obraB->id)
        ->set('needed_at', now()->addDays(7)->toDateString())
        ->set('descricao', 'Itens forjados para a obra B.')
        ->call('submit')
        ->assertHasErrors(['obra_id'])
        ->assertSee('A obra informada não está associada ao solicitante.')
        ->assertSet('code', null);

    expect(Pedido::query()->count())->toBe($pedidoCount);
    expect(Pedido::query()->where('obra_id', $this->obraB->id)->where('requester_id', $this->userA->id)->exists())->toBeFalse();
    expect(PedidoEvent::query()->count())->toBe($eventCount);
    expect(adversarialPedidoSequenceState())->toEqual($sequence);
});

test('G-14 an inactive associated obra is refused by CreatePedidoAction on obra_id, with no insert and the code sequence untouched (RF-05, AC-F10, AC-F17)', function () {
    $inactiveObra = Obra::factory()->concluida()->create();
    $this->userA->obras()->attach($inactiveObra->id);

    $pedidoCount = Pedido::query()->count();
    $eventCount = PedidoEvent::query()->count();
    $sequence = adversarialPedidoSequenceState();

    $action = app(CreatePedidoAction::class);

    expect(fn () => $action->execute($this->userA, [
        'obra_selection' => $inactiveObra->id,
        'needed_at' => now()->addDays(7)->toDateString(),
        'descricao' => 'Itens para obra inativa.',
    ]))->toThrow(function (ValidationException $exception): void {
        expect($exception->errors())->toBe([
            'obra_id' => ['A obra informada está inativa e não recebe novas solicitações.'],
        ]);
    });

    expect(Pedido::query()->count())->toBe($pedidoCount);
    expect(Pedido::query()->where('obra_id', $inactiveObra->id)->exists())->toBeFalse();
    expect(PedidoEvent::query()->count())->toBe($eventCount);
    expect(adversarialPedidoSequenceState())->toEqual($sequence);
});
