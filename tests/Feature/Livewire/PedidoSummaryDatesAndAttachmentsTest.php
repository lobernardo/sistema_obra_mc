<?php

use App\Actions\Pedidos\CreatePedidoAction;
use App\Enums\EventTypeSlug;
use App\Livewire\Suprimentos\PedidoDetalhe as SuprimentosPedidoDetalhe;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * The pedido summary of the three detail screens (UI-03, RF-13, RF-17,
 * RF-47): "Descrição", four distinct dates and the Anexos section.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    seedHistoryEventTypes();

    $this->requester = User::factory()->obra()->create(['name' => 'João Silva']);
    $this->obra = Obra::factory()->create(['name' => 'Residencial Aurora']);
    $this->requester->obras()->attach($this->obra->id);
    $this->suprimentos = User::factory()->suprimentos()->create(['name' => 'Maria Souza']);
    $this->gestao = User::factory()->gestao()->create();
});

/**
 * The rendered HTML of the pedido summary on each detail screen.
 *
 * @return array<string, string>
 */
function summaryHtmlOnEveryScreen(Pedido $pedido): array
{
    $screens = [
        'obra' => [test()->requester, 'obra.pedidos.show'],
        'suprimentos' => [test()->suprimentos, 'suprimentos.pedidos.show'],
        'gestao' => [test()->gestao, 'gestao.pedidos.show'],
    ];

    return collect($screens)
        ->map(function (array $screen) use ($pedido): string {
            [$user, $route] = $screen;
            $html = test()->actingAs($user)->get(route($route, $pedido))->assertOk()->getContent();
            preg_match('/<dl data-testid="pedido-summary".*?<\/dl>/s', $html, $match);

            return $match[0] ?? '';
        })
        ->all();
}

/**
 * The value rendered right after a summary label.
 */
function summaryValue(string $html, string $label): string
{
    preg_match('/<dt[^>]*>'.preg_quote($label, '/').'<\/dt>\s*<dd[^>]*>(.*?)<\/dd>/s', $html, $match);

    return trim(strip_tags($match[1] ?? ''));
}

function createdSummaryPedido(User $requester, int $obraId): Pedido
{
    return app(CreatePedidoAction::class)->execute($requester, [
        'obra_selection' => $obraId,
        'needed_at' => '2026-10-05',
        'descricao' => 'Cimento e areia',
    ]);
}

test('"Descrição" replaces "Itens e quantidades" on the three detail screens (UI-03, F-06)', function () {
    $pedido = createdSummaryPedido($this->requester, $this->obra->id);

    foreach (summaryHtmlOnEveryScreen($pedido) as $screen => $html) {
        expect($html)->toContain('>Descrição</dt>')
            ->not->toContain('Itens e quantidades')
            ->and(summaryValue($html, 'Descrição'))->toBe('Cimento e areia');
    }
});

test('the four date labels are present, distinct and exact (UI-03, RF-13)', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-21T15:00:00Z'));
    $pedido = createdSummaryPedido($this->requester, $this->obra->id);

    foreach (summaryHtmlOnEveryScreen($pedido) as $screen => $html) {
        foreach (['Data da solicitação', 'Preciso para', 'Data prevista', 'Previsão de entrega'] as $label) {
            expect(substr_count($html, '>'.$label.'</dt>'))->toBe(1, "{$screen}: {$label}");
        }

        expect($html)->not->toContain('Data necessária')
            ->and(summaryValue($html, 'Data da solicitação'))->toBe('21/09/2026 12:00')
            ->and(summaryValue($html, 'Preciso para'))->toBe('05/10/2026')
            ->and(summaryValue($html, 'Data prevista'))->toBe('24/09/2026')
            ->and(summaryValue($html, 'Previsão de entrega'))->toBe('—');
    }
});

test('the Data da solicitação is shown in America/Sao_Paulo (RF-47)', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-25T01:30:00Z'));
    $pedido = createdSummaryPedido($this->requester, $this->obra->id);

    foreach (summaryHtmlOnEveryScreen($pedido) as $html) {
        expect(summaryValue($html, 'Data da solicitação'))->toBe('24/09/2026 22:30')
            ->and(summaryValue($html, 'Data prevista'))->toMatch('/^\d{2}\/\d{2}\/\d{4}$/');
    }
});

test('setting the Previsão de entrega leaves the Data prevista unchanged and writes 1 alteracao_previsao (RF-13)', function () {
    $pedido = createdSummaryPedido($this->requester, $this->obra->id);
    $dataPrevista = $pedido->dataPrevistaLabel();

    $this->actingAs($this->suprimentos);

    Livewire::test(SuprimentosPedidoDetalhe::class, ['pedido' => $pedido])
        ->set('expected_delivery_at', '2026-12-20')
        ->call('updatePrevisao')
        ->assertHasNoErrors();

    $pedido->refresh();

    expect($pedido->dataPrevistaLabel())->toBe($dataPrevista)
        ->and($pedido->expected_delivery_at->toDateString())->toBe('2026-12-20')
        ->and($pedido->events()->whereHas('eventType', fn ($query) => $query->where('slug', EventTypeSlug::AlteracaoPrevisao->value))->count())->toBe(1);

    foreach (summaryHtmlOnEveryScreen($pedido) as $html) {
        expect(summaryValue($html, 'Previsão de entrega'))->toBe('20/12/2026')
            ->and(summaryValue($html, 'Data prevista'))->toBe($dataPrevista);
    }
});

test('an anexo and a romaneio are listed with download links and the marker only on the romaneio (UI-03, RF-17)', function () {
    $pedido = createdSummaryPedido($this->requester, $this->obra->id);
    $this->travelTo(CarbonImmutable::parse('2026-09-25T01:30:00Z'));
    $anexo = PedidoAttachment::factory()->for($pedido)->create([
        'original_name' => 'orcamento.pdf',
        'size_bytes' => 2048,
        'uploaded_by' => $this->requester->id,
    ]);
    $romaneio = PedidoAttachment::factory()->romaneio()->for($pedido)->create([
        'original_name' => 'romaneio-1234.pdf',
        'size_bytes' => 3 * 1048576,
        'uploaded_by' => $this->suprimentos->id,
    ]);

    foreach (summaryHtmlOnEveryScreen($pedido) as $screen => $html) {
        preg_match_all('/<li[^>]*data-testid="pedido-attachment".*?<\/li>/s', $html, $items);

        expect($items[0])->toHaveCount(2, $screen)
            ->and($html)->not->toContain('Nenhum anexo.');

        [$anexoItem, $romaneioItem] = $items[0];

        expect($anexoItem)
            ->toContain('href="'.e(route('pedidos.anexos.download', [$pedido, $anexo])).'"')
            ->toContain('orcamento.pdf')
            ->toContain('2 KB · João Silva · 24/09/2026 22:30')
            ->not->toContain('Romaneio')
            ->and($romaneioItem)
            ->toContain('href="'.e(route('pedidos.anexos.download', [$pedido, $romaneio])).'"')
            ->toContain('romaneio-1234.pdf')
            ->toContain('>Romaneio</span>')
            ->toContain('3,0 MB · Maria Souza · 24/09/2026 22:30');
    }
});

test('a pedido without attachments shows "Nenhum anexo."', function () {
    $pedido = createdSummaryPedido($this->requester, $this->obra->id);

    foreach (summaryHtmlOnEveryScreen($pedido) as $html) {
        expect($html)->toContain('Nenhum anexo.');
    }
});
