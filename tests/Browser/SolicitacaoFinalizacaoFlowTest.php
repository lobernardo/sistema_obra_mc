<?php

use App\Enums\EventTypeSlug;
use App\Enums\PedidoAttachmentKind;
use App\Enums\StatusSlug;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\User;
use App\Services\PedidoAttachmentStorage;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Browser\Support\ParsesMultipartUploads;

/**
 * solicitacao-historico-finalizacao T30 (RF-01, RF-35, UI-01, UI-02, UI-05,
 * UI-06, UI-07): the slice end to end in a real browser, against the demo
 * dataset.
 *
 * 1. Obra creates a pedido "Outra" with a PDF and a PNG selected together in
 *    the multi-file input (uploaded one after the other) and sees the code
 *    and a `dd/mm/aaaa` Data prevista;
 * 2. adds an observation and marks the pedido as entregue (two steps);
 * 3. Suprimentos sees "Finalizar pedido" disabled with its hint, and a
 *    forged `$wire.finalizarPedido()` — which a disabled button cannot
 *    prevent — shows the exact RF-35 message in `role="alert"`;
 * 4. uploads a romaneio, finalizes and sees the "Finalizado" badge and the
 *    history lines "Romaneio anexado" and "Pedido finalizado";
 * 5. (F-03) the demo Suprimentos user, associated with the active demo obras
 *    by the seeder, opens Nova Solicitação from the sidebar, creates a
 *    solicitação for one of them and finds it in Todos os Pedidos.
 *
 * File uploads go through {@see ParsesMultipartUploads}: the plugin's
 * in-process server drops multipart bodies, which a real server would parse.
 *
 * Role switches go through the sidebar logout ("Sair"): the plugin serves every
 * request from one in-process application, so the session guard keeps the
 * previous user until `logout()` clears it (see `DemoRoteiroTest`).
 */
const SOLICITACAO_FLOW_RF35_MESSAGE = 'Não foi possível finalizar o pedido. Anexe o romaneio antes de finalizar.';

/**
 * Selects several files at once in a `multiple` file input, the way a user
 * picking two files in the OS dialog does: one `change` event carrying both
 * files. `setInputFiles` of the plugin takes a single path, so the files are
 * built in the page from their bytes.
 *
 * @param  list<array{name: string, type: string, bytes: string}>  $files
 */
function solicitacaoFlowSelectFiles($page, string $inputSelector, array $files): void
{
    $payload = json_encode(array_map(fn (array $file): array => [
        'name' => $file['name'],
        'type' => $file['type'],
        'base64' => base64_encode($file['bytes']),
    ], $files), JSON_THROW_ON_ERROR);

    $page->script(<<<JS
        (() => {
            const input = document.querySelector('{$inputSelector}');
            const transfer = new DataTransfer();
            for (const file of {$payload}) {
                const bytes = Uint8Array.from(atob(file.base64), (char) => char.charCodeAt(0));
                transfer.items.add(new File([bytes], file.name, { type: file.type }));
            }
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        })()
        JS);
}

/**
 * Waits until `wire:model` bindings are live on the element with `$id`,
 * so typed text is not wiped by the deferred Alpine sync.
 */
function solicitacaoFlowWaitForModel($page, string $id): void
{
    $page->page()->waitForFunction("() => document.getElementById('{$id}')?._x_model !== undefined");
    $page->page()->evaluate('() => new Promise((resolve) => setTimeout(resolve, 50))');
}

