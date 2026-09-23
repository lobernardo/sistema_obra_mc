<?php

use App\Models\Obra;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\HtmlString;

/**
 * navegacao-sidebar-listagens RF-01..RF-08 / UI-01..UI-03 / CT-02: the
 * authenticated layout renders one white sidebar, the only primary
 * navigation, with the per-papel items of CT-02, one current item per
 * section and the logout form. The sidebar is presentation only: hidden
 * items keep the route's own 403.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
});

function sidebarRegion(string $html): string
{
    preg_match('/<aside\s[^>]*id="sidebar".*?<\/aside>/s', $html, $aside);

    expect($aside)->not->toBeEmpty('the <aside id="sidebar"> region is missing');

    return $aside[0];
}

/**
 * @return list<array{label: string, href: string, tag: string}>
 */
function sidebarLinks(string $html): array
{
    preg_match('/<nav aria-label="Navegação principal".*?<\/nav>/s', sidebarRegion($html), $nav);

    expect($nav)->not->toBeEmpty('the primary navigation is missing from the sidebar');

    preg_match_all('/(<a\s[^>]*>)(.*?)<\/a>/s', $nav[0], $matches, PREG_SET_ORDER);

    return array_map(function (array $match): array {
        preg_match('/href="([^"]*)"/', $match[1], $href);

        return ['label' => trim($match[2]), 'href' => html_entity_decode($href[1] ?? ''), 'tag' => $match[1]];
    }, $matches);
}

/**
 * @return array{obra: string, suprimentos: string, gestao: string}
 */
function pedidosRouteByRole(): array
{
    return [
        'obra' => 'obra.pedidos.index',
        'suprimentos' => 'suprimentos.pedidos.index',
        'gestao' => 'gestao.pedidos.index',
    ];
}

test('each papel gets exactly one Navegação principal landmark, inside the sidebar, and no nav-link anywhere (RF-01, RF-08)', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    $html = $this->get(route(pedidosRouteByRole()[$role]))->assertOk()->getContent();

    expect(substr_count($html, 'aria-label="Navegação principal"'))->toBe(1);
    expect(substr_count(sidebarRegion($html), 'aria-label="Navegação principal"'))->toBe(1);
    expect(substr_count($html, 'id="sidebar"'))->toBe(1);

    preg_match('/<header.*?<\/header>/s', $html, $header);

    expect($header)->not->toBeEmpty();
    expect($header[0])->not->toContain('sidebar-link')
        ->not->toContain('nav-link');
    expect($html)->not->toContain('nav-link');
})->with(['obra', 'suprimentos', 'gestao']);

test('the sidebar items per papel match CT-02 exactly (RF-03)', function (string $role, array $expected) {
    $this->actingAs(User::factory()->{$role}()->create());

    $html = $this->get(route(pedidosRouteByRole()[$role]))->assertOk()->getContent();

    expect(array_column(sidebarLinks($html), 'label'))->toBe($expected);
})->with([
    'obra' => ['obra', ['+ Nova Solicitação', 'Acompanhamento']],
    'suprimentos' => ['suprimentos', ['+ Nova Solicitação', 'Pedidos', 'Visão Geral', 'Kanban', 'Obras', 'Associações']],
    'gestao' => ['gestao', ['Pedidos', 'Dashboard', 'Kanban', 'Obras', 'Associações', 'Usuários']],
]);

test('obra sees no administrative, dashboard or kanban item (RF-03)', function () {
    $this->actingAs(User::factory()->obra()->create());

    $labels = array_column(sidebarLinks($this->get(route('obra.pedidos.index'))->assertOk()->getContent()), 'label');

    foreach (['Obras', 'Associações', 'Usuários', 'Dashboard', 'Kanban'] as $hidden) {
        expect($labels)->not->toContain($hidden);
    }
});

test('gestao never sees + Nova Solicitação nor Visão Geral anywhere in the page (RF-03, RF-07)', function () {
    $this->actingAs(User::factory()->gestao()->create());

    $html = $this->get(route('gestao.pedidos.index'))->assertOk()->getContent();

    expect($html)->not->toContain('+ Nova Solicitação')
        ->not->toContain('Visão Geral')
        ->not->toContain('topbar-nova-solicitacao')
        ->not->toContain('sidebar-nova-solicitacao');
});

test('every sidebar href answers 200 for its papel (RF-02)', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    $links = sidebarLinks($this->get(route(pedidosRouteByRole()[$role]))->assertOk()->getContent());

    expect($links)->not->toBeEmpty();

    foreach ($links as $link) {
        $this->get($link['href'])->assertOk();
    }
})->with(['obra', 'suprimentos', 'gestao']);

