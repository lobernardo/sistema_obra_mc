<?php

use App\Actions\Pedidos\AddPedidoObservacaoAction;
use App\Actions\Pedidos\AttachRomaneioAction;
use App\Actions\Pedidos\CancelPedidoAction;
use App\Actions\Pedidos\CreatePedidoAction;
use App\Actions\Pedidos\FinalizePedidoAction;
use App\Actions\Pedidos\MarkPedidoEntregueByObraAction;
use App\Actions\Pedidos\UpdatePedidoPrevisaoAction;
use App\Actions\Pedidos\UpdatePedidoPrioridadeAction;
use App\Actions\Pedidos\UpdatePedidoResponsavelAction;
use App\Actions\Pedidos\UpdatePedidoStatusAction;
use App\Enums\EventTypeSlug;
use App\Exceptions\Pedidos\PedidoTerminalStateException;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Models\PedidoEvent;
use App\Models\Priority;
use App\Models\User;
use App\Services\PedidoAttachmentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * RF-33, RF-37 — the terminal matrix of Finalizado and Entregue. Finalizado
 * accepts nothing but observations; Entregue rejects every operational
 * mutation and Obra Entregue, and accepts only romaneio, Finalizar and
 * observations. RF-22 — the trail up to Finalizado is append-only.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    seedHistoryEventTypes();
    Storage::fake(PedidoAttachmentStorage::DISK);

    $this->suprimentos = User::factory()->suprimentos()->create();
    $this->obraUser = User::factory()->obra()->create();
    $this->obra = Obra::factory()->create();
    $this->obraUser->obras()->attach($this->obra->id);
    $this->priority = Priority::factory()->create();
});

function matrixPedido(string $status): Pedido
{
    return Pedido::factory()->for(test()->obraUser, 'requester')->create([
        'obra_id' => test()->obra->id,
        'status_id' => test()->statuses[$status]->id,
    ]);
}

function matrixRomaneioFile(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('romaneio.pdf', anexoPdfBytes());
}

/**
 * The 6 operational mutations (RF-33): responsável, prioridade, previsão,
 * status, cancelamento and Obra Entregue.
 *
 * @return array<string, Closure(Pedido): mixed>
 */
function matrixOperationalMutations(): array
{
    return [
        'responsável' => fn (Pedido $pedido) => app(UpdatePedidoResponsavelAction::class)->execute(test()->suprimentos, $pedido, test()->suprimentos->id),
        'prioridade' => fn (Pedido $pedido) => app(UpdatePedidoPrioridadeAction::class)->execute(test()->suprimentos, $pedido, test()->priority->id),
        'previsão' => fn (Pedido $pedido) => app(UpdatePedidoPrevisaoAction::class)->execute(test()->suprimentos, $pedido, '2026-12-01'),
        'status' => fn (Pedido $pedido) => app(UpdatePedidoStatusAction::class)->execute(test()->suprimentos, $pedido, test()->statuses['em_analise']->id),
        'cancelamento' => fn (Pedido $pedido) => app(CancelPedidoAction::class)->execute(test()->suprimentos, $pedido),
        'obra entregue' => fn (Pedido $pedido) => app(MarkPedidoEntregueByObraAction::class)->execute(test()->obraUser, $pedido),
    ];
}

test('on Finalizado the 6 operational mutations, romaneio and Finalizar → 409 with 0 events (RF-33)', function () {
    $pedido = matrixPedido('finalizado');
    PedidoAttachment::factory()->romaneio()->create(['pedido_id' => $pedido->id, 'path' => 'x/romaneio.pdf']);
    Storage::disk(PedidoAttachmentStorage::DISK)->put('x/romaneio.pdf', anexoPdfBytes());

    $calls = [
        ...matrixOperationalMutations(),
        'romaneio' => fn (Pedido $pedido) => app(AttachRomaneioAction::class)->execute(test()->suprimentos, $pedido, matrixRomaneioFile()),
        'finalizar' => fn (Pedido $pedido) => app(FinalizePedidoAction::class)->execute(test()->suprimentos, $pedido),
    ];

    expect($calls)->toHaveCount(8);

    foreach ($calls as $name => $call) {
        expect(fn () => $call($pedido->fresh()))->toThrow(PedidoTerminalStateException::class, message: $name);
    }

    $fresh = $pedido->fresh();

    expect(PedidoEvent::query()->where('pedido_id', $pedido->id)->count())->toBe(0)
        ->and(PedidoAttachment::query()->count())->toBe(1)
        ->and(Storage::disk(PedidoAttachmentStorage::DISK)->allFiles())->toBe(['x/romaneio.pdf'])
        ->and($fresh->status_id)->toBe($this->statuses['finalizado']->id)
        ->and($fresh->responsible_id)->toBeNull()
        ->and($fresh->priority_id)->toBeNull();
});

