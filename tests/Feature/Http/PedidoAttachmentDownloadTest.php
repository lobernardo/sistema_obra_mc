<?php

use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Models\PedidoEvent;
use App\Models\User;
use App\Services\PedidoAttachmentStorage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function () {
    $this->statusId = seedWorkflowStatuses()['solicitado']->id;
    Storage::fake(PedidoAttachmentStorage::DISK);

    $this->obra = Obra::factory()->create();
    $this->obraUser = User::factory()->obra()->create();
    $this->obraUser->obras()->attach($this->obra->id);
    $this->pedido = Pedido::factory()->create(['obra_id' => $this->obra->id, 'requester_id' => $this->obraUser->id, 'status_id' => $this->statusId]);
    $this->attachment = storedDownloadAttachment($this->pedido, 'nota fiscal.pdf');
});

function downloadBytes(): string
{
    return anexoPdfBytes().'%conteudo-secreto-do-anexo';
}

function storedDownloadAttachment(Pedido $pedido, string $name, string $mime = 'application/pdf'): PedidoAttachment
{
    $path = $pedido->id.'/'.bin2hex(random_bytes(20)).'.pdf';
    Storage::disk(PedidoAttachmentStorage::DISK)->put($path, downloadBytes());

    return PedidoAttachment::factory()->create([
        'pedido_id' => $pedido->id,
        'path' => $path,
        'original_name' => $name,
        'mime_type' => $mime,
        'size_bytes' => strlen(downloadBytes()),
    ]);
}

function downloadUrl(Pedido $pedido, PedidoAttachment|int $attachment): string
{
    return route('pedidos.anexos.download', ['pedido' => $pedido->id, 'attachment' => is_int($attachment) ? $attachment : $attachment->id]);
}

function expectDownloaded(TestResponse $response, string $name): void
{
    $response->assertOk();
    expect($response->streamedContent())->toBe(downloadBytes());
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment');
    expect($response->headers->get('Content-Disposition'))->toContain($name);
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    expect($response->headers->get('Cache-Control'))->toContain('private');
}

function expectNoFileLeak(TestResponse $response): void
{
    $body = $response->baseResponse instanceof StreamedResponse
        ? $response->streamedContent()
        : (string) $response->getContent();

    expect($body)->not->toContain('conteudo-secreto-do-anexo');
    expect($body)->not->toContain('nota fiscal');
    expect((string) $response->headers->get('Content-Disposition'))->not->toContain('attachment');
}

test('authorized viewers download the exact bytes with the safe headers (RF-17)', function (string $who) {
    $actor = match ($who) {
        'associated obra' => $this->obraUser,
        'suprimentos' => User::factory()->suprimentos()->create(),
        'gestao' => User::factory()->gestao()->create(),
    };

    expectDownloaded($this->actingAs($actor)->get(downloadUrl($this->pedido, $this->attachment)), 'nota fiscal.pdf');
})->with(['associated obra', 'suprimentos', 'gestao']);

test('the requester of an "Outra" pedido downloads its attachment (RF-17, RF-40)', function () {
    $requester = User::factory()->obra()->create();
    $requester->obras()->attach(Obra::factory()->create()->id);
    $pedido = Pedido::factory()->outra('Galpão')->create(['requester_id' => $requester->id, 'status_id' => $this->statusId]);
    $attachment = storedDownloadAttachment($pedido, 'nota fiscal.pdf');

    expectDownloaded($this->actingAs($requester)->get(downloadUrl($pedido, $attachment)), 'nota fiscal.pdf');
});

test('an obra user of another obra gets 403 without bytes (RF-18)', function () {
    $other = User::factory()->obra()->create();
    $other->obras()->attach(Obra::factory()->create()->id);

    $response = $this->actingAs($other)->get(downloadUrl($this->pedido, $this->attachment));

    $response->assertForbidden();
    expectNoFileLeak($response);
});

