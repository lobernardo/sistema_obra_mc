<?php

use App\Domain\Pedidos\NotificationRecipientResolver;
use App\Enums\EventTypeSlug;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * T04 — RF-07: every recipient the resolver returns may view the pedido
 * (`PedidoPolicy::view`), in a scenario mixing the 3 papéis, associated
 * obras, a pedido "Outra", inactive users, a responsible and an unknown
 * papel.
 */
test('every resolved recipient may view the pedido, for every pedido and every actor', function () {
    $statuses = seedWorkflowStatuses();
    $eventTypes = seedHistoryEventTypes();

    $obraA = Obra::factory()->create();
    $obraB = Obra::factory()->create();

    $obraUserA = User::factory()->obra()->create();
    $obraUserA->obras()->attach($obraA->id);
    $obraUserB = User::factory()->obra()->create();
    $obraUserB->obras()->attach($obraB->id);
    $obraUserBoth = User::factory()->obra()->create();
    $obraUserBoth->obras()->attach([$obraA->id, $obraB->id]);
    $obraUserNone = User::factory()->obra()->create();
    $inactiveObraA = User::factory()->obra()->inactive()->create();
    $inactiveObraA->obras()->attach($obraA->id);

    $suprimentosA = User::factory()->suprimentos()->create();
    $suprimentosA->obras()->attach($obraA->id);
    $suprimentosFree = User::factory()->suprimentos()->create();
    $suprimentosResponsible = User::factory()->suprimentos()->create();
    $inactiveSuprimentos = User::factory()->suprimentos()->inactive()->create();

    $gestao = User::factory()->gestao()->create();
    $otherGestao = User::factory()->gestao()->create();
    $semPapel = User::factory()->create();
    $semPapel->obras()->attach($obraA->id);

    $pedidos = [
        Pedido::factory()->create(['obra_id' => $obraA->id, 'requester_id' => $obraUserA->id, 'status_id' => $statuses['solicitado']->id]),
        Pedido::factory()->create(['obra_id' => $obraB->id, 'requester_id' => $obraUserB->id, 'responsible_id' => $suprimentosResponsible->id, 'status_id' => $statuses['em_analise']->id]),
        Pedido::factory()->outra('Depósito')->create(['requester_id' => $obraUserNone->id, 'responsible_id' => $suprimentosA->id, 'status_id' => $statuses['entregue']->id]),
        Pedido::factory()->outra()->create(['requester_id' => $suprimentosFree->id, 'status_id' => $statuses['cancelado']->id]),
    ];

    $actors = [$obraUserA, $obraUserNone, $suprimentosA, $suprimentosFree, $gestao];
    $users = User::query()->with('role')->get()->keyBy('id');
    $resolver = app(NotificationRecipientResolver::class);
    $checkedPairs = 0;

    foreach ($pedidos as $pedido) {
        foreach ($actors as $actor) {
            $event = PedidoEvent::factory()->create([
                'pedido_id' => $pedido->id,
                'event_type_id' => $eventTypes[EventTypeSlug::Observacao->value]->id,
                'actor_id' => $actor->id,
                'new_value' => 'Paridade com a Policy.',
            ]);

            foreach ($resolver->recipientIdsFor($event) as $recipientId) {
                $recipient = $users[$recipientId];

                expect(Gate::forUser($recipient)->allows('view', $pedido))
                    ->toBeTrue("Usuário {$recipientId} ({$recipient->role->slug}) recebe aviso do pedido {$pedido->id} sem poder vê-lo.");
                expect($recipient->is_active)->toBeTrue();
                expect($recipientId)->not->toBe($actor->id);

                $checkedPairs++;
            }
        }
    }

    expect($checkedPairs)->toBeGreaterThan(20);

    $allRecipients = collect($pedidos)->flatMap(fn (Pedido $pedido) => PedidoEvent::query()
        ->where('pedido_id', $pedido->id)
        ->get()
        ->flatMap(fn (PedidoEvent $event) => $resolver->recipientIdsFor($event)))
        ->unique();

    expect($allRecipients)->not->toContain($semPapel->id, $inactiveObraA->id, $inactiveSuprimentos->id);
    expect($allRecipients)->toContain($otherGestao->id, $obraUserBoth->id, $suprimentosResponsible->id);
});
