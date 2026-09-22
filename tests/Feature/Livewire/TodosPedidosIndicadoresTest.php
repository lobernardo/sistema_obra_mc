<?php

use App\Domain\Pedidos\AtrasoClassifier;
use App\Domain\Pedidos\PendenteClassifier;
use App\Livewire\Suprimentos\TodosPedidos;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use Livewire\Livewire;

/**
 * RF-21: Total / Pendentes / Atrasados above the Suprimentos listing, always
 * computed over the currently filtered set and always through the domain
 * classifiers.
 */
beforeEach(function () {
    $this->solicitado = Status::factory()->solicitado()->create();
    $this->entregue = Status::factory()->entregue()->create();

    $this->actor = User::factory()->suprimentos()->create();
    $this->actingAs($this->actor);
});

test('with no filter the three indicators equal the whole dataset counts', function () {
    $pendenteNoPrazo = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'needed_at' => now()->addDays(5)]);
    $pendenteAtrasado = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'needed_at' => now()->subDays(3)]);
    $entregueVencido = Pedido::factory()->create(['status_id' => $this->entregue->id, 'needed_at' => now()->subDays(5)]);

    $todos = Pedido::query()->with('status')->get();

    $indicators = Livewire::test(TodosPedidos::class)->instance()->indicators();

    expect($indicators['total'])->toBe(3)->toBe($todos->count())
        ->and($indicators['pendentes'])->toBe(2)->toBe($todos->filter(fn (Pedido $pedido) => PendenteClassifier::isPendente($pedido))->count())
        ->and($indicators['atrasados'])->toBe(1)->toBe($todos->filter(fn (Pedido $pedido) => AtrasoClassifier::isAtrasado($pedido))->count());

    expect(PendenteClassifier::isPendente($pendenteNoPrazo->fresh()))->toBeTrue()
        ->and(AtrasoClassifier::isAtrasado($pendenteAtrasado->fresh()))->toBeTrue()
        ->and(PendenteClassifier::isPendente($entregueVencido->fresh()))->toBeFalse();
});

test('after applying the obra filter the three indicators equal that obra counts', function () {
    $obraA = Obra::factory()->create();
    $obraB = Obra::factory()->create();

    Pedido::factory()->create(['obra_id' => $obraA->id, 'status_id' => $this->solicitado->id, 'needed_at' => now()->addDays(5)]);
    Pedido::factory()->create(['obra_id' => $obraA->id, 'status_id' => $this->solicitado->id, 'needed_at' => now()->subDays(3)]);
    Pedido::factory()->create(['obra_id' => $obraA->id, 'status_id' => $this->entregue->id, 'needed_at' => now()->subDays(9)]);
    Pedido::factory()->count(4)->create(['obra_id' => $obraB->id, 'status_id' => $this->solicitado->id, 'needed_at' => now()->subDays(2)]);

    $component = Livewire::test(TodosPedidos::class)->set('obraId', $obraA->id);

    expect($component->instance()->indicators())->toBe([
        'total' => 3,
        'pendentes' => 2,
        'atrasados' => 1,
    ]);

    $componentB = Livewire::test(TodosPedidos::class)->set('obraId', $obraB->id);

    expect($componentB->instance()->indicators())->toBe([
        'total' => 4,
        'pendentes' => 4,
        'atrasados' => 4,
    ]);
});

test('the indicators never contradict the rows the listing paginates', function () {
    Pedido::factory()->count(12)->create(['status_id' => $this->solicitado->id, 'needed_at' => now()->subDays(2)]);
    Pedido::factory()->count(3)->create(['status_id' => $this->entregue->id, 'needed_at' => now()->subDays(2)]);

    $component = Livewire::test(TodosPedidos::class)->set('statusId', $this->solicitado->id);
    $instance = $component->instance();

    $indicators = $instance->indicators();
    $paginator = $instance->pedidos();

    expect($indicators['total'])->toBe($paginator->total())->toBe(12)
        ->and($paginator->count())->toBe(10)
        ->and($indicators['pendentes'])->toBe(12)
        ->and($indicators['atrasados'])->toBe(12);
});

test('the three indicator cards render above the listing with the dashboard data-testid pattern', function () {
    Pedido::factory()->create(['status_id' => $this->solicitado->id, 'needed_at' => now()->subDays(2)]);

    $html = Livewire::test(TodosPedidos::class)->html();

    foreach (['indicator-total', 'indicator-pendentes', 'indicator-atrasados'] as $testid) {
        expect($html)->toContain('data-testid="'.$testid.'"');
    }

    expect($html)->toContain('data-value')
        ->and(strpos($html, 'data-testid="indicator-total"'))->toBeLessThan(strpos($html, '<table'));
});

test('no classifier rule is re-derived in the component or in the view', function () {
    $component = file_get_contents(app_path('Livewire/Suprimentos/TodosPedidos.php'));
    $view = file_get_contents(resource_path('views/livewire/suprimentos/todos-pedidos.blade.php'));

    expect($component)->toContain('PendenteClassifier::scopePendente')
        ->toContain('AtrasoClassifier::scopeAtrasado');

    foreach ([$component, $view] as $source) {
        expect($source)->not->toContain('isTerminal')
            ->not->toContain('StatusSlug::Entregue')
            ->not->toContain('StatusSlug::Cancelado');
    }

    expect($view)->not->toContain('needed_at')
        ->not->toContain('Classifier');
});
