<?php

use App\Enums\PrioritySlug;
use App\Enums\StatusSlug;
use App\Models\Priority;
use App\Models\Status;
use Illuminate\Support\Facades\Blade;

/**
 * UI-11: `x-status-badge` and `x-priority-badge` map every slug to a distinct
 * semantic variant (no two workflow states share a color, institutional red
 * is not applied to all of them) while keeping the `data-status` /
 * `data-priority` hooks and the `$attributes->merge` contract that the
 * screens and existing tests rely on.
 */

/**
 * @return array{classes: list<string>, html: string}
 */
function renderedBadge(string $blade, array $data): array
{
    $html = Blade::render($blade, $data);

    preg_match('/class="([^"]*)"/', $html, $classes);

    expect($classes)->not->toBeEmpty('badge has no class attribute');

    $list = preg_split('/\s+/', trim($classes[1]));
    sort($list);

    return ['classes' => $list, 'html' => $html];
}

test('the 6 statuses render 6 distinct class sets and keep data-status', function () {
    $classSets = [];

    foreach (StatusSlug::cases() as $slug) {
        $status = Status::factory()->make(['slug' => $slug->value, 'name' => 'Status '.$slug->value]);

        $badge = renderedBadge('<x-status-badge :status="$status" />', ['status' => $status]);

        expect($badge['html'])->toContain('data-status="'.$slug->value.'"')
            ->toContain('Status '.$slug->value)
            ->not->toContain('sky-');
        expect($badge['classes'])->toContain('badge');

        $classSets[$slug->value] = implode(' ', $badge['classes']);
    }

    expect(array_unique($classSets))->toHaveCount(6);
});

test('each status maps to the expected semantic variant', function () {
    $expected = [
        StatusSlug::Solicitado->value => 'badge-neutral',
        StatusSlug::EmAnalise->value => 'badge-info',
        StatusSlug::EmCompraPreparacao->value => 'badge-secondary',
        StatusSlug::AguardandoEntrega->value => 'badge-warning',
        StatusSlug::Entregue->value => 'badge-concluido',
        StatusSlug::Cancelado->value => 'badge-error',
    ];

    foreach ($expected as $slug => $variant) {
        $status = Status::factory()->make(['slug' => $slug]);

        expect(renderedBadge('<x-status-badge :status="$status" />', ['status' => $status])['classes'])
            ->toContain($variant);
    }
});

test('the 4 priorities render 4 distinct class sets and keep data-priority', function () {
    $classSets = [];

    foreach (PrioritySlug::cases() as $slug) {
        $priority = Priority::factory()->make(['slug' => $slug->value, 'name' => 'Prioridade '.$slug->value]);

        $badge = renderedBadge('<x-priority-badge :priority="$priority" />', ['priority' => $priority]);

        expect($badge['html'])->toContain('data-priority="'.$slug->value.'"')
            ->toContain('Prioridade '.$slug->value)
            ->not->toContain('sky-');
        expect($badge['classes'])->toContain('badge');

        $classSets[$slug->value] = implode(' ', $badge['classes']);
    }

    expect(array_unique($classSets))->toHaveCount(4);
});

test('each priority maps to the expected semantic variant, only urgente being solid', function () {
    $expected = [
        PrioritySlug::Baixa->value => ['badge-neutral'],
        PrioritySlug::Normal->value => ['badge-info'],
        PrioritySlug::Alta->value => ['badge-warning'],
        PrioritySlug::Urgente->value => ['bg-error', 'text-white'],
    ];

    foreach ($expected as $slug => $variantClasses) {
        $priority = Priority::factory()->make(['slug' => $slug]);

        $classes = renderedBadge('<x-priority-badge :priority="$priority" />', ['priority' => $priority])['classes'];

        foreach ($variantClasses as $class) {
            expect($classes)->toContain($class);
        }
    }
});

test('a missing priority renders a muted placeholder without a data-priority hook', function () {
    $html = Blade::render('<x-priority-badge :priority="$priority" />', ['priority' => null]);

    expect($html)->toContain('text-text-muted')
        ->toContain('—')
        ->not->toContain('data-priority');
});

test('badges still merge extra attributes and classes from the caller', function () {
    $status = Status::factory()->make(['slug' => StatusSlug::Entregue->value]);
    $priority = Priority::factory()->make(['slug' => PrioritySlug::Alta->value]);

    $statusHtml = Blade::render('<x-status-badge :status="$status" class="text-sm" data-field="status" />', ['status' => $status]);
    $priorityHtml = Blade::render('<x-priority-badge :priority="$priority" class="text-sm" data-field="priority" />', ['priority' => $priority]);

    expect($statusHtml)->toMatch('/class="badge badge-concluido text-sm"/')->toContain('data-field="status"');
    expect($priorityHtml)->toMatch('/class="badge badge-warning text-sm"/')->toContain('data-field="priority"');
});

test('the atraso indicator uses the atraso and success tokens with data-atraso', function () {
    $overdue = Blade::render('<x-atraso-indicator :atrasado="true" />');
    $onTime = Blade::render('<x-atraso-indicator :atrasado="false" />');

    expect($overdue)->toContain('badge-atraso')->toContain('data-atraso="true"')->toContain('Atrasado');
    expect($onTime)->toContain('badge-success')->toContain('data-atraso="false"')->toContain('No prazo');
    expect($overdue)->not->toContain('badge-success');
});

test('the six shared components contain no sky palette class', function () {
    foreach (['status-badge', 'priority-badge', 'pedido-table', 'pedido-summary', 'pedido-history-timeline', 'atraso-indicator'] as $component) {
        expect(file_get_contents(resource_path("views/components/{$component}.blade.php")))
            ->not->toMatch('/(bg|text|border|ring|from|to)-sky-[0-9]+/');
    }
});