test('on Finalizado an observation is still accepted (RF-26, RF-33)', function () {
    $pedido = matrixPedido('finalizado');

    app(AddPedidoObservacaoAction::class)->execute($this->obraUser, $pedido, 'Material conferido.');
    app(AddPedidoObservacaoAction::class)->execute($this->suprimentos, $pedido, 'Arquivado.');

    expect(PedidoEvent::query()->where('pedido_id', $pedido->id)->count())->toBe(2)
        ->and($pedido->fresh()->status_id)->toBe($this->statuses['finalizado']->id);
});

test('on Entregue the 5 Suprimentos Actions and Obra Entregue → 409 with 0 events (RF-37)', function () {
    $pedido = matrixPedido('entregue');

    foreach (matrixOperationalMutations() as $name => $call) {
        expect(fn () => $call($pedido->fresh()))->toThrow(PedidoTerminalStateException::class, message: $name);
    }

    expect(PedidoEvent::query()->where('pedido_id', $pedido->id)->count())->toBe(0)
        ->and($pedido->fresh()->status_id)->toBe($this->statuses['entregue']->id);
});

test('on Entregue romaneio, observation and Finalizar are accepted (RF-26, RF-32, RF-37)', function () {
    $pedido = matrixPedido('entregue');

    app(AttachRomaneioAction::class)->execute($this->suprimentos, $pedido, matrixRomaneioFile());
    app(AddPedidoObservacaoAction::class)->execute($this->obraUser, $pedido, 'Recebido.');
    app(FinalizePedidoAction::class)->execute($this->suprimentos, $pedido);

    $slugs = PedidoEvent::query()->where('pedido_id', $pedido->id)->orderBy('id')->with('eventType')->get()
        ->map(fn (PedidoEvent $event) => $event->eventType->slug)->all();

    expect($slugs)->toBe([
        EventTypeSlug::RomaneioAnexado->value,
        EventTypeSlug::Observacao->value,
        EventTypeSlug::Finalizacao->value,
    ])->and($pedido->fresh()->status_id)->toBe($this->statuses['finalizado']->id);
});

test('creation + 2 observations + romaneio + finalização → exactly 5 events in order, the first observation unchanged (RF-22)', function () {
    $pedido = app(CreatePedidoAction::class)->execute($this->obraUser, [
        'obra_selection' => (string) $this->obra->id,
        'descricao' => 'Cimento e areia',
        'needed_at' => '2026-10-01',
    ]);

    app(AddPedidoObservacaoAction::class)->execute($this->obraUser, $pedido, 'Entregar no portão 2.');
    $firstObservation = PedidoEvent::query()->where('pedido_id', $pedido->id)->orderByDesc('id')->first()->getAttributes();

    app(AddPedidoObservacaoAction::class)->execute($this->suprimentos, $pedido, 'Material separado.');
    app(AttachRomaneioAction::class)->execute($this->suprimentos, $pedido, matrixRomaneioFile());
    app(FinalizePedidoAction::class)->execute($this->suprimentos, $pedido);

    $events = PedidoEvent::query()->where('pedido_id', $pedido->id)->orderBy('created_at')->orderBy('id')->with('eventType')->get();

    expect($events)->toHaveCount(5)
        ->and($events->map(fn (PedidoEvent $event) => $event->eventType->slug)->all())->toBe([
            EventTypeSlug::CriacaoPedido->value,
            EventTypeSlug::Observacao->value,
            EventTypeSlug::Observacao->value,
            EventTypeSlug::RomaneioAnexado->value,
            EventTypeSlug::Finalizacao->value,
        ])
        ->and($events[1]->getAttributes())->toBe($firstObservation)
        ->and($events->pluck('id')->all())->toBe($events->pluck('id')->sort()->values()->all());
});