test('another obra user on an "Outra" pedido gets 403 without bytes (RF-18, RF-40)', function () {
    $requester = User::factory()->obra()->create();
    $pedido = Pedido::factory()->outra()->create(['requester_id' => $requester->id, 'status_id' => $this->statusId]);
    $attachment = storedDownloadAttachment($pedido, 'nota fiscal.pdf');

    $response = $this->actingAs($this->obraUser)->get(downloadUrl($pedido, $attachment));

    $response->assertForbidden();
    expectNoFileLeak($response);
});

test('a guest is redirected to /login without bytes (RF-18)', function () {
    $response = $this->get(downloadUrl($this->pedido, $this->attachment));

    $response->assertRedirect(route('login'));
    expectNoFileLeak($response);
});

test('an inactive user is logged out without bytes (RF-18)', function () {
    $inactive = User::factory()->suprimentos()->inactive()->create();

    $response = $this->actingAs($inactive)->get(downloadUrl($this->pedido, $this->attachment));

    $response->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
    expectNoFileLeak($response);
});

test('unknown id, attachment of another pedido and missing file → 404 without bytes or name (RF-18, RNF-09)', function (string $case) {
    $url = match ($case) {
        'unknown attachment id' => downloadUrl($this->pedido, 999999),
        'attachment of another pedido' => downloadUrl(
            Pedido::factory()->create(['obra_id' => $this->obra->id, 'status_id' => $this->statusId]),
            $this->attachment,
        ),
        'file missing from storage' => (function () {
            Storage::disk(PedidoAttachmentStorage::DISK)->delete($this->attachment->path);

            return downloadUrl($this->pedido, $this->attachment);
        })->call($this),
    };

    $response = $this->actingAs(User::factory()->suprimentos()->create())->get($url);

    $response->assertNotFound();
    expectNoFileLeak($response);
})->with(['unknown attachment id', 'attachment of another pedido', 'file missing from storage']);

test('the same URL is refused in an unauthorized session (RF-18)', function () {
    $url = downloadUrl($this->pedido, $this->attachment);

    $this->actingAs($this->obraUser)->get($url)->assertOk();

    $other = User::factory()->obra()->create();
    $this->actingAs($other)->get($url)->assertForbidden();
});

test('the download writes nothing (RF-17)', function () {
    $counts = [PedidoAttachment::query()->count(), PedidoEvent::query()->count()];

    $this->actingAs($this->obraUser)->get(downloadUrl($this->pedido, $this->attachment))->assertOk();

    expect([PedidoAttachment::query()->count(), PedidoEvent::query()->count()])->toBe($counts);
});

test('the public storage paths and the signed storage.local route never serve an attachment (RF-16)', function () {
    $disk = Storage::build(config('filesystems.disks.pedido_anexos'));
    $path = 'rf16-'.bin2hex(random_bytes(8)).'/'.bin2hex(random_bytes(20)).'.pdf';
    $disk->put($path, downloadBytes());

    try {
        $this->actingAs(User::factory()->gestao()->create());

        $responses = [
            $this->get('/storage/'.$path),
            $this->get('/storage/../pedido-anexos/'.$path),
            $this->get(URL::temporarySignedRoute('storage.local', now()->addMinutes(5), ['path' => $path])),
            $this->get(URL::temporarySignedRoute('storage.local', now()->addMinutes(5), ['path' => '../pedido-anexos/'.$path])),
            $this->get(URL::temporarySignedRoute('storage.local', now()->addMinutes(5), ['path' => $path]).'x'),
        ];

        foreach ($responses as $response) {
            expect($response->getStatusCode())->not->toBe(200);
            expectNoFileLeak($response);
        }
    } finally {
        $disk->deleteDirectory(dirname($path));
    }
});

test('the download route carries auth and active', function () {
    $middleware = app('router')->getRoutes()->getByName('pedidos.anexos.download')->gatherMiddleware();

    expect($middleware)->toContain('auth');
    expect($middleware)->toContain('active');
    expect(app('router')->getRoutes()->getByName('pedidos.anexos.download')->uri())->toBe('pedidos/{pedido}/anexos/{attachment}');
});
