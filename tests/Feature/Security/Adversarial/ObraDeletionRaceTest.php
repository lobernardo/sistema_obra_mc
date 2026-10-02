<?php

use App\Actions\Obras\DeleteObraAction;
use App\Enums\ObraAdminAction;
use App\Exceptions\Obras\ObraNotFoundException;
use App\Models\Obra;
use App\Models\ObraAdminEvent;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * RF-14 / RNF-01: `DeleteObraAction` decides on a locked re-count, never on
 * what the UI saw. A pedido committed between the confirmation and the
 * Action turns the deletion into the RF-16 block, and a second deletion of
 * the same obra never writes a second `obra_deleted`.
 */
test('a pedido committed after the confirmation turns the deletion into the RF-16 block (RF-14)', function () {
    $actor = User::factory()->gestao()->create();
    $obra = Obra::factory()->create(['name' => 'Obra Corrida']);

    // The UI confirmed the deletion on this instance, when the obra had no pedidos.
    $confirmed = Obra::query()->findOrFail($obra->id);
    expect($confirmed->pedidos()->exists())->toBeFalse();

    Pedido::factory()->create(['obra_id' => $obra->id]);

    try {
        app(DeleteObraAction::class)->execute($actor, $confirmed);

        throw new RuntimeException('A ValidationException was expected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([
            'excluir' => ['Não é possível excluir a obra «Obra Corrida»: ela possui 1 pedido(s) e 0 convite(s) utilizado(s). Desative a obra para impedir novos usos.'],
        ]);
    }

    expect(Obra::query()->whereKey($obra->id)->exists())->toBeTrue();
    expect(ObraAdminEvent::query()->where('action', ObraAdminAction::ObraDeleted->value)->exists())->toBeFalse();
    expect(ObraAdminEvent::query()->where('action', ObraAdminAction::ObraDeleteBlocked->value)->count())->toBe(1);
});

test('a second deletion of the same obra throws ObraNotFoundException without a second obra_deleted (RNF-01)', function () {
    $actor = User::factory()->suprimentos()->create();
    $obra = Obra::factory()->create();

    // Both requests loaded the obra before either deleted it.
    $first = Obra::query()->findOrFail($obra->id);
    $second = Obra::query()->findOrFail($obra->id);

    app(DeleteObraAction::class)->execute($actor, $first);

    expect(fn () => app(DeleteObraAction::class)->execute($actor, $second))
        ->toThrow(ObraNotFoundException::class, 'A obra informada não foi encontrada.');

    expect(ObraAdminEvent::query()->where('action', ObraAdminAction::ObraDeleted->value)->count())->toBe(1);
    expect(ObraAdminEvent::query()->where('action', ObraAdminAction::ObraDeleteBlocked->value)->exists())->toBeFalse();
});
