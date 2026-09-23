<?php

use App\Actions\Pedidos\AddPedidoObservacaoAction;
use App\Enums\EventTypeSlug;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    seedHistoryEventTypes();

    $this->obraUser = User::factory()->obra()->create();
    $this->obra = Obra::factory()->create();
    $this->obraUser->obras()->attach($this->obra->id);
    $this->pedido = Pedido::factory()->for($this->obraUser, 'requester')->create([
        'obra_id' => $this->obra->id,
        'status_id' => $this->statuses['em_analise']->id,
    ]);
    $this->action = app(AddPedidoObservacaoAction::class);
});

function observacaoEvents(Pedido $pedido): Collection
{
    return PedidoEvent::query()
        ->where('pedido_id', $pedido->id)
        ->whereHas('eventType', fn ($query) => $query->where('slug', EventTypeSlug::Observacao->value))
        ->orderBy('id')
        ->get();
}

test('2 consecutive observations write 2 events, keep the first intact and leave the pedido untouched (RF-22, RF-24)', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-20T12:00:00Z'));
    $pedido = $this->pedido->fresh();
    $updatedAt = $pedido->updated_at->toIso8601String();
    $suprimentos = User::factory()->suprimentos()->create();

    $this->travelTo(CarbonImmutable::parse('2026-09-21T12:00:00Z'));
    $this->action->execute($this->obraUser, $pedido, '  Entregar no portão 2.  ');
    $first = observacaoEvents($pedido)->first()->getAttributes();

    $this->action->execute($suprimentos, $pedido, "Material separado.\nAguardando transporte.");

    $events = observacaoEvents($pedido);
    $fresh = $pedido->fresh();

    expect($events)->toHaveCount(2)
        ->and($events[0]->getAttributes())->toBe($first)
        ->and($events[0]->new_value)->toBe('Entregar no portão 2.')
        ->and($events[0]->actor_id)->toBe($this->obraUser->id)
        ->and($events[1]->new_value)->toBe("Material separado.\nAguardando transporte.")
        ->and($events[1]->actor_id)->toBe($suprimentos->id)
        ->and($fresh->status_id)->toBe($this->statuses['em_analise']->id)
        ->and($fresh->updated_at->toIso8601String())->toBe($updatedAt);
});

test('a blank or whitespace-only observation is rejected with 422 and writes nothing (RF-25)', function (string $texto) {
    expect(fn () => $this->action->execute($this->obraUser, $this->pedido, $texto))
        ->toThrow(ValidationException::class, 'Escreva a observação.');

    expect(observacaoEvents($this->pedido))->toHaveCount(0);
})->with(['empty' => '', 'spaces' => "   \n\t  "]);

test('2000 characters are accepted and 2001 are rejected (RF-25)', function () {
    $this->action->execute($this->obraUser, $this->pedido, str_repeat('á', 2000));

    expect(fn () => $this->action->execute($this->obraUser, $this->pedido, str_repeat('a', 2001)))
        ->toThrow(ValidationException::class, 'A observação deve ter no máximo 2000 caracteres.');

    expect(observacaoEvents($this->pedido))->toHaveCount(1)
        ->and(mb_strlen(observacaoEvents($this->pedido)->first()->new_value))->toBe(2000);
});

test('trimming happens before the length check', function () {
    $this->action->execute($this->obraUser, $this->pedido, '   '.str_repeat('b', 2000).'   ');

    expect(observacaoEvents($this->pedido)->first()->new_value)->toBe(str_repeat('b', 2000));
});

test('terminal pedidos accept observations without 409 and keep their status (RF-26)', function (string $status) {
    $pedido = Pedido::factory()->for($this->obraUser, 'requester')->create([
        'obra_id' => $this->obra->id,
        'status_id' => $this->statuses[$status]->id,
    ]);

    $this->action->execute($this->obraUser, $pedido, 'Observação em pedido terminal.');
    $this->action->execute(User::factory()->suprimentos()->create(), $pedido, 'Observação de Suprimentos.');

    expect(observacaoEvents($pedido))->toHaveCount(2)
        ->and($pedido->fresh()->status_id)->toBe($this->statuses[$status]->id);
})->with(['entregue', 'cancelado', 'finalizado']);

test('gestao is denied and writes nothing (RF-25)', function () {
    expect(fn () => $this->action->execute(User::factory()->gestao()->create(), $this->pedido, 'Tentativa'))
        ->toThrow(AuthorizationException::class, 'Apenas os perfis Obra e Suprimentos podem adicionar observações.');

    expect(observacaoEvents($this->pedido))->toHaveCount(0);
});

test('an obra user of another obra is denied and writes nothing (RF-25)', function () {
    $outsider = User::factory()->obra()->create();
    $outsider->obras()->attach(Obra::factory()->create()->id);

    expect(fn () => $this->action->execute($outsider, $this->pedido, 'Tentativa'))
        ->toThrow(AuthorizationException::class);

    expect(observacaoEvents($this->pedido))->toHaveCount(0);
});

test('the requester of an "Outra" pedido may observe; another obra user may not (RF-40)', function () {
    $pedido = Pedido::factory()->outra('Galpão provisório')->for($this->obraUser, 'requester')->create([
        'status_id' => $this->statuses['solicitado']->id,
    ]);
    $other = User::factory()->obra()->create();
    $other->obras()->attach($this->obra->id);

    $this->action->execute($this->obraUser, $pedido, 'Minha observação.');

    expect(fn () => $this->action->execute($other, $pedido, 'Tentativa'))
        ->toThrow(AuthorizationException::class);

    expect(observacaoEvents($pedido))->toHaveCount(1);
});

test('an unauthorized actor is refused before validation runs', function () {
    expect(fn () => $this->action->execute(User::factory()->gestao()->create(), $this->pedido, ''))
        ->toThrow(AuthorizationException::class);
});
