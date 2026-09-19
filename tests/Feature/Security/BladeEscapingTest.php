<?php

use App\Livewire\Kanban\KanbanBoard;
use App\Livewire\Obra\PedidoDetalhe;
use App\Livewire\Suprimentos\TodosPedidos;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use Livewire\Livewire;
use Symfony\Component\Finder\Finder;

/**
 * RNF-08: Blade's default `{{ }}` escaping must be the only way user-provided
 * content (obra name, items_description, etc.) reaches HTML. `{!! !!}` skips
 * that escaping entirely, so any use of it against unsanitized user content
 * would reopen an XSS hole.
 */
test('no Blade view uses raw echo ({!! !!}) for content', function () {
    $viewsPath = base_path('resources/views');

    $files = iterator_to_array(
        Finder::create()->files()->name('*.blade.php')->in($viewsPath)
    );

    $offenders = [];

    foreach ($files as $file) {
        if (str_contains($file->getContents(), '{!!')) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

test('a malicious obra name is escaped in the Suprimentos Todos os Pedidos listing', function () {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $status = Status::factory()->solicitado()->create();
    $obra = Obra::factory()->create(['name' => "<script>alert('xss')</script>"]);
    Pedido::factory()->create(['obra_id' => $obra->id, 'status_id' => $status->id]);

    $html = Livewire::test(TodosPedidos::class)->html();

    expect($html)
        ->toContain('&lt;script&gt;')
        ->not->toContain("<script>alert('xss')</script>");
});

test('a malicious obra name is escaped in the Kanban board', function () {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $status = Status::factory()->solicitado()->create();
    $obra = Obra::factory()->create(['name' => "<script>alert('xss')</script>"]);
    Pedido::factory()->create(['obra_id' => $obra->id, 'status_id' => $status->id]);

    $html = Livewire::test(KanbanBoard::class)->html();

    expect($html)
        ->toContain('&lt;script&gt;')
        ->not->toContain("<script>alert('xss')</script>");
});

test('a malicious items_description is escaped in the Obra pedido detail', function () {
    $actor = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $actor->obras()->attach($obra->id);
    $this->actingAs($actor);

    $status = Status::factory()->solicitado()->create();
    $pedido = Pedido::factory()->create([
        'obra_id' => $obra->id,
        'requester_id' => $actor->id,
        'status_id' => $status->id,
        'items_description' => '<img src=x onerror=alert(1)>',
    ]);

    $html = Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])->html();

    expect($html)
        ->toContain('&lt;img')
        ->not->toContain('<img src=x onerror=alert(1)>');
});
