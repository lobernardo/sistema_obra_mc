<?php

use App\Models\User;
use App\Support\SidebarNavigation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * navegacao-sidebar-listagens T02 (RF-02, RF-03, RF-07, RF-08, CT-02,
 * RNF-01, RNF-05): the sidebar catalogue shows exactly the CT-02 items per
 * papel, and each item carries exactly the `can:` abilities of its target
 * route, so sidebar visibility equals route authorization.
 */
dataset('sidebar labels per papel', [
    'obra' => ['obra', ['+ Nova Solicitação', 'Acompanhamento']],
    'suprimentos' => ['suprimentos', ['+ Nova Solicitação', 'Pedidos', 'Visão Geral', 'Kanban', 'Obras', 'Associações']],
    'gestao' => ['gestao', ['Pedidos', 'Dashboard', 'Kanban', 'Obras', 'Associações', 'Usuários']],
]);

test('each papel gets exactly the RF-03 labels in order', function (string $factoryState, array $labels) {
    $user = User::factory()->{$factoryState}()->create();

    expect(array_column(SidebarNavigation::for($user), 'label'))->toBe($labels);
})->with('sidebar labels per papel');

test('a user without a recognised papel and a null user get no item', function () {
    $unrecognised = User::factory()->create();
    $roleless = new User(['name' => 'Sem Papel']);

    expect(SidebarNavigation::for($unrecognised))->toBe([])
        ->and(SidebarNavigation::for($roleless))->toBe([])
        ->and(SidebarNavigation::for(null))->toBe([]);
});

/**
 * @return array<string, array{string, array{label: string, route: string, active: string, abilities: list<string>, group: ?string, highlight: bool}}>
 */
function sidebarCatalogueItems(): array
{
    $items = [];

    foreach (SidebarNavigation::catalogue() as $papel => $papelItems) {
        foreach ($papelItems as $item) {
            $items["{$papel} {$item['label']}"] = [$papel, $item];
        }
    }

    return $items;
}

test('every catalogue route resolves', function (string $papel, array $item) {
    expect(route($item['route']))->toBeString()->not->toBeEmpty();
})->with(fn (): array => sidebarCatalogueItems());

test('item abilities equal the can: middleware of the target route as a set', function (string $papel, array $item) {
    $routeAbilities = collect(Route::getRoutes()->getByName($item['route'])->gatherMiddleware())
        ->filter(fn (string $middleware): bool => str_starts_with($middleware, 'can:'))
        ->map(fn (string $middleware): string => substr($middleware, strlen('can:')))
        ->sort()
        ->values()
        ->all();

    expect(collect($item['abilities'])->sort()->values()->all())->toBe($routeAbilities);
})->with(fn (): array => sidebarCatalogueItems());

test('only the two + Nova Solicitação items are highlighted, each first', function () {
    $catalogue = SidebarNavigation::catalogue();

    foreach (['obra', 'suprimentos'] as $papel) {
        $highlighted = array_values(array_filter($catalogue[$papel], fn (array $item): bool => $item['highlight']));

        expect($highlighted)->toHaveCount(1)
            ->and($highlighted[0]['label'])->toBe('+ Nova Solicitação')
            ->and($catalogue[$papel][0]['highlight'])->toBeTrue();
    }

    expect(array_filter($catalogue['gestao'], fn (array $item): bool => $item['highlight']))->toBe([]);
});

test('building the sidebar issues no query once role is loaded', function (string $factoryState, array $labels) {
    $user = User::factory()->{$factoryState}()->create()->load('role');

    DB::flushQueryLog();
    DB::enableQueryLog();

    $items = SidebarNavigation::for($user);

    expect(DB::getQueryLog())->toBe([])
        ->and($items)->toHaveCount(count($labels));

    DB::disableQueryLog();
})->with('sidebar labels per papel');
