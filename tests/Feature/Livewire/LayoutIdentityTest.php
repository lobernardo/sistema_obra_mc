<?php

use App\Models\Obra;
use App\Models\User;

/**
 * UI-07 / UI-08 / UI-15 / UI-16 / UI-25: the authenticated layout
 * (`resources/views/layouts/app.blade.php`) is a white, topbar-only shell
 * built on the design tokens, branded exclusively through `config('app.name')`
 * and free of any MC Inteligência reference.
 */
beforeEach(function () {
    seedWorkflowStatuses();
});

/**
 * @return array{nav: string, links: list<string>}
 */
function primaryNavigation(string $html): array
{
    preg_match('/<nav aria-label="Navegação principal".*?<\/nav>/s', $html, $nav);

    expect($nav)->not->toBeEmpty('the primary navigation landmark is missing');

    preg_match_all('/<a\s[^>]*>(.*?)<\/a>/s', $nav[0], $links);

    return ['nav' => $nav[0], 'links' => array_map('trim', $links[1])];
}

function layoutHeader(string $html): string
{
    preg_match('/<header.*?<\/header>/s', $html, $header);

    expect($header)->not->toBeEmpty('the <header> landmark is missing');

    return $header[0];
}

test('gestao sees exactly 6 nav links: Dashboard, Kanban, Todos os Pedidos, Usuários, Obras and Associações (UI-08)', function () {
    $this->actingAs(User::factory()->gestao()->create());

    $html = $this->get(route('gestao.dashboard'))->assertOk()->getContent();

    expect(primaryNavigation($html)['links'])->toBe(['Dashboard', 'Kanban', 'Todos os Pedidos', 'Usuários', 'Obras', 'Associações']);
});

test('obra and suprimentos never see the Usuários link (UI-08)', function (string $role, string $routeName) {
    $this->actingAs(User::factory()->{$role}()->create());

    $html = $this->get(route($routeName))->assertOk()->getContent();

    $navigation = primaryNavigation($html);

    expect($navigation['links'])->not->toContain('Usuários')
        ->and($navigation['nav'])->not->toContain(route('gestao.usuarios.index'));
})->with([
    'obra' => ['obra', 'obra.pedidos.index'],
    'suprimentos' => ['suprimentos', 'suprimentos.kanban'],
]);

test('the active nav item is marked with aria-current and the active token class', function () {
    $this->actingAs(User::factory()->gestao()->create());

    $html = $this->get(route('gestao.kanban'))->assertOk()->getContent();

    $nav = primaryNavigation($html)['nav'];

    expect($nav)->toMatch('/<a[^>]*class="[^"]*nav-link-active[^"]*"[^>]*aria-current="page"[^>]*>\s*Kanban\s*<\/a>/s');
    expect(substr_count($nav, 'aria-current="page"'))->toBe(1);
    expect(substr_count($nav, 'nav-link-active'))->toBe(1);
});

test('the topbar is a white surface built on tokens, with no dark or sky palette classes (UI-04, UI-08)', function (string $role, string $routeName) {
    $this->actingAs(User::factory()->{$role}()->create());

    $html = $this->get(route($routeName))->assertOk()->getContent();

    $header = layoutHeader($html);

    expect($header)->toContain('bg-surface')
        ->toContain('border-border')
        ->not->toContain('bg-slate-900')
        ->not->toContain('bg-slate-800')
        ->not->toContain('sky-')
        ->not->toMatch('/shadow(?!-sm)[-\w]*/');

    expect($html)->not->toContain('<aside');
})->with([
    'gestao' => ['gestao', 'gestao.dashboard'],
    'obra' => ['obra', 'obra.pedidos.index'],
    'suprimentos' => ['suprimentos', 'suprimentos.kanban'],
]);

test('authenticated pages contain no MC Inteligência reference (UI-16, UI-25)', function (string $role, string $routeName) {
    $this->actingAs(User::factory()->{$role}()->create());

    $this->get(route($routeName))
        ->assertOk()
        ->assertDontSee('MC Inteligência')
        ->assertDontSee('Tecnologia por');
})->with([
    'gestao' => ['gestao', 'gestao.dashboard'],
    'obra' => ['obra', 'obra.pedidos.index'],
    'suprimentos' => ['suprimentos', 'suprimentos.kanban'],
]);

test('the brand text and <title> come from config(app.name), never a hardcoded name (UI-15)', function () {
    config(['app.name' => 'Marca Configurada']);

    $this->actingAs(User::factory()->gestao()->create());

    $html = $this->get(route('gestao.dashboard'))->assertOk()->getContent();

    expect(layoutHeader($html))->toMatch('/<a[^>]*href="'.preg_quote(route('home'), '/').'"[^>]*>\s*Marca Configurada\s*<\/a>/s');
    expect($html)->toContain('<title>Marca Configurada</title>');
    expect($html)->not->toContain('Albuquerque Engenharia');
});

