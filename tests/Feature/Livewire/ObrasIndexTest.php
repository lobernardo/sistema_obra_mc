<?php

use App\Livewire\Obras\Index;
use App\Models\Obra;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

dataset('obras routes', [
    'index' => ['obras.index'],
    'create' => ['obras.create'],
    'edit' => ['obras.edit'],
]);

/**
 * @return array<string, mixed>
 */
function obrasRouteParameters(string $routeName): array
{
    return $routeName === 'obras.edit' ? ['obra' => Obra::factory()->create()] : [];
}

test('the three obras routes carry auth, active and can:manage-obras (CT-03)', function (string $routeName) {
    $middleware = collect(app('router')->getRoutes()->getByName($routeName)->gatherMiddleware());

    expect($middleware)->toContain('auth')
        ->toContain('active')
        ->toContain('can:manage-obras');
})->with('obras routes');

test('gestao and suprimentos get 200 on the three obras routes', function (string $factoryState, string $routeName) {
    $this->actingAs(User::factory()->{$factoryState}()->create());

    $this->get(route($routeName, obrasRouteParameters($routeName)))->assertOk();
})->with(['gestao', 'suprimentos'])->with('obras routes');

test('an obra user gets 403 on the three obras routes (RF-07)', function (string $routeName) {
    $this->actingAs(User::factory()->obra()->create());

    $this->get(route($routeName, obrasRouteParameters($routeName)))->assertForbidden();
})->with('obras routes');

test('a guest is redirected to the login page', function (string $routeName) {
    $this->get(route($routeName, obrasRouteParameters($routeName)))->assertRedirect(route('login'));
})->with('obras routes');

test('an inactive gestao or suprimentos user is logged out', function (string $factoryState, string $routeName) {
    $this->actingAs(User::factory()->{$factoryState}()->inactive()->create());

    $this->get(route($routeName, obrasRouteParameters($routeName)))->assertRedirect(route('login'));

    expect(Auth::check())->toBeFalse();
})->with(['gestao', 'suprimentos'])->with('obras routes');

test('obras in the 3 states are listed with their labels and no delete control (UI-03)', function () {
    $this->actingAs(User::factory()->suprimentos()->create());

    $aIniciar = Obra::factory()->aIniciar()->create(['name' => 'Alfa Residencial', 'responsavel' => 'Eng. Carla']);
    $emAndamento = Obra::factory()->emAndamento()->create(['name' => 'Beta Comercial']);
    $concluida = Obra::factory()->concluida()->create(['name' => 'Gama Industrial']);

    $html = $this->get(route('obras.index'))
        ->assertOk()
        ->assertSeeInOrder(['Nome', 'Responsável', 'Status'])
        ->assertSee('Nova obra')
        ->assertSee(route('obras.create'))
        ->assertSee(route('obras.edit', $aIniciar))
        ->assertSee(route('obras.edit', $emAndamento))
        ->assertSee(route('obras.edit', $concluida))
        ->assertDontSee('Excluir')
        ->assertDontSee('Remover')
        ->getContent();

    foreach ([[$aIniciar, 'A iniciar', 'Eng. Carla'], [$emAndamento, 'Em andamento', '—'], [$concluida, 'Concluído', '—']] as [$obra, $label, $responsavel]) {
        preg_match('/<tr[^>]*data-obra-id="'.$obra->id.'".*?<\/tr>/s', $html, $row);

        expect($row)->not->toBeEmpty();
        expect($row[0])->toContain(e($obra->name))
            ->toContain($label)
            ->toContain(e($responsavel));
    }

    expect($html)->not->toMatch('/wire:click="[^"]*(delete|destroy|excluir|remover)/i');
});

test('the listing is ordered by name and paginated at 15', function () {
    $this->actingAs(User::factory()->gestao()->create());

    foreach (range(1, 16) as $index) {
        Obra::factory()->create(['name' => sprintf('Obra %02d', $index)]);
    }

    Livewire::test(Index::class)
        ->assertSeeInOrder(['Obra 01', 'Obra 02', 'Obra 15'])
        ->assertDontSee('Obra 16')
        ->call('gotoPage', 2)
        ->assertSee('Obra 16')
        ->assertDontSee('Obra 01');
});

test('the component exposes no delete method (RF-06)', function () {
    $methods = array_map(fn (ReflectionMethod $method) => strtolower($method->getName()), (new ReflectionClass(Index::class))->getMethods(ReflectionMethod::IS_PUBLIC));

    expect(array_filter($methods, fn (string $name) => preg_match('/delete|destroy|remove|excluir/', $name) === 1))->toBe([]);
});

test('an obra user cannot mount the listing component', function () {
    $this->actingAs(User::factory()->obra()->create());

    Livewire::test(Index::class)->assertForbidden();
});
