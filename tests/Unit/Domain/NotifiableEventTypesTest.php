<?php

use App\Domain\Pedidos\NotifiableEventTypes;
use App\Enums\EventTypeSlug;

/**
 * T03 — RF-02, RF-09: the 10 history event types notify; anything else
 * does not.
 */
test('the 10 event types are notifiable', function (string $slug) {
    expect(app(NotifiableEventTypes::class)->isNotifiable($slug))->toBeTrue();
})->with([
    'criacao_pedido', 'mudanca_status', 'entrega', 'cancelamento', 'finalizacao',
    'alteracao_responsavel', 'alteracao_prioridade', 'alteracao_previsao', 'observacao', 'romaneio_anexado',
]);

test('the classification lists exactly the EventTypeSlug values', function () {
    expect(array_keys(app(NotifiableEventTypes::class)->classification()))
        ->toEqualCanonicalizing(array_column(EventTypeSlug::cases(), 'value'));
});

test('an unknown slug is not notifiable', function (string $slug) {
    expect(app(NotifiableEventTypes::class)->isNotifiable($slug))->toBeFalse();
})->with(['tipo_desconhecido', '', 'OBSERVACAO']);

test('a subclass bound in the container changes the classification', function () {
    app()->bind(NotifiableEventTypes::class, fn () => new class extends NotifiableEventTypes
    {
        public function classification(): array
        {
            return [...parent::classification(), EventTypeSlug::Observacao->value => false, 'tipo_ficticio' => true];
        }
    });

    $types = app(NotifiableEventTypes::class);

    expect($types->isNotifiable('tipo_ficticio'))->toBeTrue();
    expect($types->isNotifiable(EventTypeSlug::Observacao->value))->toBeFalse();
    expect($types->isNotifiable(EventTypeSlug::Entrega->value))->toBeTrue();
});
