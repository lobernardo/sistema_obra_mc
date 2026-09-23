<?php

use App\Actions\Pedidos\CreatePedidoAction;
use App\Enums\EventTypeSlug;
use App\Enums\PedidoAttachmentKind;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Models\PedidoEvent;
use App\Models\User;
use App\Services\PedidoAttachmentStorage;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    seedWorkflowStatuses();
    EventType::factory()->criacaoPedido()->create();
    Storage::fake(PedidoAttachmentStorage::DISK);

    $this->requester = User::factory()->obra()->create();
    $this->obra = Obra::factory()->create(['name' => 'Residencial Aurora']);
    $this->requester->obras()->attach($this->obra->id);
});

/**
 * @return array{last_value: int|string, is_called: bool}
 */
function attachmentsSequenceState(): array
{
    return (array) DB::selectOne('select last_value, is_called from pedido_code_sequence');
}

/**
 * @return list<string>
 */
function storedAttachmentFiles(): array
{
    return Storage::disk(PedidoAttachmentStorage::DISK)->allFiles();
}

/**
 * @param  list<UploadedFile>  $anexos
 * @return array<string, mixed>
 */
function creationWithAnexos(Obra $obra, array $anexos): array
{
    return [
        'obra_selection' => (string) $obra->id,
        'descricao' => 'Cimento e areia',
        'needed_at' => '2026-10-01',
        'anexos' => $anexos,
    ];
}

function expectNothingWritten(array $sequence): void
{
    expect(Pedido::query()->count())->toBe(0);
    expect(PedidoEvent::query()->count())->toBe(0);
    expect(PedidoAttachment::query()->count())->toBe(0);
    expect(storedAttachmentFiles())->toBe([]);
    expect(attachmentsSequenceState())->toEqual($sequence);
}

test('3 valid files → 1 pedido, 1 event, 3 anexo rows and 3 stored files (RF-08, RF-14)', function () {
    $files = [
        UploadedFile::fake()->createWithContent('orcamento.pdf', anexoPdfBytes()),
        UploadedFile::fake()->createWithContent('foto da obra.jpg', anexoJpegBytes()),
        UploadedFile::fake()->createWithContent('lista.xlsx', anexoXlsxBytes()),
    ];

    $pedido = app(CreatePedidoAction::class)->execute($this->requester, creationWithAnexos($this->obra, $files));

    expect(Pedido::query()->count())->toBe(1);
    expect($pedido->events()->count())->toBe(1);
    expect($pedido->events()->first()->eventType->slug)->toBe(EventTypeSlug::CriacaoPedido->value);

    $attachments = $pedido->attachments()->get();
    expect($attachments)->toHaveCount(3);
    expect($attachments->pluck('original_name')->all())->toBe(['orcamento.pdf', 'foto da obra.jpg', 'lista.xlsx']);
    expect($attachments->pluck('mime_type')->all())->toBe([
        'application/pdf',
        'image/jpeg',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]);

    foreach ($attachments as $attachment) {
        expect($attachment->kind)->toBe(PedidoAttachmentKind::Anexo);
        expect($attachment->uploaded_by)->toBe($this->requester->id);
        expect($attachment->path)->toMatch('/^'.$pedido->id.'\/[0-9a-f]{40}\.(pdf|jpg|xlsx)$/');
        Storage::disk(PedidoAttachmentStorage::DISK)->assertExists($attachment->path);
    }

    expect($attachments->pluck('size_bytes')->all())->toBe([strlen(anexoPdfBytes()), strlen(anexoJpegBytes()), strlen(anexoXlsxBytes())]);
    expect(Storage::disk(PedidoAttachmentStorage::DISK)->get($attachments[0]->path))->toBe(anexoPdfBytes());
    expect(storedAttachmentFiles())->toHaveCount(3);
});

test('a creation without files writes no attachment', function () {
    $pedido = app(CreatePedidoAction::class)->execute($this->requester, creationWithAnexos($this->obra, []));

    expect($pedido->attachments()->count())->toBe(0);
    expect(storedAttachmentFiles())->toBe([]);
});

