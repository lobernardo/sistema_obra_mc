<?php

use App\Actions\Pedidos\AttachRomaneioAction;
use App\Actions\Pedidos\CreatePedidoAction;
use App\Enums\EventTypeSlug;
use App\Enums\PedidoAttachmentKind;
use App\Exceptions\Pedidos\PedidoTerminalStateException;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Models\PedidoEvent;
use App\Models\User;
use App\Services\PedidoAttachmentStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    seedHistoryEventTypes();
    Storage::fake(PedidoAttachmentStorage::DISK);

    $this->suprimentos = User::factory()->suprimentos()->create();
    $this->obra = Obra::factory()->create();
    $this->action = app(AttachRomaneioAction::class);
});

function romaneioPedido(string $status): Pedido
{
    return Pedido::factory()->create([
        'obra_id' => test()->obra->id,
        'status_id' => test()->statuses[$status]->id,
    ]);
}

function romaneioPdf(string $name = 'qualquer-nome.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, anexoPdfBytes());
}

function romaneioEvents(Pedido $pedido): array
{
    return PedidoEvent::query()
        ->where('pedido_id', $pedido->id)
        ->whereHas('eventType', fn ($query) => $query->where('slug', EventTypeSlug::RomaneioAnexado->value))
        ->orderBy('id')
        ->get()
        ->all();
}

function romaneioStoredFiles(): array
{
    return Storage::disk(PedidoAttachmentStorage::DISK)->allFiles();
}

test('"qualquer-nome.pdf" → 1 romaneio row + 1 romaneio_anexado event carrying the name (RF-30)', function () {
    $pedido = romaneioPedido('aguardando_entrega');

    $attachment = $this->action->execute($this->suprimentos, $pedido, romaneioPdf());

    $events = romaneioEvents($pedido);

    expect($attachment->kind)->toBe(PedidoAttachmentKind::Romaneio)
        ->and($attachment->original_name)->toBe('qualquer-nome.pdf')
        ->and($attachment->mime_type)->toBe('application/pdf')
        ->and($attachment->uploaded_by)->toBe($this->suprimentos->id)
        ->and($pedido->romaneios()->count())->toBe(1)
        ->and($events)->toHaveCount(1)
        ->and($events[0]->new_value)->toBe('qualquer-nome.pdf')
        ->and($events[0]->actor_id)->toBe($this->suprimentos->id)
        ->and(Storage::disk(PedidoAttachmentStorage::DISK)->get($attachment->path))->toBe(anexoPdfBytes())
        ->and($attachment->path)->not->toContain('qualquer')
        ->and($pedido->fresh()->status_id)->toBe($this->statuses['aguardando_entrega']->id);
});

