<?php

use App\Enums\StatusSlug;
use App\Livewire\Gestao\TodosPedidos as GestaoTodosPedidos;
use App\Livewire\Obra\Acompanhamento;
use App\Livewire\Suprimentos\TodosPedidos as SuprimentosTodosPedidos;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\User;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Slice 3 T09 (RF-10, RF-11, RF-12, RF-13, UI-05): the shared `x-pedido-table`
 * identifies each pedido as "<requester> / <Pedido::obraLabel()>", labels
 * "Descrição" and "Preciso para", and fills "Previsão" from the automatic
 * Data prevista — asserted on the three listings, table and card.
 */
dataset('identification listings', ['obra', 'suprimentos', 'gestao']);

/**
 * The listing component, its actor (an obra actor is associated with the
 * obra) and the user that requests "Outra" pedidos visible to that actor
 * (for Obra, only the actor's own "Outra" pedidos are visible).
 *
 * @return array{component: class-string, actor: User, outraRequester: User}
 */
function pedidoIdentificationListing(string $listing, Obra $obra): array
{
    if ($listing === 'obra') {
        $actor = User::factory()->obra()->create(['name' => 'Maria Souza']);
        $actor->obras()->attach($obra->id);

        return ['component' => Acompanhamento::class, 'actor' => $actor, 'outraRequester' => $actor];
    }

    return [
        'component' => $listing === 'suprimentos' ? SuprimentosTodosPedidos::class : GestaoTodosPedidos::class,
        'actor' => $listing === 'suprimentos' ? User::factory()->suprimentos()->create() : User::factory()->gestao()->create(),
        'outraRequester' => User::factory()->obra()->create(['name' => 'Maria Souza']),
    ];
}

/**
 * @return array{string, string} the table path and the card path of the listing HTML
 */
function pedidoIdentificationPaths(string $html): array
{
    $tableStart = strpos($html, '<table class="data-table">');
    $cardListStart = strpos($html, 'data-testid="pedido-card-list"');

    expect($tableStart)->not->toBeFalse()
        ->and($cardListStart)->not->toBeFalse();

    return [substr($html, $tableStart, $cardListStart - $tableStart), substr($html, $cardListStart)];
}

/**
 * The markup of the table row and of the card of one pedido.
 *
 * @return array{string, string}
 */
function pedidoIdentificationRowAndCard(string $html, Pedido $pedido): array
{
    [$tableMarkup, $cardMarkup] = pedidoIdentificationPaths($html);

    preg_match('/<tr wire:key="pedido-'.$pedido->id.'".*?<\/tr>/s', $tableMarkup, $row);
    preg_match('/<article\s+wire:key="pedido-card-'.$pedido->id.'".*?<\/article>/s', $cardMarkup, $card);

    expect($row)->not->toBeEmpty()
        ->and($card)->not->toBeEmpty();

    return [$row[0], $card[0]];
}

beforeEach(function () {
    $this->solicitadoId = seedWorkflowStatuses()[StatusSlug::Solicitado->value]->id;
    $this->obra = Obra::factory()->create(['name' => 'Residencial Aurora']);
});

test('the requester and the obra appear once per row and once per card', function (string $listing) {
    ['component' => $component, 'actor' => $actor] = pedidoIdentificationListing($listing, $this->obra);
    $requester = User::factory()->obra()->create(['name' => 'João Silva']);
    $pedido = Pedido::factory()->create(['status_id' => $this->solicitadoId, 'obra_id' => $this->obra->id, 'requester_id' => $requester->id]);

    $this->actingAs($actor);

    [$row, $card] = pedidoIdentificationRowAndCard(Livewire::test($component)->html(), $pedido);

    expect(substr_count($row, 'João Silva / Residencial Aurora'))->toBe(1)
        ->and(substr_count($card, 'João Silva / Residencial Aurora'))->toBe(1)
        ->and($row)->toContain('<td data-field="identificacao">João Silva / Residencial Aurora</td>')
        ->and($card)->toContain('data-field="identificacao"');
})->with('identification listings');

test('"Outra" pedidos are identified with and without reference', function (string $listing) {
    ['component' => $component, 'actor' => $actor, 'outraRequester' => $requester] = pedidoIdentificationListing($listing, $this->obra);
    $withoutReference = Pedido::factory()->outra()->create(['status_id' => $this->solicitadoId, 'requester_id' => $requester->id]);
    $withReference = Pedido::factory()->outra('Galpão provisório')->create(['status_id' => $this->solicitadoId, 'requester_id' => $requester->id]);

    $this->actingAs($actor);

    $html = Livewire::test($component)->html();

    [$row, $card] = pedidoIdentificationRowAndCard($html, $withoutReference);
    expect($row)->toContain('Maria Souza / Outra<')
        ->and($card)->toContain('Maria Souza / Outra<');

    $expected = e('Maria Souza / '.$withReference->obraLabel());
    expect($withReference->obraLabel())->toContain('Outra')->toContain('Galpão provisório');

    [$row, $card] = pedidoIdentificationRowAndCard($html, $withReference);
    expect($row)->toContain($expected)
        ->and($card)->toContain($expected);
})->with('identification listings');

