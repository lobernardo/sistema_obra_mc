<?php

use App\Livewire\Gestao\TodosPedidos as GestaoTodosPedidos;
use App\Livewire\Obra\Acompanhamento;
use App\Livewire\Suprimentos\TodosPedidos as SuprimentosTodosPedidos;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use Livewire\Livewire;

/**
 * T09/T10 (RF-11, RF-12, RF-13, UI-01, UI-05, CT-06): `x-pedido-table` is the
 * single component behind the three listings, so every assertion below is
 * repeated for `Obra\Acompanhamento`, `Suprimentos\TodosPedidos` and
 * `Gestao\TodosPedidos`. The props contract (`:pedidos :show-route
 * :empty-message`) is unchanged — the call sites are untouched.
 */
const ITEMS_DESCRIPTION_300 = 'Cimento CP II sacos de cinquenta quilos areia media lavada brita numero um vergalhao de aco CA cinquenta bitola dez milimetros arame recozido numero dezoito pregos com cabeca duas polegadas tabuas de pinus para formas escoras metalicas telas soldadas Q cento e trinta e seis e lonas plasticas pretas.';

/**
 * Seeds the lookup rows the three listings render and returns the obra used
 * by every pedido, so the Obra actor can be associated with it.
 *
 * @return array{obra: Obra, status: Status, priority: Priority}
 */
function pedidoTableFixtures(): array
{
    return [
        'obra' => Obra::factory()->create(['name' => 'Residencial Aurora']),
        'status' => Status::factory()->solicitado()->create(),
        'priority' => Priority::factory()->normal()->create(),
    ];
}

/**
 * Builds an authenticated actor for the listing under test and returns the
 * Livewire component class to exercise.
 *
 * @return array{class-string, User}
 */
function pedidoTableListing(string $listing, Obra $obra): array
{
    return match ($listing) {
        'obra' => [Acompanhamento::class, tap(User::factory()->obra()->create(), fn (User $user) => $user->obras()->attach($obra->id))],
        'suprimentos' => [SuprimentosTodosPedidos::class, User::factory()->suprimentos()->create()],
        'gestao' => [GestaoTodosPedidos::class, User::factory()->gestao()->create()],
    };
}

dataset('listings', ['obra', 'suprimentos', 'gestao']);

test('the Itens column truncates to at most 90 visible characters and carries the full text in title', function (string $listing) {
    $fixtures = pedidoTableFixtures();
    [$component, $actor] = pedidoTableListing($listing, $fixtures['obra']);

    $this->actingAs($actor);

    expect(mb_strlen(ITEMS_DESCRIPTION_300))->toBe(300);

    Pedido::factory()->create([
        'obra_id' => $fixtures['obra']->id,
        'status_id' => $fixtures['status']->id,
        'priority_id' => $fixtures['priority']->id,
        'items_description' => ITEMS_DESCRIPTION_300,
    ]);

    $html = Livewire::test($component)->html();

    // The header is present and positioned immediately after "Obra".
    expect($html)->toContain('<th>Itens</th>');
    expect(strpos($html, '<th>Itens</th>'))->toBeGreaterThan(strpos($html, '<th>Obra</th>'));

    // The full, untruncated text is the `title` of every Itens cell.
    expect($html)->toContain('title="'.ITEMS_DESCRIPTION_300.'"');

    // Every rendered Itens cell shows at most 90 characters.
    preg_match_all('/data-field="items"[^>]*>(.*?)</s', $html, $matches);
    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $visibleText) {
        $visibleText = html_entity_decode(trim($visibleText), ENT_QUOTES, 'UTF-8');
        expect(mb_strlen($visibleText))->toBeLessThanOrEqual(90)
            ->and($visibleText)->not->toBe(ITEMS_DESCRIPTION_300);
    }
})->with('listings');

test('the Solicitado em column renders requested_at as d/m/Y', function (string $listing) {
    $fixtures = pedidoTableFixtures();
    [$component, $actor] = pedidoTableListing($listing, $fixtures['obra']);

    $this->actingAs($actor);

    Pedido::factory()->create([
        'obra_id' => $fixtures['obra']->id,
        'status_id' => $fixtures['status']->id,
        'priority_id' => $fixtures['priority']->id,
        'requested_at' => '2026-03-07 14:22:00',
    ]);

    Livewire::test($component)
        ->assertSee('Solicitado em')
        ->assertSee('07/03/2026');
})->with('listings');

test('the empty state spans the ten rendered columns', function (string $listing) {
    $fixtures = pedidoTableFixtures();
    [$component, $actor] = pedidoTableListing($listing, $fixtures['obra']);

    $this->actingAs($actor);

    $html = Livewire::test($component)->html();

    expect(substr_count($html, '<th>'))->toBe(10);
    expect($html)->toContain('colspan="10"');
})->with('listings');

test('the same pedido code is rendered in both the table and the card variant', function (string $listing) {
    $fixtures = pedidoTableFixtures();
    [$component, $actor] = pedidoTableListing($listing, $fixtures['obra']);

    $this->actingAs($actor);

    $pedido = Pedido::factory()->create([
        'obra_id' => $fixtures['obra']->id,
        'status_id' => $fixtures['status']->id,
        'priority_id' => $fixtures['priority']->id,
    ]);

    $html = Livewire::test($component)->html();

    [$tableMarkup, $cardMarkup] = pedidoTableRenderingPaths($html);

    expect($tableMarkup)->toContain('<tr wire:key="pedido-'.$pedido->id.'"')
        ->toContain('data-pedido-code="'.$pedido->code.'"');

    expect($cardMarkup)->toContain('wire:key="pedido-card-'.$pedido->id.'"')
        ->toContain('data-pedido-code="'.$pedido->code.'"')
        ->toContain('data-status="solicitado"')
        ->toContain('data-atraso=');
})->with('listings');

test('the empty message renders exactly once per rendering path', function (string $listing) {
    $fixtures = pedidoTableFixtures();
    [$component, $actor] = pedidoTableListing($listing, $fixtures['obra']);

    $this->actingAs($actor);

    $html = Livewire::test($component)->html();

    $message = $listing === 'obra'
        ? 'Nenhum pedido encontrado para as suas obras.'
        : 'Nenhum pedido encontrado.';

    [$tableMarkup, $cardMarkup] = pedidoTableRenderingPaths($html);

    expect(substr_count($tableMarkup, $message))->toBe(1);
    expect(substr_count($cardMarkup, $message))->toBe(1);
})->with('listings');

/**
 * Splits the rendered HTML into the table path (`hidden md:block`) and the
 * card path (`md:hidden`) so each can be asserted independently.
 *
 * @return array{string, string}
 */
function pedidoTableRenderingPaths(string $html): array
{
    $cardListStart = strpos($html, 'data-testid="pedido-card-list"');
    expect($cardListStart)->not->toBeFalse();

    $tableStart = strpos($html, '<table class="data-table">');
    expect($tableStart)->not->toBeFalse();

    return [
        substr($html, $tableStart, $cardListStart - $tableStart),
        substr($html, $cardListStart),
    ];
}
