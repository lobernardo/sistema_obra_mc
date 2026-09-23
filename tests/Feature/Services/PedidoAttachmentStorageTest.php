<?php

use App\Enums\PedidoAttachmentKind;
use App\Models\Pedido;
use App\Services\PedidoAttachmentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake(PedidoAttachmentStorage::DISK);
});

/**
 * @return array<string, mixed>
 */
function inspectAttachment(string $name, string $bytes, PedidoAttachmentKind $kind = PedidoAttachmentKind::Anexo): array
{
    return app(PedidoAttachmentStorage::class)->inspect(
        UploadedFile::fake()->createWithContent($name, $bytes),
        $kind,
        'anexos.0',
    );
}

function expectAttachmentRefused(string $name, string $bytes, PedidoAttachmentKind $kind = PedidoAttachmentKind::Anexo): void
{
    try {
        inspectAttachment($name, $bytes, $kind);

        test()->fail('A ValidationException was expected.');
    } catch (ValidationException $exception) {
        expect($exception->status)->toBe(422);
        expect(array_keys($exception->errors()))->toBe(['anexos.0']);
    }
}

dataset('allowed anexos', [
    'jpg' => ['foto.jpg', fn () => anexoJpegBytes(), 'jpg', 'image/jpeg'],
    'jpeg normalized to jpg' => ['foto.JPEG', fn () => anexoJpegBytes(), 'jpg', 'image/jpeg'],
    'png' => ['planta.png', fn () => anexoPngBytes(), 'png', 'image/png'],
    'webp' => ['foto.webp', fn () => anexoWebpBytes(), 'webp', 'image/webp'],
    'pdf' => ['orcamento.pdf', fn () => anexoPdfBytes(), 'pdf', 'application/pdf'],
    'docx' => ['memorial.docx', fn () => anexoDocxBytes(), 'docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'xlsx' => ['lista.xlsx', fn () => anexoXlsxBytes(), 'xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
]);

test('every allowed general attachment type is accepted, detected from its bytes (RF-14)', function (string $name, string $bytes, string $extension, string $mime) {
    $inspection = inspectAttachment($name, $bytes);

    expect($inspection)->toBe([
        'mime' => $mime,
        'extension' => $extension,
        'size' => strlen($bytes),
        'display_name' => $name,
    ]);
})->with('allowed anexos');

test('a romaneio accepts PDF, JPG and PNG (RF-30)', function (string $name, Closure $bytes) {
    expect(inspectAttachment($name, $bytes(), PedidoAttachmentKind::Romaneio)['display_name'])->toBe($name);
})->with([
    ['romaneio.pdf', fn () => anexoPdfBytes()],
    ['romaneio.jpg', fn () => anexoJpegBytes()],
    ['romaneio.png', fn () => anexoPngBytes()],
]);

test('a romaneio refuses DOCX and WEBP (RF-32)', function (string $name, Closure $bytes) {
    expectAttachmentRefused($name, $bytes(), PedidoAttachmentKind::Romaneio);
})->with([
    ['romaneio.docx', fn () => anexoDocxBytes()],
    ['romaneio.webp', fn () => anexoWebpBytes()],
]);

test('spoofed and forbidden files are refused with 422 (RF-14, RNF-01)', function (string $name, Closure $bytes) {
    expectAttachmentRefused($name, $bytes());
})->with([
    '.pdf with PNG bytes' => ['nota.pdf', fn () => anexoPngBytes()],
    '.png with HTML bytes' => ['imagem.png', fn () => anexoHtmlBytes()],
    '.jpg with PDF bytes' => ['foto.jpg', fn () => anexoPdfBytes()],
    '.docx with plain ZIP bytes' => ['memorial.docx', fn () => anexoZipBytes()],
    '.svg' => ['desenho.svg', fn () => anexoSvgBytes()],
    '.txt' => ['notas.txt', fn () => 'apenas texto'],
    '.heic' => ['foto.heic', fn () => "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic"],
    '.zip' => ['pacote.zip', fn () => anexoZipBytes()],
    '.exe' => ['instalador.exe', fn () => "MZ\x90\x00".str_repeat("\x00", 60)],
    '.html' => ['pagina.html', fn () => anexoHtmlBytes()],
    'no extension' => ['arquivo', fn () => anexoPdfBytes()],
]);

test('the refusal names the offending file', function () {
    try {
        inspectAttachment('nota.pdf', anexoPngBytes());

        $this->fail('A ValidationException was expected.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['anexos.0'][0])
            ->toBe('O arquivo «nota.pdf» não é de um tipo permitido (JPG, PNG, WEBP, PDF, DOCX ou XLSX).');
    }
});

test('10 485 760 bytes are accepted and 10 485 761 are refused (RF-14)', function () {
    expect(PedidoAttachmentStorage::MAX_BYTES)->toBe(10_485_760);

    expect(inspectAttachment('limite.pdf', anexoPdfBytes(10_485_760))['size'])->toBe(10_485_760);

    try {
        inspectAttachment('grande.pdf', anexoPdfBytes(10_485_761));

        $this->fail('A ValidationException was expected.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['anexos.0'][0])->toBe('O arquivo «grande.pdf» excede 10 MB.');
    }
});

test('an empty file is refused', function () {
    try {
        inspectAttachment('vazio.pdf', '');

        $this->fail('A ValidationException was expected.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['anexos.0'][0])->toBe('O arquivo «vazio.pdf» está vazio.');
    }
});

test('the display name keeps only a safe last path segment (RF-16)', function (string $original, string $expected) {
    expect(app(PedidoAttachmentStorage::class)->sanitizeDisplayName($original, 'pdf'))->toBe($expected);
})->with([
    'path traversal' => ['../../etc/passwd.pdf', 'passwd.pdf'],
    'windows path' => ['C:\\Users\\joao\\nota fiscal.pdf', 'nota fiscal.pdf'],
    'control characters' => ["nota\x00\x07\nfiscal.pdf", 'notafiscal.pdf'],
    'unsafe characters' => ['<script>"a";|b.pdf', 'scriptab.pdf'],
    'accents kept' => ['orçamento ação (1).pdf', 'orçamento ação (1).pdf'],
    'only dots' => ['...', 'anexo.pdf'],
    'blank' => ['', 'anexo.pdf'],
]);

test('a long display name is bounded to 150 characters keeping the extension', function () {
    $name = app(PedidoAttachmentStorage::class)->sanitizeDisplayName(str_repeat('a', 300).'.pdf', 'pdf');

    expect(mb_strlen($name))->toBe(150);
    expect($name)->toEndWith('.pdf');
});

test('store writes the bytes under a random server-generated path (RF-16, RNF-01)', function () {
    $pedido = Pedido::factory()->create();
    $file = UploadedFile::fake()->createWithContent('../../etc/passwd.pdf', anexoPdfBytes());

    $storage = app(PedidoAttachmentStorage::class);
    $path = $storage->store($pedido, $file, 'pdf');

    expect($path)->toMatch('/^\d+\/[0-9a-f]{40}\.(jpg|png|webp|pdf|docx|xlsx)$/');
    expect($path)->toStartWith($pedido->id.'/');
    expect($path)->not->toContain('passwd');
    expect($path)->not->toContain('etc');
    expect($storage->exists($path))->toBeTrue();
    Storage::disk(PedidoAttachmentStorage::DISK)->assertExists($path);
    expect(Storage::disk(PedidoAttachmentStorage::DISK)->get($path))->toBe(anexoPdfBytes());

    expect($storage->store($pedido, $file, 'pdf'))->not->toBe($path);
});

test('deleteQuietly removes the given files and ignores missing ones (RNF-02)', function () {
    $pedido = Pedido::factory()->create();
    $storage = app(PedidoAttachmentStorage::class);
    $path = $storage->store($pedido, UploadedFile::fake()->createWithContent('a.pdf', anexoPdfBytes()), 'pdf');

    $storage->deleteQuietly([$path, $pedido->id.'/inexistente.pdf']);

    expect($storage->exists($path))->toBeFalse();
});

test('the pedido_anexos disk is private, not served and rooted outside every public or served root (RF-16, RF-20)', function () {
    $disk = config('filesystems.disks.pedido_anexos');

    expect($disk['driver'])->toBe('local');
    expect($disk)->not->toHaveKey('serve');
    expect($disk)->not->toHaveKey('url');
    expect($disk['visibility'])->toBe('private');

    $source = file_get_contents(config_path('filesystems.php'));
    expect($source)->toContain("env('PEDIDO_ANEXOS_ROOT', storage_path('app/pedido-anexos'))");

    $defaultRoot = storage_path('app/pedido-anexos');
    foreach ([storage_path('app/private'), storage_path('app/public'), public_path()] as $forbidden) {
        expect(str_starts_with($defaultRoot.'/', rtrim($forbidden, '/').'/'))->toBeFalse();
    }
});

test('the Livewire temporary upload directory stays on the private local disk (RNF-01)', function () {
    expect(config('livewire.temporary_file_upload.disk'))->toBeNull();
    expect(config('filesystems.default'))->toBe('local');
    expect(config('filesystems.disks.local.root'))->toBe(storage_path('app/private'));
});
