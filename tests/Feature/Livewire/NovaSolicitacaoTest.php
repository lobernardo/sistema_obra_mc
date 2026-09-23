<?php

use App\Domain\Pedidos\DataPrevistaCalculator;
use App\Enums\EventTypeSlug;
use App\Livewire\Pedidos\NovaSolicitacao;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach(function () {
    seedWorkflowStatuses();
    EventType::factory()->criacaoPedido()->create();
});

/**
 * The option labels of the obra select, in DOM order.
 *
 * @return list<string>
 */
function novaSolicitacaoObraOptions(string $html): array
{
    preg_match('/<select id="obra_selection".*?<\/select>/s', $html, $select);
    preg_match_all('/<option value="[^"]*">([^<]*)<\/option>/', $select[0] ?? '', $options);

    return array_map(fn (string $label): string => html_entity_decode(trim($label)), $options[1]);
}

/**
 * @return array{last_value: int|string, is_called: bool}
 */
function novaSolicitacaoSequenceState(): array
{
    return (array) DB::selectOne('select last_value, is_called from pedido_code_sequence');
}

dataset('creating papéis', ['obra', 'suprimentos']);

dataset('empty state per papel', [
    'obra' => ['obra', 'Nenhuma obra ativa está associada ao seu usuário. Fale com a Gestão ou com Suprimentos.', 'obra.pedidos.index'],
    'suprimentos' => ['suprimentos', 'Nenhuma obra ativa está associada ao seu usuário. Fale com a Gestão ou associe-se em Associações.', 'suprimentos.pedidos.index'],
]);

test('the obra select lists exactly the associated active obras by name, then "Outra" (RF-02)', function (string $role) {
    $requester = User::factory()->{$role}()->create();
    $obraA = Obra::factory()->create(['name' => 'Aurora']);
    $obraB = Obra::factory()->aIniciar()->create(['name' => 'Bela Vista']);
    $obraC = Obra::factory()->concluida()->create(['name' => 'Centro Concluído']);
    Obra::factory()->create(['name' => 'Delta Alheia']);
    $requester->obras()->attach([$obraB->id, $obraC->id, $obraA->id]);

    $html = Livewire::actingAs($requester)->test(NovaSolicitacao::class)->html();

    expect(novaSolicitacaoObraOptions($html))->toBe(['Selecione uma obra', 'Aurora', 'Bela Vista', 'Outra']);
})->with('creating papéis');

test('"Referência" is rendered only while "Outra" is selected (RF-04, UI-01)', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    Livewire::actingAs($requester)->test(NovaSolicitacao::class)
        ->assertDontSeeHtml('id="obra_reference"')
        ->set('obra_selection', 'outra')
        ->assertSeeHtml('id="obra_reference"')
        ->assertSeeHtml('maxlength="255"')
        ->set('obra_selection', (string) $obra->id)
        ->assertDontSeeHtml('id="obra_reference"');
});

test('the form labels are exactly those of UI-01', function () {
    $requester = User::factory()->obra()->create();
    $requester->obras()->attach(Obra::factory()->create()->id);

    Livewire::actingAs($requester)->test(NovaSolicitacao::class)
        ->set('obra_selection', 'outra')
        ->assertSeeHtml('<label for="obra_selection" class="form-label">Obra</label>')
        ->assertSeeHtml('<label for="obra_reference" class="form-label">Referência <span class="font-normal text-text-muted">(opcional)</span></label>')
        ->assertSeeHtml('<label for="descricao" class="form-label">Descrição</label>')
        ->assertSeeHtml('<label for="needed_at" class="form-label">Preciso para</label>')
        ->assertSeeHtml('<dt class="form-label">Data da solicitação</dt>')
        ->assertSeeHtml('<dt class="form-label">Data prevista</dt>')
        ->assertDontSee('Data necessária')
        ->assertDontSee('Itens e quantidades');
});

test('the dates preview shows today and the computed Data prevista (RF-10, UI-01)', function () {
    $this->travelTo(Carbon::parse('2026-09-21 12:00:00'));

    $requester = User::factory()->obra()->create();
    $requester->obras()->attach(Obra::factory()->create()->id);

    Livewire::actingAs($requester)->test(NovaSolicitacao::class)
        ->assertSeeHtml('<dd data-field="requested_at" class="text-sm text-text">21/09/2026</dd>')
        ->assertSeeHtml('<dd data-field="data_prevista" class="text-sm text-text">24/09/2026</dd>');
});

test('Data da solicitação is the local São Paulo day, not the UTC day (RF-45)', function () {
    $this->travelTo(Carbon::parse('2026-09-22T01:30:00Z'));

    $requester = User::factory()->obra()->create();
    $requester->obras()->attach(Obra::factory()->create()->id);

    Livewire::actingAs($requester)->test(NovaSolicitacao::class)
        ->assertSeeHtml('<dd data-field="requested_at" class="text-sm text-text">21/09/2026</dd>');
});