test('obra and suprimentos create, deliver, attach the romaneio and finalize through the UI', function () {
    Storage::fake(PedidoAttachmentStorage::DISK);
    ParsesMultipartUploads::register();
    $this->seed(DemoSeeder::class);

    $reference = 'Galpão provisório '.Str::random(4);
    $descricao = 'Lona plástica 4x5 m e 30 abraçadeiras — fluxo E2E '.Str::random(6);
    $neededAt = now()->addDays(10)->toDateString();
    $observacao = 'Material conferido no canteiro '.Str::random(4);

    $login = function ($page, string $email, string $expectedPath) {
        $page->assertPathIs('/login');
        solicitacaoFlowWaitForModel($page, 'email');

        return $page
            ->type('email', $email)
            ->type('password', 'password')
            ->press('Entrar')
            ->assertPathIs($expectedPath);
    };

    $logout = fn ($page) => logoutThroughSidebar($page);

    // (1) Obra creates a pedido "Outra" with a PDF and a PNG.
    $page = $login($this->visit('/login'), 'obra.demo@example.com', '/obra/pedidos');

    $page->page()->goto(route('obra.nova-solicitacao'));
    solicitacaoFlowWaitForModel($page, 'descricao');
    $page->select('obra_selection', 'outra');
    $page->page()->locator('#obra_reference')->waitFor(['state' => 'visible']);

    solicitacaoFlowSelectFiles($page, '#anexos', [
        ['name' => 'orcamento.pdf', 'type' => 'application/pdf', 'bytes' => anexoPdfBytes()],
        ['name' => 'foto-canteiro.png', 'type' => 'image/png', 'bytes' => anexoPngBytes()],
    ]);
    $page->page()->waitForFunction('() => document.querySelectorAll("[data-anexos-list] [data-anexo-name]").length === 2');
    $page->assertSeeIn('[data-anexos-list]', 'orcamento.pdf')->assertSeeIn('[data-anexos-list]', 'foto-canteiro.png');

    solicitacaoFlowWaitForModel($page, 'obra_reference');
    $page->type('obra_reference', $reference)
        ->type('descricao', $descricao)
        ->type('needed_at', $neededAt)
        ->press('Enviar solicitação')
        ->assertSee('Solicitação criada com sucesso!');

    $code = trim((string) $page->text('[role="status"] strong'));
    $pedido = Pedido::query()->where('code', $code)->firstOrFail();

    expect($pedido->obra_id)->toBeNull();
    expect($pedido->obra_reference)->toBe($reference);
    expect($pedido->attachments()->where('kind', PedidoAttachmentKind::Anexo->value)->count())->toBe(2);
    expect((string) $page->text('.alert-success[role="status"]'))->toMatch('/Data prevista: \d{2}\/\d{2}\/\d{4}/');
    $page->assertSeeIn('.alert-success[role="status"]', 'Data prevista: '.$pedido->dataPrevistaLabel());

    // (2) Observation, then "Marcar como entregue" with the two-step confirmation.
    $page->page()->goto(route('obra.pedidos.show', $pedido));
    $page->assertSeeIn('@pedido-summary', 'Outra — '.$reference)
        ->assertSeeIn('@pedido-summary', 'orcamento.pdf')
        ->assertSeeIn('@pedido-summary', 'foto-canteiro.png');

    solicitacaoFlowWaitForModel($page, 'observacao');
    $page->type('observacao', $observacao)
        ->click('section[aria-label="Adicionar observação"] button[type="submit"]')
        ->assertSeeIn('section[aria-label="Histórico"]', $observacao)
        ->assertSeeIn('section[aria-label="Histórico"]', 'Observação adicionada');

    $page->click('@entrega-button')
        ->assertPresent('@entrega-confirm-dialog')
        ->click('@entrega-confirm')
        ->assertSee('Pedido marcado como entregue.');

    expect($pedido->fresh()->status->slug)->toBe(StatusSlug::Entregue->value);

    // (3) Suprimentos: Finalizar disabled without a romaneio; the forged call shows RF-35.
    $sup = $login($logout($page), 'suprimentos.demo@example.com', '/suprimentos/pedidos');

    $sup->page()->goto(route('suprimentos.pedidos.show', $pedido));
    $sup->assertButtonDisabled('@finalizar-button')
        ->assertSee('Anexe o romaneio antes de finalizar.');

    $sup->script(<<<'JS'
        (() => {
            const root = document.querySelector('[data-testid="finalizar-button"]').closest('[wire\\:id]');
            window.Livewire.find(root.getAttribute('wire:id')).finalizarPedido();
        })()
        JS);
    $sup->page()->locator('[data-testid="finalizar-error"]')->waitFor(['state' => 'visible']);
    $sup->assertSeeIn('[role="alert"][data-testid="finalizar-error"]', SOLICITACAO_FLOW_RF35_MESSAGE);

    expect($pedido->fresh()->status->slug)->toBe(StatusSlug::Entregue->value);

    // (4) Romaneio, then Finalizar with the two-step confirmation.
    $romaneioPath = tempnam(sys_get_temp_dir(), 'romaneio').'.pdf';
    file_put_contents($romaneioPath, anexoPdfBytes());

    try {
        $sup->attach('#romaneio', $romaneioPath);
        $sup->page()->waitForFunction(<<<'JS'
            () => {
                const root = document.getElementById('romaneio')?.closest('[wire\\:id]');
                return root !== null && ![null, undefined, ''].includes(window.Livewire.find(root.getAttribute('wire:id')).romaneio);
            }
            JS);
        $sup->press('Enviar romaneio');
        $sup->page()->waitForFunction('() => document.querySelector("[data-testid=\'finalizar-button\']")?.disabled === false');
    } finally {
        @unlink($romaneioPath);
    }

    $sup->assertSeeIn('section[aria-label="Histórico"]', 'Romaneio anexado')
        ->assertButtonEnabled('@finalizar-button')
        ->click('@finalizar-button')
        ->assertPresent('@finalizar-confirm-dialog')
        ->click('@finalizar-confirm')
        ->assertSee('Pedido finalizado.')
        ->assertSeeIn('div:has(> div > h1.page-title) > .badge[data-status="finalizado"]', 'Finalizado');

    $historyActions = $sup->script('Array.from(document.querySelectorAll(\'[data-testid="pedido-history-event"] > strong\')).map((el) => el.textContent.trim())');
    expect($historyActions)->toContain('Romaneio anexado')
        ->and($historyActions)->toContain('Pedido finalizado')
        ->and(end($historyActions))->toBe('Pedido finalizado');

    $pedido->refresh();
    expect($pedido->status->slug)->toBe(StatusSlug::Finalizado->value);
    expect($pedido->romaneios()->count())->toBe(1);
    expect($pedido->events()->whereHas('eventType', fn ($query) => $query->where('slug', EventTypeSlug::Finalizacao->value))->count())->toBe(1);

    // (5) F-03: Suprimentos creates from the sidebar for an associated active obra.
    $obra = Obra::query()->where('name', '[DEMO] Obra Alfa')->firstOrFail();
    $suprimentosUser = User::query()->where('email', 'suprimentos.demo@example.com')->firstOrFail();
    expect($suprimentosUser->obras()->active()->whereKey($obra->id)->exists())->toBeTrue();

    $suprimentosDescricao = 'Reposição de EPIs — Suprimentos E2E '.Str::random(6);

    $sup->click('[data-testid="sidebar-nova-solicitacao"]')
        ->assertPathIs('/suprimentos/nova-solicitacao')
        ->assertSee('Nova Solicitação');

    solicitacaoFlowWaitForModel($sup, 'descricao');
    $sup->select('obra_selection', (string) $obra->id)
        ->type('descricao', $suprimentosDescricao)
        ->type('needed_at', $neededAt)
        ->press('Enviar solicitação')
        ->assertSee('Solicitação criada com sucesso!')
        ->assertPresent('[role="status"] a[href$="/suprimentos/pedidos"]');

    $suprimentosCode = trim((string) $sup->text('[role="status"] strong'));
    $suprimentosPedido = Pedido::query()->where('code', $suprimentosCode)->firstOrFail();

    expect($suprimentosPedido->obra_id)->toBe($obra->id);
    expect($suprimentosPedido->requester_id)->toBe($suprimentosUser->id);

    $sup->click('[role="status"] a[href$="/suprimentos/pedidos"]')
        ->assertPathIs('/suprimentos/pedidos')
        ->assertSee('Todos os Pedidos');

    solicitacaoFlowWaitForModel($sup, 'search');
    $sup->type('search', $suprimentosCode)
        ->assertSeeIn('tr[data-pedido-code="'.$suprimentosCode.'"]', $suprimentosCode);

    $sup->assertNoJavascriptErrors();
});
