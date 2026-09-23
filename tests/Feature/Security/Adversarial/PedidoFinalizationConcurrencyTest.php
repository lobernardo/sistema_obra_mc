<?php

use App\Actions\Pedidos\AttachRomaneioAction;
use App\Actions\Pedidos\CancelPedidoAction;
use App\Actions\Pedidos\FinalizePedidoAction;
use App\Actions\Pedidos\MarkPedidoEntregueByObraAction;
use App\Enums\EventTypeSlug;
use App\Exceptions\Pedidos\PedidoTerminalStateException;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use App\Services\PedidoAttachmentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * RF-38, RNF-02 — concurrent terminal transitions of one pedido. Same
 * in-process method the developer approved for the convite race: every
 * request loads a stale instance before any of them runs, then they run
 * one after the other, so only the `lockForUpdate` re-read inside each
 * Action's transaction decides who wins. Truly parallel processes cannot
 * share the `RefreshDatabase` transaction; under PostgreSQL the loser of a
 * real race blocks on the row lock and then re-reads the committed status.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    seedHistoryEventTypes();
    Storage::fake(PedidoAttachmentStorage::DISK);

    $this->suprimentos = User::factory()->suprimentos()->create();
    $this->obraUser = User::factory()->obra()->create();
    $this->obra = Obra::factory()->create();
    $this->obraUser->obras()->attach($this->obra->id);
});

function concurrencyPedido(string $status): Pedido
{
    return Pedido::factory()->for(test()->obraUser, 'requester')->create([
        'obra_id' => test()->obra->id,
        'status_id' => test()->statuses[$status]->id,
    ]);
}

/**
 * @param  list<Closure(): mixed>  $requests
 * @return array{successes: int, conflicts: int}
 */
function runStaleRequests(array $requests): array
{
    $outcome = ['successes' => 0, 'conflicts' => 0];

    foreach ($requests as $request) {
        try {
            $request();
            $outcome['successes']++;
        } catch (PedidoTerminalStateException) {
            $outcome['conflicts']++;
        }
    }

    return $outcome;
}

function concurrencyEventCount(Pedido $pedido, EventTypeSlug $slug): int
{
    return PedidoEvent::query()
        ->where('pedido_id', $pedido->id)
        ->whereHas('eventType', fn ($query) => $query->where('slug', $slug->value))
        ->count();
}

test('two finalizations of the same Entregue pedido: 1 success, 1 409 and exactly 1 finalizacao (RF-38)', function () {
    $pedido = concurrencyPedido('entregue');
    app(AttachRomaneioAction::class)->execute($this->suprimentos, $pedido, UploadedFile::fake()->createWithContent('romaneio.pdf', anexoPdfBytes()));

    $staleA = Pedido::query()->with('status')->findOrFail($pedido->id);
    $staleB = Pedido::query()->with('status')->findOrFail($pedido->id);
    $otherSuprimentos = User::factory()->suprimentos()->create();

    $outcome = runStaleRequests([
        fn () => app(FinalizePedidoAction::class)->execute($this->suprimentos, $staleA),
        fn () => app(FinalizePedidoAction::class)->execute($otherSuprimentos, $staleB),
    ]);

    expect($outcome)->toBe(['successes' => 1, 'conflicts' => 1])
        ->and($pedido->fresh()->status_id)->toBe($this->statuses['finalizado']->id)
        ->and(concurrencyEventCount($pedido, EventTypeSlug::Finalizacao))->toBe(1);
});

test('Obra Entregue vs Suprimentos cancel, in either order: 1 success, 1 409 and 1 terminal event (RF-28, RNF-02)', function (string $first) {
    $pedido = concurrencyPedido('aguardando_entrega');

    $staleForObra = Pedido::query()->with('status')->findOrFail($pedido->id);
    $staleForCancel = Pedido::query()->with('status')->findOrFail($pedido->id);

    $entrega = fn () => app(MarkPedidoEntregueByObraAction::class)->execute($this->obraUser, $staleForObra);
    $cancel = fn () => app(CancelPedidoAction::class)->execute($this->suprimentos, $staleForCancel);

    $outcome = runStaleRequests($first === 'entrega' ? [$entrega, $cancel] : [$cancel, $entrega]);

    $expectedStatus = $first === 'entrega' ? 'entregue' : 'cancelado';

    expect($outcome)->toBe(['successes' => 1, 'conflicts' => 1])
        ->and($pedido->fresh()->status_id)->toBe($this->statuses[$expectedStatus]->id)
        ->and(concurrencyEventCount($pedido, EventTypeSlug::Entrega) + concurrencyEventCount($pedido, EventTypeSlug::Cancelamento))->toBe(1)
        ->and(PedidoEvent::query()->where('pedido_id', $pedido->id)->count())->toBe(1);
})->with(['entrega', 'cancelamento']);

test('two Obra Entregue requests: 1 success, 1 409 and exactly 1 entrega (RNF-02)', function () {
    $pedido = concurrencyPedido('em_compra_preparacao');

    $staleA = Pedido::query()->with('status')->findOrFail($pedido->id);
    $staleB = Pedido::query()->with('status')->findOrFail($pedido->id);

    $outcome = runStaleRequests([
        fn () => app(MarkPedidoEntregueByObraAction::class)->execute($this->obraUser, $staleA),
        fn () => app(MarkPedidoEntregueByObraAction::class)->execute($this->obraUser, $staleB),
    ]);

    expect($outcome)->toBe(['successes' => 1, 'conflicts' => 1])
        ->and(concurrencyEventCount($pedido, EventTypeSlug::Entrega))->toBe(1);
});

test('a romaneio upload racing a finalization never lands on the Finalizado pedido', function () {
    $pedido = concurrencyPedido('entregue');
    app(AttachRomaneioAction::class)->execute($this->suprimentos, $pedido, UploadedFile::fake()->createWithContent('romaneio.pdf', anexoPdfBytes()));

    $staleForUpload = Pedido::query()->with('status')->findOrFail($pedido->id);
    app(FinalizePedidoAction::class)->execute($this->suprimentos, $pedido->fresh());

    expect(fn () => app(AttachRomaneioAction::class)->execute(
        $this->suprimentos,
        $staleForUpload,
        UploadedFile::fake()->createWithContent('tarde.pdf', anexoPdfBytes()),
    ))->toThrow(PedidoTerminalStateException::class);

    expect($pedido->romaneios()->count())->toBe(1)
        ->and(Storage::disk(PedidoAttachmentStorage::DISK)->allFiles())->toHaveCount(1)
        ->and(concurrencyEventCount($pedido, EventTypeSlug::RomaneioAnexado))->toBe(1);
});
