<?php

use App\Actions\Pedidos\CreatePedidoAction;
use App\Livewire\Pedidos\NovaSolicitacao;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * gestao-nova-solicitacao: Gestão creates pedidos on any active obra without
 * associations, and creating stays its only pedido write.
 */
beforeEach(function () {
    seedWorkflowStatuses();
    seedHistoryEventTypes();

    $this->gestao = User::factory()->gestao()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 */
function gestaoCreatePedido(User $gestao, array $overrides = []): Pedido
{
    return app(CreatePedidoAction::class)->execute($gestao, [
        'obra_selection' => 'outra',
        'descricao' => 'Itens da Gestão',
        'needed_at' => '2026-10-15',
        ...$overrides,
    ]);
}

function gestaoSequenceState(): string
{
    return json_encode(DB::selectOne('select last_value, is_called from pedido_code_sequence'));
}

test('the gestao select lists every active obra, never a concluída one, and returns to the gestao listing', function () {
    $ativaB = Obra::factory()->create(['name' => 'B Obra']);
    $ativaA = Obra::factory()->create(['name' => 'A Obra']);
    Obra::factory()->concluida()->create(['name' => 'C Concluída']);

    $component = Livewire::actingAs($this->gestao)->test(NovaSolicitacao::class)
        ->assertViewHas('obras', fn ($obras): bool => $obras->pluck('id')->all() === [$ativaA->id, $ativaB->id])
        ->set('obra_selection', (string) $ativaA->id)
        ->set('descricao', 'Cimento')
        ->set('needed_at', '2026-10-15')
        ->call('submit')
        ->assertHasNoErrors();

    expect($component->get('code'))->toMatch('/^PED-\d{6}$/')
        ->and($component->instance()->listingRoute())->toBe(route('gestao.pedidos.index'))
        ->and($this->gestao->obras()->count())->toBe(0);
});

test('gestao is refused on a nonexistent or concluída obra and with no active obra at all, without consuming a code', function (string $case, string $message) {
    $concluida = Obra::factory()->concluida()->create();

    if ($case !== 'no active obra') {
        Obra::factory()->create();
    }

    $selection = match ($case) {
        'nonexistent' => '999999',
        'concluída' => (string) $concluida->id,
        'no active obra' => 'outra',
    };
    $sequence = gestaoSequenceState();

    expect(fn () => gestaoCreatePedido($this->gestao, ['obra_selection' => $selection]))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toBe(['obra_id' => [$message]]));

    expect(Pedido::query()->count())->toBe(0)
        ->and(gestaoSequenceState())->toBe($sequence);
})->with([
    'nonexistent' => ['nonexistent', 'A obra informada não foi encontrada.'],
    'concluída' => ['concluída', 'A obra informada está inativa e não recebe novas solicitações.'],
    'no active obra' => ['no active obra', 'Nenhuma obra ativa cadastrada. Cadastre ou reative uma obra em Obras.'],
]);

test('a gestao pedido on a real obra is visible to that obra users only', function () {
    $obra = Obra::factory()->create();
    $obraUser = User::factory()->obra()->create();
    $obraUser->obras()->attach($obra->id);
    $outsider = User::factory()->obra()->create();
    $outsider->obras()->attach(Obra::factory()->create()->id);

    $pedido = gestaoCreatePedido($this->gestao, ['obra_selection' => (string) $obra->id]);

    expect($obraUser->can('view', $pedido))->toBeTrue()
        ->and(Pedido::query()->visibleTo($obraUser)->whereKey($pedido->id)->exists())->toBeTrue()
        ->and($outsider->can('view', $pedido))->toBeFalse()
        ->and(Pedido::query()->visibleTo($outsider)->whereKey($pedido->id)->exists())->toBeFalse();

    $this->actingAs($obraUser)->get(route('obra.pedidos.show', $pedido))->assertOk();
    $this->actingAs($outsider)->get(route('obra.pedidos.show', $pedido))->assertForbidden();
});

test('gestao gets no other write on a pedido it created itself', function (string $ability) {
    Obra::factory()->create();

    $pedido = gestaoCreatePedido($this->gestao);

    expect($this->gestao->can($ability, $pedido))->toBeFalse();
})->with(['addObservacao', 'marcarEntregue', 'anexarRomaneio', 'finalizar', 'setResponsavel', 'setPrioridade', 'setPrevisao', 'updateStatus', 'cancelar']);