test('2 valid + 1 invalid → 422 on anexos.2 naming the file, nothing written, code not consumed (RF-15)', function () {
    $sequence = attachmentsSequenceState();

    try {
        app(CreatePedidoAction::class)->execute($this->requester, creationWithAnexos($this->obra, [
            UploadedFile::fake()->createWithContent('a.pdf', anexoPdfBytes()),
            UploadedFile::fake()->createWithContent('b.png', anexoPngBytes()),
            UploadedFile::fake()->createWithContent('falso.pdf', anexoHtmlBytes()),
        ]));

        $this->fail('A ValidationException was expected.');
    } catch (ValidationException $exception) {
        expect($exception->status)->toBe(422);
        expect(array_keys($exception->errors()))->toBe(['anexos.2']);
        expect($exception->errors()['anexos.2'][0])->toContain('«falso.pdf»');
    }

    expectNothingWritten($sequence);
});

test('11 files → 422, nothing written (RF-14)', function () {
    $sequence = attachmentsSequenceState();
    $files = array_map(
        fn (int $i): UploadedFile => UploadedFile::fake()->createWithContent("anexo-{$i}.pdf", anexoPdfBytes()),
        range(1, 11),
    );

    try {
        app(CreatePedidoAction::class)->execute($this->requester, creationWithAnexos($this->obra, $files));

        $this->fail('A ValidationException was expected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['anexos' => ['Envie no máximo 10 anexos.']]);
    }

    expectNothingWritten($sequence);
});

test('10 files are accepted (RF-14)', function () {
    $files = array_map(
        fn (int $i): UploadedFile => UploadedFile::fake()->createWithContent("anexo-{$i}.pdf", anexoPdfBytes()),
        range(1, 10),
    );

    $pedido = app(CreatePedidoAction::class)->execute($this->requester, creationWithAnexos($this->obra, $files));

    expect($pedido->attachments()->count())->toBe(10);
    expect(storedAttachmentFiles())->toHaveCount(10);
});

test('a failure of the event insert leaves 0 pedidos, events, attachment rows and files (RF-08, RNF-02)', function () {
    EventType::query()->where('slug', EventTypeSlug::CriacaoPedido->value)->delete();

    expect(fn () => app(CreatePedidoAction::class)->execute($this->requester, creationWithAnexos($this->obra, [
        UploadedFile::fake()->createWithContent('a.pdf', anexoPdfBytes()),
        UploadedFile::fake()->createWithContent('b.png', anexoPngBytes()),
    ])))->toThrow(QueryException::class);

    expect(Pedido::query()->count())->toBe(0);
    expect(PedidoEvent::query()->count())->toBe(0);
    expect(PedidoAttachment::query()->count())->toBe(0);
    expect(storedAttachmentFiles())->toBe([]);
});

test('a creation-time attachment named "romaneio.pdf" is classified anexo (RF-31)', function () {
    $pedido = app(CreatePedidoAction::class)->execute($this->requester, creationWithAnexos($this->obra, [
        UploadedFile::fake()->createWithContent('romaneio.pdf', anexoPdfBytes()),
    ]));

    expect($pedido->attachments()->sole()->kind)->toBe(PedidoAttachmentKind::Anexo);
    expect($pedido->romaneios()->count())->toBe(0);
});

test('obra rejections with files attached leave no file (RF-03, RF-07)', function (string $case) {
    $sequence = attachmentsSequenceState();

    $requester = match ($case) {
        'zero obras' => User::factory()->obra()->create(),
        default => $this->requester,
    };
    $obra = match ($case) {
        'not associated' => Obra::factory()->create(),
        'concluída' => tap(Obra::factory()->concluida()->create(), fn (Obra $obra) => $requester->obras()->attach($obra->id)),
        default => $this->obra,
    };

    expect(fn () => app(CreatePedidoAction::class)->execute($requester, creationWithAnexos($obra, [
        UploadedFile::fake()->createWithContent('a.pdf', anexoPdfBytes()),
    ])))->toThrow(ValidationException::class);

    expectNothingWritten($sequence);
})->with(['not associated', 'concluída', 'zero obras']);

test('a non-file entry in anexos is refused', function () {
    $sequence = attachmentsSequenceState();

    expect(fn () => app(CreatePedidoAction::class)->execute($this->requester, creationWithAnexos($this->obra, ['../../etc/passwd'])))
        ->toThrow(ValidationException::class);

    expectNothingWritten($sequence);
});