test('typed URLs of hidden items keep the route own 403 (RF-02)', function (string $role, string $path) {
    $this->actingAs(User::factory()->{$role}()->create());

    $this->get($path)->assertForbidden();
})->with([
    'obra → usuários' => ['obra', '/gestao/usuarios'],
    'obra → obras' => ['obra', '/obras'],
    'obra → pedidos de suprimentos' => ['obra', '/suprimentos/pedidos'],
    'suprimentos → usuários' => ['suprimentos', '/gestao/usuarios'],
    'suprimentos → dashboard' => ['suprimentos', '/gestao/dashboard'],
    'gestao → nova solicitação de suprimentos' => ['gestao', '/suprimentos/nova-solicitacao'],
    'gestao → nova solicitação de obra' => ['gestao', '/obra/nova-solicitacao'],
]);

test('exactly one item is current, the one of the screen section (RF-04)', function (string $role, string $routeName, ?string $parameter, string $expectedLabel) {
    $user = User::factory()->{$role}()->create();
    $this->actingAs($user);

    $parameters = match ($parameter) {
        'pedido' => [Pedido::factory()->create([
            'requester_id' => $user->id,
            'status_id' => $this->statuses['solicitado']->id,
        ])],
        'obra' => [Obra::factory()->create()],
        'user' => [User::factory()->obra()->create()],
        default => [],
    };

    $html = $this->get(route($routeName, $parameters))->assertOk()->getContent();
    $sidebar = sidebarRegion($html);

    expect(substr_count($html, 'aria-current="page"'))->toBe(1);
    expect(substr_count($sidebar, 'aria-current="page"'))->toBe(1);

    $current = array_values(array_filter(sidebarLinks($html), fn (array $link): bool => str_contains($link['tag'], 'aria-current="page"')));

    expect(array_column($current, 'label'))->toBe([$expectedLabel]);
})->with([
    'obra nova solicitação' => ['obra', 'obra.nova-solicitacao', null, '+ Nova Solicitação'],
    'obra acompanhamento' => ['obra', 'obra.pedidos.index', null, 'Acompanhamento'],
    'obra pedido detalhe' => ['obra', 'obra.pedidos.show', 'pedido', 'Acompanhamento'],
    'suprimentos nova solicitação' => ['suprimentos', 'suprimentos.nova-solicitacao', null, '+ Nova Solicitação'],
    'suprimentos pedidos' => ['suprimentos', 'suprimentos.pedidos.index', null, 'Pedidos'],
    'suprimentos pedido detalhe' => ['suprimentos', 'suprimentos.pedidos.show', 'pedido', 'Pedidos'],
    'suprimentos visão geral' => ['suprimentos', 'suprimentos.visao-geral', null, 'Visão Geral'],
    'suprimentos kanban' => ['suprimentos', 'suprimentos.kanban', null, 'Kanban'],
    'suprimentos obras' => ['suprimentos', 'obras.index', null, 'Obras'],
    'suprimentos obras nova' => ['suprimentos', 'obras.create', null, 'Obras'],
    'suprimentos obras editar' => ['suprimentos', 'obras.edit', 'obra', 'Obras'],
    'suprimentos associações' => ['suprimentos', 'associacoes.index', null, 'Associações'],
    'gestao pedidos' => ['gestao', 'gestao.pedidos.index', null, 'Pedidos'],
    'gestao pedido detalhe' => ['gestao', 'gestao.pedidos.show', 'pedido', 'Pedidos'],
    'gestao dashboard' => ['gestao', 'gestao.dashboard', null, 'Dashboard'],
    'gestao kanban' => ['gestao', 'gestao.kanban', null, 'Kanban'],
    'gestao obras' => ['gestao', 'obras.index', null, 'Obras'],
    'gestao obras nova' => ['gestao', 'obras.create', null, 'Obras'],
    'gestao obras editar' => ['gestao', 'obras.edit', 'obra', 'Obras'],
    'gestao associações' => ['gestao', 'associacoes.index', null, 'Associações'],
    'gestao usuários' => ['gestao', 'gestao.usuarios.index', null, 'Usuários'],
    'gestao usuários novo' => ['gestao', 'gestao.usuarios.create', null, 'Usuários'],
    'gestao usuários editar' => ['gestao', 'gestao.usuarios.edit', 'user', 'Usuários'],
]);

test('the gestao pedido detail marks Pedidos, never Dashboard (RF-04)', function () {
    $this->actingAs(User::factory()->gestao()->create());

    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);

    $links = sidebarLinks($this->get(route('gestao.pedidos.show', $pedido))->assertOk()->getContent());
    $byLabel = array_column($links, 'tag', 'label');

    expect($byLabel['Pedidos'])->toContain('aria-current="page"')->toContain('sidebar-link-active');
    expect($byLabel['Dashboard'])->not->toContain('aria-current')->not->toContain('sidebar-link-active');
});