test('the only "dias" text is the explanatory note rendered from DIAS_UTEIS (RF-11)', function () {
    $requester = User::factory()->obra()->create();
    $requester->obras()->attach(Obra::factory()->create()->id);

    $html = Livewire::actingAs($requester)->test(NovaSolicitacao::class)
        ->assertSee('Calculada automaticamente: '.DataPrevistaCalculator::DIAS_UTEIS.' dias úteis após a data da solicitação.')
        ->html();

    expect(substr_count($html, 'dias'))->toBe(1);
});

test('a valid submission creates the pedido and shows the code, the Data prevista and the papel listing link (UI-01)', function (string $role, string $listingRoute) {
    $this->travelTo(Carbon::parse('2026-09-21 12:00:00'));

    $requester = User::factory()->{$role}()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    $component = Livewire::actingAs($requester)->test(NovaSolicitacao::class)
        ->set('obra_selection', (string) $obra->id)
        ->set('needed_at', '2026-10-01')
        ->set('descricao', 'Cimento e areia')
        ->call('submit')
        ->assertHasNoErrors();

    $pedido = Pedido::query()->sole();

    $component->assertSet('code', $pedido->code)
        ->assertSee($pedido->code)
        ->assertSee('Data prevista: 24/09/2026')
        ->assertSeeHtml('href="'.route($listingRoute).'"')
        ->assertSet('obra_selection', '')
        ->assertSet('descricao', '');

    expect($pedido->obra_id)->toBe($obra->id);
    expect($pedido->requester_id)->toBe($requester->id);
    expect($pedido->events()->where('event_type_id', EventType::query()->where('slug', EventTypeSlug::CriacaoPedido->value)->value('id'))->count())->toBe(1);
})->with([
    'obra' => ['obra', 'obra.pedidos.index'],
    'suprimentos' => ['suprimentos', 'suprimentos.pedidos.index'],
]);

test('"Outra" with a reference creates a pedido without obra (RF-04)', function (string $role) {
    $requester = User::factory()->{$role}()->create();
    $requester->obras()->attach(Obra::factory()->create()->id);

    Livewire::actingAs($requester)->test(NovaSolicitacao::class)
        ->set('obra_selection', 'outra')
        ->set('obra_reference', '  Galpão provisório  ')
        ->set('needed_at', '2026-10-01')
        ->set('descricao', 'Telhas')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSee('PED-');

    $pedido = Pedido::query()->sole();
    expect($pedido->obra_id)->toBeNull();
    expect($pedido->obra_reference)->toBe('Galpão provisório');
})->with('creating papéis');

test('an associated obra with status A iniciar is offered and accepts a new solicitação (RF-03)', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->aIniciar()->create();
    $requester->obras()->attach($obra->id);

    Livewire::actingAs($requester)->test(NovaSolicitacao::class)
        ->assertSee($obra->name)
        ->set('obra_selection', (string) $obra->id)
        ->set('needed_at', '2026-07-01')
        ->set('descricao', 'Cimento e areia')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSee('PED-');

    expect(Pedido::query()->where('obra_id', $obra->id)->count())->toBe(1);
});

test('with zero eligible obras the papel-aware empty state replaces the form (RF-07, F-17)', function (string $role, string $message, string $listingRoute, bool $hasConcluidaObra) {
    $requester = User::factory()->{$role}()->create();

    if ($hasConcluidaObra) {
        $requester->obras()->attach(Obra::factory()->concluida()->create()->id);
    }

    Livewire::actingAs($requester)->test(NovaSolicitacao::class)
        ->assertSeeHtml('<p role="status" class="alert-info">'.$message.'</p>')
        ->assertSee('Voltar')
        ->assertSeeHtml('href="'.route($listingRoute).'"')
        ->assertDontSeeHtml('<form')
        ->assertDontSeeHtml('<option')
        ->assertDontSee('Outra')
        ->assertDontSee('Enviar solicitação');
})->with('empty state per papel')->with(['only Concluído obras' => true, 'no associations' => false]);

test('a forged "Outra" submit in the empty state fails on obra_id and consumes no code (RF-07)', function (string $role, string $message) {
    $requester = User::factory()->{$role}()->create();
    $sequence = novaSolicitacaoSequenceState();

    Livewire::actingAs($requester)->test(NovaSolicitacao::class)
        ->set('obra_selection', 'outra')
        ->set('needed_at', '2026-07-01')
        ->set('descricao', 'Itens forjados')
        ->call('submit')
        ->assertHasErrors(['obra_id'])
        ->assertSet('code', null);

    expect(Pedido::query()->count())->toBe(0);
    expect(novaSolicitacaoSequenceState())->toEqual($sequence);
})->with('empty state per papel');

