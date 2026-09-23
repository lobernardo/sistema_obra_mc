<?php

use App\Enums\StatusSlug;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/**
 * RF-47 / F-12: timestamps render in the `America/Sao_Paulo` calendar and
 * calendar `date` columns are never timezone-shifted.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
});

/**
 * Files under the given directories whose source mentions an
 * `obra_admin_events` render site (the grep of T39).
 *
 * @param  list<string>  $directories
 * @return list<string>
 */
function obraAdminEventRenderSites(array $directories): array
{
    $sites = [];

    foreach ($directories as $directory) {
        foreach (File::allFiles($directory) as $file) {
            if (preg_match('/ObraAdminEvent|obra_admin_events|adminEvents/', $file->getContents()) === 1) {
                $sites[] = $file->getRelativePathname();
            }
        }
    }

    return $sites;
}

test('no view or Livewire component renders obra_admin_events timestamps today', function () {
    expect(obraAdminEventRenderSites([resource_path('views'), app_path('Livewire')]))->toBe([]);
});

test('a pedido needed on 25/09/2026 shows 25/09/2026 on every screen, even at 24/09 22:30 local', function (string $role, string $routeName, bool $withPedido) {
    $this->travelTo(CarbonImmutable::parse('2026-09-25T01:30:00Z'));

    $obra = Obra::factory()->emAndamento()->create();
    $actor = User::factory()->{$role}()->create();
    $obra->users()->attach($actor);

    $pedido = Pedido::factory()->create([
        'obra_id' => $obra->id,
        'status_id' => $this->statuses[StatusSlug::Solicitado->value]->id,
        'requested_at' => '2026-09-01 12:00:00',
        'needed_at' => '2026-09-25',
        'expected_delivery_at' => null,
    ]);

    $this->actingAs($actor)
        ->get($withPedido ? route($routeName, $pedido) : route($routeName))
        ->assertOk()
        ->assertSee('25/09/2026')
        ->assertDontSee('24/09/2026');
})->with([
    'obra listing' => ['obra', 'obra.pedidos.index', false],
    'obra detail' => ['obra', 'obra.pedidos.show', true],
    'suprimentos kanban' => ['suprimentos', 'suprimentos.kanban', false],
    'suprimentos listing' => ['suprimentos', 'suprimentos.pedidos.index', false],
    'suprimentos detail' => ['suprimentos', 'suprimentos.pedidos.show', true],
    'gestao kanban' => ['gestao', 'gestao.kanban', false],
    'gestao listing' => ['gestao', 'gestao.pedidos.index', false],
    'gestao detail' => ['gestao', 'gestao.pedidos.show', true],
]);