test('the sidebar holds the POST logout form with the CSRF token and Sair, and logout still redirects to login (RF-05)', function () {
    $this->actingAs(User::factory()->suprimentos()->create());

    $sidebar = sidebarRegion($this->get(route('suprimentos.pedidos.index'))->assertOk()->getContent());

    expect($sidebar)->toMatch('/<form method="POST" action="'.preg_quote(route('logout'), '/').'">.*?<input type="hidden" name="_token" value="[^"]+".*?<button type="submit"[^>]*>\s*Sair\s*<\/button>\s*<\/form>/s');

    $this->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('the sidebar shows the configured brand, the user name and the papel (RF-06)', function () {
    config(['app.name' => 'Marca Configurada']);

    $user = User::factory()->gestao()->create(['name' => 'Gestora Principal']);
    $this->actingAs($user);

    $sidebar = sidebarRegion($this->get(route('gestao.pedidos.index'))->assertOk()->getContent());

    expect($sidebar)->toMatch('/<a[^>]*href="'.preg_quote(route('home'), '/').'"[^>]*>\s*Marca Configurada\s*<\/a>/s')
        ->toContain('Gestora Principal')
        ->toContain($user->role->name);
});

test('a user without a recognised papel gets no item, but the brand and Sair (RF-03)', function () {
    $this->actingAs(User::factory()->create(['name' => 'Sem Papel']));

    $html = view('layouts.app', ['slot' => new HtmlString('')])->render();

    expect(sidebarLinks($html))->toBe([]);
    expect(sidebarRegion($html))->toContain(config('app.name'))
        ->toContain('Sem Papel')
        ->toMatch('/<button type="submit"[^>]*>\s*Sair\s*<\/button>/s');
    expect($html)->not->toContain('topbar-nova-solicitacao');
});

test('obra and suprimentos get the highlighted + Nova Solicitação first in the sidebar and in the top bar (RF-07)', function (string $role, string $routeName) {
    $this->actingAs(User::factory()->{$role}()->create());

    $html = $this->get(route(pedidosRouteByRole()[$role]))->assertOk()->getContent();

    $first = sidebarLinks($html)[0];

    expect($first['label'])->toBe('+ Nova Solicitação')
        ->and($first['tag'])->toContain('data-testid="sidebar-nova-solicitacao"')
        ->and($first['tag'])->toContain('btn-primary')
        ->and($first['href'])->toBe(route($routeName));

    preg_match('/<header.*?<\/header>/s', $html, $header);

    expect($header[0])->toMatch('/<a[^>]*href="'.preg_quote(route($routeName), '/').'"[^>]*data-testid="topbar-nova-solicitacao"[^>]*class="[^"]*btn-primary[^"]*"[^>]*>\s*\+ Nova Solicitação\s*<\/a>/s');

    $this->get(route($routeName))->assertOk();
})->with([
    'obra' => ['obra', 'obra.nova-solicitacao'],
    'suprimentos' => ['suprimentos', 'suprimentos.nova-solicitacao'],
]);

test('the menu toggle is a button controlling the sidebar, collapsed by default, named Menu (UI-02)', function () {
    $this->actingAs(User::factory()->obra()->create());

    $html = $this->get(route('obra.pedidos.index'))->assertOk()->getContent();

    preg_match('/<button\s[^>]*data-testid="menu-toggle"[^>]*>.*?<\/button>/s', $html, $toggle);

    expect($toggle)->not->toBeEmpty('the menu toggle button is missing');
    expect($toggle[0])->toContain('aria-controls="sidebar"')
        ->toContain('aria-expanded="false"')
        ->toContain('type="button"')
        ->toMatch('/<span class="sr-only">Menu<\/span>/');
});

test('the sidebar container is a white surface with no primary fill or gradient (UI-03)', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    $html = $this->get(route(pedidosRouteByRole()[$role]))->assertOk()->getContent();

    preg_match('/<aside\s[^>]*id="sidebar"[^>]*>/s', $html, $asideTag);
    preg_match('/class="([^"]*)"/', $asideTag[0], $classes);

    $classList = explode(' ', preg_replace('/\s+/', ' ', trim($classes[1])));

    expect($classList)->toContain('bg-surface')
        ->not->toContain('bg-primary');
    expect(sidebarRegion($html))->not->toContain('bg-gradient-')
        ->not->toContain('bg-primary');
})->with(['obra', 'suprimentos', 'gestao']);