test('2 uploads → 2 romaneio rows + 2 events, nothing replaced (RF-32)', function () {
    $pedido = romaneioPedido('em_compra_preparacao');

    $first = $this->action->execute($this->suprimentos, $pedido, romaneioPdf('romaneio-1.pdf'));
    $second = $this->action->execute($this->suprimentos, $pedido, UploadedFile::fake()->createWithContent('romaneio-2.png', anexoPngBytes()));

    expect($pedido->romaneios()->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and(array_map(fn (PedidoEvent $event) => $event->new_value, romaneioEvents($pedido)))->toBe(['romaneio-1.pdf', 'romaneio-2.png'])
        ->and(romaneioStoredFiles())->toHaveCount(2);
});

test('an upload on an active status or Entregue is accepted (RF-32, RF-37)', function (string $status) {
    $pedido = romaneioPedido($status);

    $this->action->execute($this->suprimentos, $pedido, UploadedFile::fake()->createWithContent('romaneio.jpg', anexoJpegBytes()));

    expect($pedido->romaneios()->count())->toBe(1)
        ->and(romaneioEvents($pedido))->toHaveCount(1)
        ->and($pedido->fresh()->status_id)->toBe($this->statuses[$status]->id);
})->with(['solicitado', 'em_analise', 'em_compra_preparacao', 'aguardando_entrega', 'entregue']);

test('an upload on Cancelado or Finalizado → 409, 0 rows and no new file (RF-32)', function (string $status) {
    $pedido = romaneioPedido($status);

    expect(fn () => $this->action->execute($this->suprimentos, $pedido, romaneioPdf()))
        ->toThrow(PedidoTerminalStateException::class);

    expect(PedidoAttachment::query()->count())->toBe(0)
        ->and(romaneioEvents($pedido))->toBe([])
        ->and(romaneioStoredFiles())->toBe([]);
})->with(['cancelado', 'finalizado']);

test('a stale instance that became Finalizado is refused by the locked re-read, leaving no file', function () {
    $pedido = romaneioPedido('entregue');
    $stale = Pedido::query()->with('status')->findOrFail($pedido->id);
    Pedido::query()->whereKey($pedido->id)->update(['status_id' => $this->statuses['finalizado']->id]);

    expect(fn () => $this->action->execute($this->suprimentos, $stale, romaneioPdf()))
        ->toThrow(PedidoTerminalStateException::class);

    expect(PedidoAttachment::query()->count())->toBe(0)
        ->and(romaneioStoredFiles())->toBe([]);
});

test('types outside PDF, JPG, PNG are rejected with 422 (RF-30, RF-32)', function (string $name, Closure $bytes) {
    $pedido = romaneioPedido('aguardando_entrega');

    expect(fn () => $this->action->execute($this->suprimentos, $pedido, UploadedFile::fake()->createWithContent($name, $bytes())))
        ->toThrow(function (ValidationException $exception) use ($name): void {
            expect($exception->errors())->toHaveKey('romaneio')
                ->and($exception->errors()['romaneio'][0])->toContain($name)
                ->and($exception->errors()['romaneio'][0])->toContain('PDF, JPG ou PNG');
        });

    expect(PedidoAttachment::query()->count())->toBe(0)
        ->and(romaneioEvents($pedido))->toBe([])
        ->and(romaneioStoredFiles())->toBe([]);
})->with([
    'docx' => ['romaneio.docx', fn () => anexoDocxBytes()],
    'webp' => ['romaneio.webp', fn () => anexoWebpBytes()],
    'pdf with html bytes' => ['romaneio.pdf', fn () => anexoHtmlBytes()],
]);

test('a file above 10 MB is rejected with 422', function () {
    $pedido = romaneioPedido('aguardando_entrega');

    expect(fn () => $this->action->execute($this->suprimentos, $pedido, UploadedFile::fake()->createWithContent('grande.pdf', anexoPdfBytes(PedidoAttachmentStorage::MAX_BYTES + 1))))
        ->toThrow(ValidationException::class, 'excede 10 MB');

    expect(PedidoAttachment::query()->count())->toBe(0);
});

test('a missing file is rejected with 422', function () {
    expect(fn () => $this->action->execute($this->suprimentos, romaneioPedido('entregue'), null))
        ->toThrow(ValidationException::class, 'Selecione o arquivo do romaneio.');
});

test('obra and gestao actors are denied and write nothing (RF-31)', function (string $role) {
    $pedido = romaneioPedido('aguardando_entrega');
    $actor = User::factory()->{$role}()->create();

    if ($role === 'obra') {
        $actor->obras()->attach($this->obra->id);
    }

    expect(fn () => $this->action->execute($actor, $pedido, romaneioPdf()))
        ->toThrow(AuthorizationException::class);

    expect(PedidoAttachment::query()->count())->toBe(0)
        ->and(romaneioEvents($pedido))->toBe([])
        ->and(romaneioStoredFiles())->toBe([]);
})->with(['obra', 'gestao']);

test('a failure of the event insert leaves 0 rows and removes the file (RNF-02)', function () {
    $pedido = romaneioPedido('aguardando_entrega');
    EventType::query()->where('slug', EventTypeSlug::RomaneioAnexado->value)->delete();

    expect(fn () => $this->action->execute($this->suprimentos, $pedido, romaneioPdf()))
        ->toThrow(QueryException::class);

    expect(PedidoAttachment::query()->count())->toBe(0)
        ->and(romaneioStoredFiles())->toBe([]);
});

test('a creation-time attachment named "romaneio.pdf" never counts as romaneio (RF-31)', function () {
    $requester = User::factory()->obra()->create();
    $requester->obras()->attach($this->obra->id);

    $pedido = app(CreatePedidoAction::class)->execute($requester, [
        'obra_selection' => (string) $this->obra->id,
        'descricao' => 'Cimento',
        'needed_at' => '2026-10-01',
        'anexos' => [romaneioPdf('romaneio.pdf')],
    ]);

    expect($pedido->attachments()->sole()->kind)->toBe(PedidoAttachmentKind::Anexo)
        ->and($pedido->romaneios()->count())->toBe(0)
        ->and(romaneioEvents($pedido))->toBe([]);
});
