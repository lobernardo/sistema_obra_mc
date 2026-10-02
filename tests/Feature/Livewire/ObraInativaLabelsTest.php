<?php

use App\Livewire\Associacoes\Index as AssociacoesIndex;
use App\Livewire\Gestao\Dashboard;
use App\Livewire\Gestao\TodosPedidos as GestaoTodosPedidos;
use App\Livewire\Obra\Acompanhamento;
use App\Livewire\Obras\Index as ObrasIndex;
use App\Livewire\Suprimentos\TodosPedidos as SuprimentosTodosPedidos;
use App\Models\Obra;
use App\Models\User;
use Livewire\Livewire;

/**
 * obras-ativacao-exclusao UI-01 (list) and UI-07: an inactive obra carries
 * the "INATIVA" badge in `/obras` and in the current associations of
 * `/associacoes`, and is labelled "<nome> (inativa)" in the obra filter
 * selects — where it stays listed and selectable.
 */
function obraInativaRowOf(string $html, string $attribute, int $obraId): string
{
    preg_match('/<(?:tr|li)[^>]*'.$attribute.'="'.$obraId.'".*?<\/(?:tr|li)>/s', $html, $row);

    expect($row)->not->toBeEmpty("No row with {$attribute}={$obraId}.");

    return $row[0];
}

/**
 * @return array<string, string> option value => trimmed option text
 */
function obraInativaFilterOptions(string $html): array
{
    preg_match('/<select[^>]*id="obraId".*?<\/select>/s', $html, $select);

    expect($select)->not->toBeEmpty('No obra filter select found.');

    preg_match_all('/<option value="([^"]*)"[^>]*>\s*([^<]*?)\s*<\/option>/', $select[0], $options, PREG_SET_ORDER);

    return collect($options)->mapWithKeys(fn (array $option) => [$option[1] => html_entity_decode($option[2])])->all();
}

beforeEach(function () {
    $this->activeObra = Obra::factory()->emAndamento()->create(['name' => 'Obra Ativa']);
    $this->inactiveObra = Obra::factory()->emAndamento()->inactive()->create(['name' => 'Obra Parada']);
});

test('the /obras list shows INATIVA only on the inactive obra row (UI-01)', function () {
    $html = Livewire::actingAs(User::factory()->gestao()->create())->test(ObrasIndex::class)->html();

    $inactiveRow = obraInativaRowOf($html, 'data-obra-id', $this->inactiveObra->id);
    $activeRow = obraInativaRowOf($html, 'data-obra-id', $this->activeObra->id);

    expect($inactiveRow)->toContain('INATIVA')->toContain('data-obra-inativa');
    expect($activeRow)->not->toContain('INATIVA')->not->toContain('data-obra-inativa');
});

test('/associacoes shows INATIVA next to an inactive obra in the current associations (UI-07)', function () {
    $target = User::factory()->obra()->create(['name' => 'Usuária Alvo']);
    $target->obras()->attach([$this->activeObra->id, $this->inactiveObra->id]);

    $html = Livewire::actingAs(User::factory()->suprimentos()->create())->test(AssociacoesIndex::class)->html();

    expect(obraInativaRowOf($html, 'data-associated-obra', $this->inactiveObra->id))
        ->toContain('INATIVA')
        ->toContain('data-obra-inativa')
        ->toContain('data-testid="remove-association"');
    expect(obraInativaRowOf($html, 'data-associated-obra', $this->activeObra->id))
        ->not->toContain('INATIVA');
});

test('the listing and dashboard obra filters label the inactive obra "(inativa)" and keep it selectable (UI-07)', function (string $role, string $component) {
    $actor = User::factory()->{$role}()->create();

    if ($role === 'obra') {
        $actor->obras()->attach([$this->activeObra->id, $this->inactiveObra->id]);
    }

    $options = obraInativaFilterOptions(Livewire::actingAs($actor)->test($component)->html());

    expect($options[(string) $this->inactiveObra->id])->toBe('Obra Parada (inativa)');
    expect($options[(string) $this->activeObra->id])->toBe('Obra Ativa');

    Livewire::actingAs($actor)->test($component)
        ->set('obraId', $this->inactiveObra->id)
        ->assertHasNoErrors()
        ->assertSet('obraId', $this->inactiveObra->id);
})->with([
    'Obra Acompanhamento' => ['obra', Acompanhamento::class],
    'Suprimentos TodosPedidos' => ['suprimentos', SuprimentosTodosPedidos::class],
    'Gestão TodosPedidos' => ['gestao', GestaoTodosPedidos::class],
    'Gestão Dashboard' => ['gestao', Dashboard::class],
]);
