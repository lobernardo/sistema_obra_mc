<?php

use App\Livewire\Gestao\Dashboard;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use App\Services\DashboardIndicatorsService;
use Illuminate\Support\Collection;
use Livewire\Livewire;

beforeEach(function () {
    $this->solicitado = Status::factory()->solicitado()->create();
    $this->emAnalise = Status::factory()->emAnalise()->create();

    $this->actor = User::factory()->gestao()->create();
    $this->actingAs($this->actor);
});

/**
 * @return array{volumeTotal: int, pendentes: int, atrasados: int, porStatus: Collection, porObra: Collection, prazos: Collection}
 */
function renderedIndicators(array $set): array
{
    $component = Livewire::test(Dashboard::class);

    foreach ($set as $property => $value) {
        $component->set($property, $value);
    }

    return $component->instance()->indicators(app(DashboardIndicatorsService::class));
}

test('the obra filter narrows every indicator to the selected obra', function () {
    $obraA = Obra::factory()->create();
    $obraB = Obra::factory()->create();

    Pedido::factory()->count(2)->create(['obra_id' => $obraA->id, 'status_id' => $this->solicitado->id]);
    Pedido::factory()->count(3)->create(['obra_id' => $obraB->id, 'status_id' => $this->solicitado->id]);

    $indicators = renderedIndicators(['obraId' => $obraA->id]);

    expect($indicators['volumeTotal'])->toBe(2);
    expect($indicators['porObra']->sum('count'))->toBe(2);
});

test('the status filter narrows every indicator to the selected status', function () {
    Pedido::factory()->count(2)->create(['status_id' => $this->solicitado->id]);
    Pedido::factory()->count(4)->create(['status_id' => $this->emAnalise->id]);

    $indicators = renderedIndicators(['statusId' => $this->emAnalise->id]);

    expect($indicators['volumeTotal'])->toBe(4);
    expect($indicators['porStatus']->sum('count'))->toBe(4);
});

test('the priority filter narrows every indicator to the selected priority', function () {
    $urgente = Priority::factory()->urgente()->create();
    $baixa = Priority::factory()->baixa()->create();

    Pedido::factory()->count(1)->create(['status_id' => $this->solicitado->id, 'priority_id' => $urgente->id]);
    Pedido::factory()->count(5)->create(['status_id' => $this->solicitado->id, 'priority_id' => $baixa->id]);

    $indicators = renderedIndicators(['priorityId' => $urgente->id]);

    expect($indicators['volumeTotal'])->toBe(1);
});

test('the responsible filter narrows every indicator to the selected responsible', function () {
    $responsible = User::factory()->suprimentos()->create();
    $other = User::factory()->suprimentos()->create();

    Pedido::factory()->count(2)->create(['status_id' => $this->solicitado->id, 'responsible_id' => $responsible->id]);
    Pedido::factory()->count(3)->create(['status_id' => $this->solicitado->id, 'responsible_id' => $other->id]);

    $indicators = renderedIndicators(['responsibleId' => $responsible->id]);

    expect($indicators['volumeTotal'])->toBe(2);
});

test('the periodo filter narrows every indicator to the requested_at range', function () {
    Pedido::factory()->create(['status_id' => $this->solicitado->id, 'requested_at' => '2026-06-15 10:00:00']);
    Pedido::factory()->create(['status_id' => $this->solicitado->id, 'requested_at' => '2026-06-01 10:00:00']);
    Pedido::factory()->create(['status_id' => $this->solicitado->id, 'requested_at' => '2026-07-01 10:00:00']);

    $indicators = renderedIndicators([
        'requestedFrom' => '2026-06-10',
        'requestedTo' => '2026-06-20',
    ]);

    expect($indicators['volumeTotal'])->toBe(1);
});

test('combined filters narrow the indicators to the intersection', function () {
    $obra = Obra::factory()->create();
    $other = Obra::factory()->create();

    Pedido::factory()->count(2)->create(['obra_id' => $obra->id, 'status_id' => $this->solicitado->id]);
    Pedido::factory()->count(1)->create(['obra_id' => $obra->id, 'status_id' => $this->emAnalise->id]);
    Pedido::factory()->count(4)->create(['obra_id' => $other->id, 'status_id' => $this->solicitado->id]);

    $indicators = renderedIndicators([
        'obraId' => $obra->id,
        'statusId' => $this->solicitado->id,
    ]);

    expect($indicators['volumeTotal'])->toBe(2);
});
