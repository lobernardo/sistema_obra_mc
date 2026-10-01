<?php

use App\Actions\Pedidos\AddPedidoObservacaoAction;
use App\Actions\Pedidos\AttachRomaneioAction;
use App\Actions\Pedidos\CancelPedidoAction;
use App\Actions\Pedidos\FinalizePedidoAction;
use App\Actions\Pedidos\MarkPedidoEntregueByObraAction;
use App\Actions\Pedidos\UpdatePedidoPrevisaoAction;
use App\Actions\Pedidos\UpdatePedidoPrioridadeAction;
use App\Actions\Pedidos\UpdatePedidoResponsavelAction;
use App\Actions\Pedidos\UpdatePedidoStatusAction;
use App\Exceptions\Pedidos\PedidoTerminalStateException;
use App\Livewire\Kanban\KanbanBoard;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\Priority;
use App\Models\User;
use App\Services\PedidoAttachmentStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * gestao-operacao-pedidos: Gestão runs every Suprimentos operation on a
 * pedido through its own routes, but never the obra-side "Marcar como
 * entregue" nor the responsável role.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    seedHistoryEventTypes();
    Storage::fake(PedidoAttachmentStorage::DISK);

    $this->gestao = User::factory()->gestao()->create();
    $this->suprimentos = User::factory()->suprimentos()->create();
    $this->obra = Obra::factory()->create();
    $this->pedido = Pedido::factory()->create([
        'obra_id' => $this->obra->id,
        'status_id' => $this->statuses['solicitado']->id,
    ]);
});

test('gestao runs each Suprimentos operation and is recorded as the actor', function (Closure $operation) {
    $before = PedidoEvent::query()->count();

    $operation($this->gestao, $this->pedido);

    expect(PedidoEvent::query()->count())->toBe($before + 1)
        ->and(PedidoEvent::query()->latest('id')->value('actor_id'))->toBe($this->gestao->id);
})->with([
    'status' => [fn (User $actor, Pedido $pedido) => app(UpdatePedidoStatusAction::class)->execute($actor, $pedido, test()->statuses['em_analise']->id)],
    'entregue' => [fn (User $actor, Pedido $pedido) => app(UpdatePedidoStatusAction::class)->execute($actor, $pedido, test()->statuses['entregue']->id)],
    'responsável' => [fn (User $actor, Pedido $pedido) => app(UpdatePedidoResponsavelAction::class)->execute($actor, $pedido, test()->suprimentos->id)],
    'prioridade' => [fn (User $actor, Pedido $pedido) => app(UpdatePedidoPrioridadeAction::class)->execute($actor, $pedido, Priority::factory()->alta()->create()->id)],
    'previsão' => [fn (User $actor, Pedido $pedido) => app(UpdatePedidoPrevisaoAction::class)->execute($actor, $pedido, now()->addDays(5)->toDateString())],
    'cancelar' => [fn (User $actor, Pedido $pedido) => app(CancelPedidoAction::class)->execute($actor, $pedido)],
    'observação' => [fn (User $actor, Pedido $pedido) => app(AddPedidoObservacaoAction::class)->execute($actor, $pedido, 'Pela Gestão')],
    'romaneio' => [fn (User $actor, Pedido $pedido) => app(AttachRomaneioAction::class)->execute($actor, $pedido, UploadedFile::fake()->createWithContent('romaneio.pdf', anexoPdfBytes()))],
]);

test('gestao finalizes a pedido with romaneio', function () {
    app(AttachRomaneioAction::class)->execute($this->gestao, $this->pedido, UploadedFile::fake()->createWithContent('romaneio.pdf', anexoPdfBytes()));

    $pedido = app(FinalizePedidoAction::class)->execute($this->gestao, $this->pedido->fresh());

    expect($pedido->status->slug)->toBe('finalizado');
});

test('gestao cannot be the responsável, cannot use the obra-side Marcar como entregue and is stopped by terminal status', function () {
    expect(fn () => app(UpdatePedidoResponsavelAction::class)->execute($this->gestao, $this->pedido, $this->gestao->id))
        ->toThrow(ValidationException::class);

    expect(fn () => app(MarkPedidoEntregueByObraAction::class)->execute($this->gestao, $this->pedido))
        ->toThrow(AuthorizationException::class);

    app(CancelPedidoAction::class)->execute($this->gestao, $this->pedido);

    expect(fn () => app(UpdatePedidoPrevisaoAction::class)->execute($this->gestao, $this->pedido->fresh(), now()->addDays(5)->toDateString()))
        ->toThrow(PedidoTerminalStateException::class);
});

test('the gestao detail route shows the operational controls and links back to the gestao listing', function () {
    $this->actingAs($this->gestao)
        ->get(route('gestao.pedidos.show', $this->pedido))
        ->assertOk()
        ->assertSee('Cancelar pedido')
        ->assertSee('Observação / ocorrência')
        ->assertSee('href="'.route('gestao.pedidos.index').'"', false)
        ->assertDontSee('href="'.route('suprimentos.pedidos.index').'"', false);
});

test('the gestao kanban links cards to the gestao detail and moves them', function () {
    $this->actingAs($this->gestao)
        ->get(route('gestao.kanban'))
        ->assertOk()
        ->assertSee('href="'.route('gestao.pedidos.show', $this->pedido).'"', false)
        ->assertDontSee(route('suprimentos.pedidos.show', $this->pedido));

    Livewire::actingAs($this->gestao)->test(KanbanBoard::class)
        ->call('moveViaControl', $this->pedido->id, $this->statuses['em_analise']->id);

    expect($this->pedido->fresh()->status_id)->toBe($this->statuses['em_analise']->id);
});