test('the role badge, user name and logout control use the neutral token classes', function () {
    $this->actingAs(User::factory()->gestao()->create(['name' => 'Gestora Principal']));

    $header = layoutHeader($this->get(route('gestao.dashboard'))->assertOk()->getContent());

    expect($header)->toContain('badge badge-neutral')
        ->toContain('text-text-muted')
        ->toMatch('/<button[^>]*class="[^"]*btn-secondary[^"]*"[^>]*>\s*Sair\s*<\/button>/s')
        ->not->toContain('rounded-full');
});

/**
 * RF-30 (T28): the Suprimentos navigation gains the Visão Geral entry, active
 * only on the new screen, while the landing page after login stays the Kanban.
 * UI-08 (obras-associacoes-cadastro-convites): Obras and Associações follow
 * Todos os Pedidos, provisional until the slice-3 sidebar (RF-08).
 */
test('the suprimentos menu renders five entries with Visão Geral, Obras and Associações among them', function () {
    $this->actingAs(User::factory()->suprimentos()->create());

    $html = $this->get(route('suprimentos.kanban'))->assertOk()->getContent();

    expect(primaryNavigation($html)['links'])->toBe(['Visão Geral', 'Kanban', 'Todos os Pedidos', 'Obras', 'Associações']);
});

test('the Visão Geral entry is marked active only on its own route', function () {
    $this->actingAs(User::factory()->suprimentos()->create());

    $visaoGeralNav = primaryNavigation($this->get(route('suprimentos.visao-geral'))->assertOk()->getContent())['nav'];

    expect($visaoGeralNav)->toMatch('/<a[^>]*class="[^"]*nav-link-active[^"]*"[^>]*aria-current="page"[^>]*>\s*Visão Geral\s*<\/a>/s');
    expect(substr_count($visaoGeralNav, 'aria-current="page"'))->toBe(1);

    foreach (['suprimentos.kanban', 'suprimentos.pedidos.index'] as $routeName) {
        $nav = primaryNavigation($this->get(route($routeName))->assertOk()->getContent())['nav'];

        expect($nav)->not->toMatch('/<a[^>]*nav-link-active[^>]*>\s*Visão Geral/s');
        expect(substr_count($nav, 'aria-current="page"'))->toBe(1);
    }
});

test('a suprimentos user still lands on the Kanban from /home', function () {
    $this->actingAs(User::factory()->suprimentos()->create());

    $this->get('/home')->assertRedirect(route('suprimentos.kanban'));
});

/**
 * UI-08: the obra papel toolbar is untouched by the Obras/Associações entries.
 */
test('the obra menu stays Acompanhamento and + Nova Solicitação, without Obras or Associações (UI-08)', function () {
    $this->actingAs(User::factory()->obra()->create());

    $navigation = primaryNavigation($this->get(route('obra.pedidos.index'))->assertOk()->getContent());

    expect($navigation['links'])->toBe(['Acompanhamento', '+ Nova Solicitação'])
        ->and($navigation['nav'])->not->toContain(route('obras.index'))
        ->and($navigation['nav'])->not->toContain(route('associacoes.index'));
});

test('the Obras entry is active only under obras.* and Associações only under associacoes.* (UI-08)', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $obra = Obra::factory()->create();
    $this->actingAs($user);

    foreach (['obras.index' => [], 'obras.create' => [], 'obras.edit' => ['obra' => $obra]] as $routeName => $parameters) {
        $nav = primaryNavigation($this->get(route($routeName, $parameters))->assertOk()->getContent())['nav'];

        expect($nav)->toMatch('/<a[^>]*class="[^"]*nav-link-active[^"]*"[^>]*aria-current="page"[^>]*>\s*Obras\s*<\/a>/s');
        expect(substr_count($nav, 'aria-current="page"'))->toBe(1);
    }

    $associacoesNav = primaryNavigation($this->get(route('associacoes.index'))->assertOk()->getContent())['nav'];

    expect($associacoesNav)->toMatch('/<a[^>]*class="[^"]*nav-link-active[^"]*"[^>]*aria-current="page"[^>]*>\s*Associações\s*<\/a>/s');
    expect($associacoesNav)->not->toMatch('/<a[^>]*nav-link-active[^>]*>\s*Obras\s*<\/a>/s');
    expect(substr_count($associacoesNav, 'aria-current="page"'))->toBe(1);

    $homeRoute = $role === 'gestao' ? 'gestao.pedidos.index' : 'suprimentos.pedidos.index';
    $pedidosNav = primaryNavigation($this->get(route($homeRoute))->assertOk()->getContent())['nav'];

    expect($pedidosNav)->not->toMatch('/<a[^>]*nav-link-active[^>]*>\s*(Obras|Associações)\s*<\/a>/s');
})->with(['gestao', 'suprimentos']);