test('a forged Concluído obra is rejected without creating a pedido or history', function () {
    $requester = User::factory()->obra()->create();
    $activeObra = Obra::factory()->create();
    $inactiveObra = Obra::factory()->concluida()->create();
    $requester->obras()->attach([$activeObra->id, $inactiveObra->id]);
    $sequence = novaSolicitacaoSequenceState();

    Livewire::actingAs($requester)->test(NovaSolicitacao::class)
        ->set('obra_selection', (string) $inactiveObra->id)
        ->set('needed_at', '2026-07-01')
        ->set('descricao', 'Cimento e areia')
        ->call('submit')
        ->assertHasErrors(['obra_id'])
        ->assertSee('A obra informada está inativa e não recebe novas solicitações.')
        ->assertSet('code', null);

    $this->assertDatabaseCount('pedidos', 0);
    $this->assertDatabaseCount('pedido_events', 0);
    expect(novaSolicitacaoSequenceState())->toEqual($sequence);
});

test('an obra_selection outside the requester association is rejected even when set directly', function (string $role) {
    $requester = User::factory()->{$role}()->create();
    $requester->obras()->attach(Obra::factory()->create()->id);
    $unassociatedObra = Obra::factory()->create();

    Livewire::actingAs($requester)->test(NovaSolicitacao::class)
        ->set('obra_selection', (string) $unassociatedObra->id)
        ->set('needed_at', '2026-07-01')
        ->set('descricao', 'Cimento e areia')
        ->call('submit')
        ->assertHasErrors(['obra_id'])
        ->assertSee('A obra informada não está associada ao solicitante.');

    expect(Pedido::query()->count())->toBe(0);
})->with('creating papéis');

test('missing fields are rejected server-side with the PT-BR messages', function (string $field, string $key, string $message) {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    Livewire::actingAs($requester)->test(NovaSolicitacao::class)
        ->set('obra_selection', (string) $obra->id)
        ->set('needed_at', '2026-07-01')
        ->set('descricao', 'Cimento e areia')
        ->set($field, '')
        ->call('submit')
        ->assertHasErrors([$key])
        ->assertSee($message);

    expect(Pedido::query()->count())->toBe(0);
})->with([
    'obra' => ['obra_selection', 'obra_id', 'Selecione a obra.'],
    'Preciso para' => ['needed_at', 'needed_at', 'Informe a data em Preciso para.'],
    'Descrição' => ['descricao', 'descricao', 'Informe a descrição.'],
]);

test('gestao is denied the component on mount (RF-01)', function () {
    Livewire::actingAs(User::factory()->gestao()->create())
        ->test(NovaSolicitacao::class)
        ->assertForbidden();
});

test('a forged submit by gestao is denied with 403 and writes nothing (RF-01)', function () {
    $obraUser = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $obraUser->obras()->attach($obra->id);
    $sequence = novaSolicitacaoSequenceState();

    $component = Livewire::actingAs($obraUser)->test(NovaSolicitacao::class);

    $this->actingAs(User::factory()->gestao()->create());

    $component->set('obra_selection', 'outra')
        ->set('needed_at', '2026-07-01')
        ->set('descricao', 'Itens forjados')
        ->call('submit')
        ->assertForbidden();

    expect(Pedido::query()->count())->toBe(0);
    expect(novaSolicitacaoSequenceState())->toEqual($sequence);
});

test('both Nova Solicitação routes answer 200 for their papel (CT-05)', function (string $role, string $routeName) {
    $requester = User::factory()->{$role}()->create();
    $requester->obras()->attach(Obra::factory()->create()->id);

    $this->actingAs($requester)->get(route($routeName))->assertOk()->assertSee('Enviar solicitação');
})->with([
    'obra' => ['obra', 'obra.nova-solicitacao'],
    'suprimentos' => ['suprimentos', 'suprimentos.nova-solicitacao'],
]);

test('the Suprimentos Nova Solicitação route answers 403 for other papéis and redirects a guest (CT-05)', function () {
    $this->get(route('suprimentos.nova-solicitacao'))->assertRedirect(route('login'));

    foreach (['obra', 'gestao'] as $role) {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('suprimentos.nova-solicitacao'))
            ->assertForbidden();
    }
});

test('both routes bind the papel-neutral component behind create-pedido (CT-05)', function () {
    $obraRoute = Route::getRoutes()->getByName('obra.nova-solicitacao');
    $suprimentosRoute = Route::getRoutes()->getByName('suprimentos.nova-solicitacao');

    expect($suprimentosRoute->uri())->toBe('suprimentos/nova-solicitacao');
    expect($suprimentosRoute->gatherMiddleware())->toContain('auth', 'active', 'can:is-suprimentos', 'can:create-pedido');
    expect($obraRoute->gatherMiddleware())->toContain('auth', 'active', 'can:is-obra', 'can:create-pedido');
    expect(ltrim($obraRoute->getActionName(), '\\'))->toStartWith(NovaSolicitacao::class);
    expect(ltrim($suprimentosRoute->getActionName(), '\\'))->toStartWith(NovaSolicitacao::class);
});