test('an inactive requester\'s name is still shown', function (string $listing) {
    ['component' => $component, 'actor' => $actor] = pedidoIdentificationListing($listing, $this->obra);
    $requester = User::factory()->obra()->inactive()->create(['name' => 'Carlos Inativo']);
    $pedido = Pedido::factory()->create(['status_id' => $this->solicitadoId, 'obra_id' => $this->obra->id, 'requester_id' => $requester->id]);

    $this->actingAs($actor);

    [$row, $card] = pedidoIdentificationRowAndCard(Livewire::test($component)->html(), $pedido);

    expect($row)->toContain('Carlos Inativo / Residencial Aurora')
        ->and($card)->toContain('Carlos Inativo / Residencial Aurora');
})->with('identification listings');

test('"Descrição" and "Preciso para" replace "Itens" and "Data necessária"', function (string $listing) {
    ['component' => $component, 'actor' => $actor] = pedidoIdentificationListing($listing, $this->obra);
    Pedido::factory()->create(['status_id' => $this->solicitadoId, 'obra_id' => $this->obra->id]);

    $this->actingAs($actor);

    [$tableMarkup, $cardMarkup] = pedidoIdentificationPaths(Livewire::test($component)->html());

    expect($tableMarkup)->toContain('<th>Descrição</th>')
        ->toContain('<th>Preciso para</th>')
        ->not->toContain('<th>Itens</th>')
        ->not->toContain('<th>Data necessária</th>');

    expect($cardMarkup)->toContain('>Descrição</p>')
        ->toContain('<dt class="text-text-muted">Preciso para</dt>')
        ->not->toContain('Data necessária');
})->with('identification listings');

test('"Previsão" is the automatic Data prevista, never the previsão de entrega', function (string $listing) {
    ['component' => $component, 'actor' => $actor] = pedidoIdentificationListing($listing, $this->obra);

    $this->travelTo(CarbonImmutable::parse('2026-09-21 12:00', LocalTime::TIMEZONE));

    $withoutDelivery = Pedido::factory()->create(['status_id' => $this->solicitadoId, 'obra_id' => $this->obra->id, 'needed_at' => '2026-10-15', 'expected_delivery_at' => null]);
    $withDelivery = Pedido::factory()->create(['status_id' => $this->solicitadoId, 'obra_id' => $this->obra->id, 'needed_at' => '2026-10-15', 'expected_delivery_at' => '2026-09-30']);

    $this->actingAs($actor);

    $html = Livewire::test($component)->html();

    foreach ([$withoutDelivery, $withDelivery] as $pedido) {
        [$row, $card] = pedidoIdentificationRowAndCard($html, $pedido);

        expect($row)->toContain('24/09/2026')->not->toContain('30/09/2026')
            ->and($card)->toContain('24/09/2026')->not->toContain('30/09/2026');
    }

    expect($html)->not->toContain('30/09/2026');
})->with('identification listings');

test('"Outra" and Finalizado pedidos render and Finalizado is a status option (RF-13)', function (string $listing) {
    ['component' => $component, 'actor' => $actor, 'outraRequester' => $requester] = pedidoIdentificationListing($listing, $this->obra);
    $finalizadoStatusId = seedWorkflowStatuses()[StatusSlug::Finalizado->value]->id;

    $pedidos = [
        Pedido::factory()->outra('Galpão provisório')->create(['status_id' => $this->solicitadoId, 'requester_id' => $requester->id]),
        Pedido::factory()->outra()->create(['status_id' => $this->solicitadoId, 'requester_id' => $requester->id]),
        Pedido::factory()->create(['obra_id' => $this->obra->id, 'status_id' => $finalizadoStatusId]),
    ];

    $this->actingAs($actor);

    $html = Livewire::test($component)->assertOk()->html();

    foreach ($pedidos as $pedido) {
        pedidoIdentificationRowAndCard($html, $pedido);
    }

    expect($html)->toMatch('/<option value="'.$finalizadoStatusId.'"[^>]*>Finalizado<\/option>/');
})->with('identification listings');

test('header count, cells per row and empty-state colspan agree', function (string $listing) {
    ['component' => $component, 'actor' => $actor] = pedidoIdentificationListing($listing, $this->obra);

    $this->actingAs($actor);

    $emptyHtml = Livewire::test($component)->html();
    [$emptyTable] = pedidoIdentificationPaths($emptyHtml);
    $headerCount = substr_count($emptyTable, '<th>');

    expect($headerCount)->toBe(10)
        ->and($emptyTable)->toContain('colspan="'.$headerCount.'"');

    $pedido = Pedido::factory()->create(['status_id' => $this->solicitadoId, 'obra_id' => $this->obra->id]);

    [$row] = pedidoIdentificationRowAndCard(Livewire::test($component)->html(), $pedido);

    expect(preg_match_all('/<td\b/', $row))->toBe($headerCount);
})->with('identification listings');

test('the shared table never reads expected_delivery_at', function () {
    expect(file_get_contents(resource_path('views/components/pedido-table.blade.php')))
        ->not->toContain('expected_delivery_at');
});
