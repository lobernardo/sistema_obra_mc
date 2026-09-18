<?php

use App\Actions\Pedidos\CancelPedidoAction;
use App\Actions\Pedidos\CreatePedidoAction;
use App\Actions\Pedidos\UpdatePedidoPrevisaoAction;
use App\Actions\Pedidos\UpdatePedidoPrioridadeAction;
use App\Actions\Pedidos\UpdatePedidoResponsavelAction;
use App\Actions\Pedidos\UpdatePedidoStatusAction;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use App\Services\PedidoCodeGenerator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * RF-09 — calling each mutation's public backend entrypoint directly,
 * bypassing any Livewire component/UI control, must be rejected exactly as
 * it would be from the UI. No test here renders a view or interacts with a
 * form: it manipulates the backend surface a forged request would hit.
 */
dataset('non suprimentos roles', ['obra', 'gestao']);

test('setResponsavel is rejected when called directly by a non-suprimentos actor', function (string $role) {
    $actor = User::factory()->{$role}()->create();
    $responsible = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create();

    expect(fn () => (new UpdatePedidoResponsavelAction)->execute($actor, $pedido, $responsible->id))
        ->toThrow(AuthorizationException::class);
})->with('non suprimentos roles');

test('setPrioridade is rejected when called directly by a non-suprimentos actor', function (string $role) {
    $actor = User::factory()->{$role}()->create();
    $pedido = Pedido::factory()->create();
    $priority = Priority::factory()->create();

    expect(fn () => (new UpdatePedidoPrioridadeAction)->execute($actor, $pedido, $priority->id))
        ->toThrow(AuthorizationException::class);
})->with('non suprimentos roles');

test('setPrevisao is rejected when called directly by a non-suprimentos actor', function (string $role) {
    $actor = User::factory()->{$role}()->create();
    $pedido = Pedido::factory()->create();

    expect(fn () => (new UpdatePedidoPrevisaoAction)->execute($actor, $pedido, now()->addDays(5)->toDateString()))
        ->toThrow(AuthorizationException::class);
})->with('non suprimentos roles');

test('moveStatus is rejected when called directly by a non-suprimentos actor', function (string $role) {
    $actor = User::factory()->{$role}()->create();
    $pedido = Pedido::factory()->create(['status_id' => Status::factory()->solicitado()->create()->id]);
    $target = Status::factory()->emAnalise()->create();

    expect(fn () => (new UpdatePedidoStatusAction)->execute($actor, $pedido, $target->id))
        ->toThrow(AuthorizationException::class);
})->with('non suprimentos roles');

test('cancelar is rejected when called directly by a non-suprimentos actor', function (string $role) {
    $actor = User::factory()->{$role}()->create();
    $pedido = Pedido::factory()->create();

    expect(fn () => (new CancelPedidoAction)->execute($actor, $pedido))
        ->toThrow(AuthorizationException::class);
})->with('non suprimentos roles');

test('createSolicitacao is rejected when the payload forges an obra_id outside the requester\'s associations', function () {
    $requester = User::factory()->obra()->create();
    $foreignObra = Obra::factory()->create();

    $action = new CreatePedidoAction(new PedidoCodeGenerator);

    expect(fn () => $action->execute($requester, [
        'obra_id' => $foreignObra->id,
        'needed_at' => now()->addDays(10)->toDateString(),
        'items_description' => 'Itens forjados via payload direto.',
    ]))->toThrow(ValidationException::class);

    expect(Pedido::query()->where('obra_id', $foreignObra->id)->exists())->toBeFalse();
});
