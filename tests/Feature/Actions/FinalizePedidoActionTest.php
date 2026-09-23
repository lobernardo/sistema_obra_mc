<?php

use App\Actions\Pedidos\AttachRomaneioAction;
use App\Actions\Pedidos\FinalizePedidoAction;
use App\Enums\EventTypeSlug;
use App\Exceptions\Pedidos\PedidoTerminalStateException;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Models\PedidoEvent;
use App\Models\User;
use App\Services\PedidoAttachmentStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    seedHistoryEventTypes();
    Storage::fake(PedidoAttachmentStorage::DISK);

    $this->suprimentos = User::factory()->suprimentos()->create();
    $this->obra = Obra::factory()->create();
    $this->action = app(FinalizePedidoAction::class);
});

function finalizePedido(string $status): Pedido
{
    return Pedido::factory()->create([
        'obra_id' => test()->obra->id,
        'status_id' => test()->statuses[$status]->id,
    ]);
}

/**
 * Attaches a romaneio through the real upload path; its status must allow it.
 */
function finalizeWithRomaneio(Pedido $pedido): PedidoAttachment
{
    return app(AttachRomaneioAction::class)->execute(
        test()->suprimentos,
        $pedido,
        UploadedFile::fake()->createWithContent('romaneio.pdf', anexoPdfBytes()),
    );
}

/**
 * @return list<array<string, mixed>>
 */
function finalizeEventSnapshot(Pedido $pedido): array
{
    return PedidoEvent::query()->where('pedido_id', $pedido->id)->orderBy('id')->get()
        ->map(fn (PedidoEvent $event) => $event->getAttributes())->all();
}

function finalizacaoCount(Pedido $pedido): int
{
    return PedidoEvent::query()
        ->where('pedido_id', $pedido->id)
        ->whereHas('eventType', fn ($query) => $query->where('slug', EventTypeSlug::Finalizacao->value))
        ->count();
}

test('with a romaneio, from each active status and from Entregue → finalizado + 1 finalizacao, earlier events intact (RF-34, RF-37)', function (string $status) {
    $pedido = finalizePedido($status);
    finalizeWithRomaneio($pedido);
    $before = finalizeEventSnapshot($pedido);

    $result = $this->action->execute($this->suprimentos, $pedido);

    $after = finalizeEventSnapshot($pedido);
    $last = PedidoEvent::query()->where('pedido_id', $pedido->id)->latest('id')->first();

    expect($result->status_id)->toBe($this->statuses['finalizado']->id)
        ->and($pedido->fresh()->status_id)->toBe($this->statuses['finalizado']->id)
        ->and(array_slice($after, 0, count($before)))->toBe($before)
        ->and($after)->toHaveCount(count($before) + 1)
        ->and($last->eventType->slug)->toBe(EventTypeSlug::Finalizacao->value)
        ->and($last->actor_id)->toBe($this->suprimentos->id)
        ->and($last->previous_value)->toBe((string) $this->statuses[$status]->id)
        ->and($last->new_value)->toBe((string) $this->statuses['finalizado']->id);
})->with(['solicitado', 'em_analise', 'em_compra_preparacao', 'aguardando_entrega', 'entregue']);

test('from Cancelado or Finalizado → 409 and 0 events (RF-37)', function (string $status) {
    $pedido = finalizePedido($status);
    PedidoAttachment::factory()->romaneio()->create(['pedido_id' => $pedido->id, 'path' => 'x/romaneio.pdf']);
    Storage::disk(PedidoAttachmentStorage::DISK)->put('x/romaneio.pdf', anexoPdfBytes());

    expect(fn () => $this->action->execute($this->suprimentos, $pedido))
        ->toThrow(PedidoTerminalStateException::class);

    expect(PedidoEvent::query()->where('pedido_id', $pedido->id)->count())->toBe(0)
        ->and($pedido->fresh()->status_id)->toBe($this->statuses[$status]->id);
})->with(['cancelado', 'finalizado']);

test('without a valid romaneio → 422 with the exact message, nothing changes (RF-35)', function (string $case) {
    $pedido = finalizePedido('entregue');

    match ($case) {
        'no attachments' => null,
        'only an anexo' => (function () use ($pedido): void {
            PedidoAttachment::factory()->create(['pedido_id' => $pedido->id, 'path' => 'x/romaneio.pdf', 'original_name' => 'romaneio.pdf']);
            Storage::disk(PedidoAttachmentStorage::DISK)->put('x/romaneio.pdf', anexoPdfBytes());
        })(),
        'romaneio file missing' => PedidoAttachment::factory()->romaneio()->create(['pedido_id' => $pedido->id, 'path' => 'x/sumiu.pdf']),
    };

    $before = finalizeEventSnapshot($pedido);

    expect(fn () => $this->action->execute($this->suprimentos, $pedido))
        ->toThrow(function (ValidationException $exception): void {
            expect($exception->errors())->toBe([
                'finalizar' => ['Não foi possível finalizar o pedido. Anexe o romaneio antes de finalizar.'],
            ]);
        });

    expect($pedido->fresh()->status_id)->toBe($this->statuses['entregue']->id)
        ->and(finalizeEventSnapshot($pedido))->toBe($before)
        ->and(finalizacaoCount($pedido))->toBe(0);
})->with(['no attachments', 'only an anexo', 'romaneio file missing']);

test('one present romaneio among missing ones is enough (RF-34)', function () {
    $pedido = finalizePedido('aguardando_entrega');
    PedidoAttachment::factory()->romaneio()->create(['pedido_id' => $pedido->id, 'path' => 'x/sumiu.pdf']);
    finalizeWithRomaneio($pedido);

    $this->action->execute($this->suprimentos, $pedido);

    expect($pedido->fresh()->status_id)->toBe($this->statuses['finalizado']->id);
});

test('obra and gestao are denied with AuthorizationException, nothing changes (RF-36)', function (string $role) {
    $pedido = finalizePedido('entregue');
    finalizeWithRomaneio($pedido);
    $actor = User::factory()->{$role}()->create();

    if ($role === 'obra') {
        $actor->obras()->attach($this->obra->id);
    }

    expect(fn () => $this->action->execute($actor, $pedido))
        ->toThrow(AuthorizationException::class);

    expect($pedido->fresh()->status_id)->toBe($this->statuses['entregue']->id)
        ->and(finalizacaoCount($pedido))->toBe(0);
})->with(['obra', 'gestao']);
