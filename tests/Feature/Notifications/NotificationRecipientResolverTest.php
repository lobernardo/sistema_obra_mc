<?php

use App\Domain\Pedidos\NotificationRecipientResolver;
use App\Enums\EventTypeSlug;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * T04 — RF-03..RF-08, RNF-09: the single recipient rule, in one
 * deduplicated query.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    $this->eventTypes = seedHistoryEventTypes();
    $this->resolver = app(NotificationRecipientResolver::class);
});

function recipientEventOn(Pedido $pedido, User $actor, EventTypeSlug $type = EventTypeSlug::Observacao): PedidoEvent
{
    return PedidoEvent::factory()->create([
        'pedido_id' => $pedido->id,
        'event_type_id' => test()->eventTypes[$type->value]->id,
        'actor_id' => $actor->id,
        'new_value' => 'Texto do evento.',
    ]);
}

function associatedTo(User $user, Obra $obra): User
{
    $user->obras()->attach($obra->id);

    return $user;
}

/**
 * The RF-04 matrix: obra X with S1 (associated), S2 (neither), S3
 * (responsible only), O1 (associated) and O2 (another obra).
 *
 * @return array{obra: Obra, pedido: Pedido, s1: User, s2: User, s3: User, o1: User, o2: User}
 */
function rf04Matrix(): array
{
    $obra = Obra::factory()->create();
    $otherObra = Obra::factory()->create();

    $s1 = associatedTo(User::factory()->suprimentos()->create(), $obra);
    $s2 = User::factory()->suprimentos()->create();
    $s3 = User::factory()->suprimentos()->create();
    $o1 = associatedTo(User::factory()->obra()->create(), $obra);
    $o2 = associatedTo(User::factory()->obra()->create(), $otherObra);

    $pedido = Pedido::factory()->create([
        'obra_id' => $obra->id,
        'requester_id' => $o1->id,
        'responsible_id' => $s3->id,
        'status_id' => test()->statuses['em_analise']->id,
    ]);

    return compact('obra', 'pedido', 's1', 's2', 's3', 'o1', 'o2');
}

test('RF-04: associated suprimentos, the responsible and the associated obra are notified; nobody else', function () {
    ['pedido' => $pedido, 's1' => $s1, 's2' => $s2, 's3' => $s3, 'o1' => $o1, 'o2' => $o2] = rf04Matrix();
    $actor = User::factory()->gestao()->create();

    $ids = $this->resolver->recipientIdsFor(recipientEventOn($pedido, $actor));

    expect($ids)->toEqualCanonicalizing([$s1->id, $s3->id, $o1->id]);
    expect($ids)->not->toContain($s2->id);
    expect($ids)->not->toContain($o2->id);
    expect($s2->can('view', $pedido))->toBeTrue();
});

test('RF-04a: a suprimentos both associated and responsible appears exactly once', function () {
    ['obra' => $obra, 'o1' => $o1] = rf04Matrix();
    $s1 = associatedTo(User::factory()->suprimentos()->create(), $obra);
    $pedido = Pedido::factory()->create([
        'obra_id' => $obra->id,
        'requester_id' => $o1->id,
        'responsible_id' => $s1->id,
        'status_id' => $this->statuses['em_analise']->id,
    ]);

    $ids = $this->resolver->recipientIdsFor(recipientEventOn($pedido, $o1));

    expect(array_count_values($ids)[$s1->id])->toBe(1);
    expect($ids)->toBe(array_values(array_unique($ids)));
});

test('RF-04b: after the responsible changes to S3, S3 is notified and the previous one is not', function () {
    $obra = Obra::factory()->create();
    $previous = User::factory()->suprimentos()->create();
    $s3 = User::factory()->suprimentos()->create();
    $actor = User::factory()->gestao()->create();
    $pedido = Pedido::factory()->create([
        'obra_id' => $obra->id,
        'responsible_id' => $previous->id,
        'status_id' => $this->statuses['em_analise']->id,
    ]);

    DB::transaction(function () use ($pedido, $s3, $actor, &$ids): void {
        $pedido->update(['responsible_id' => $s3->id]);
        $ids = $this->resolver->recipientIdsFor(recipientEventOn($pedido, $actor, EventTypeSlug::AlteracaoResponsavel));
    });

    expect($ids)->toContain($s3->id);
    expect($ids)->not->toContain($previous->id);
});

test('RF-03 and RF-05: every active gestao except the actor is notified', function () {
    ['pedido' => $pedido] = rf04Matrix();
    $author = User::factory()->gestao()->create();
    $g2 = User::factory()->gestao()->create();
    $g3 = User::factory()->gestao()->create();

    $ids = $this->resolver->recipientIdsFor(recipientEventOn($pedido, $author, EventTypeSlug::AlteracaoPrioridade));

    expect($ids)->toContain($g2->id, $g3->id);
    expect($ids)->not->toContain($author->id);
});

test('RF-05: an associated obra author is not notified about its own event', function () {
    ['pedido' => $pedido, 'o1' => $o1, 's1' => $s1] = rf04Matrix();

    $ids = $this->resolver->recipientIdsFor(recipientEventOn($pedido, $o1, EventTypeSlug::CriacaoPedido));

    expect($ids)->not->toContain($o1->id);
    expect($ids)->toContain($s1->id);
});

test('RF-06: inactive candidates of every papel are not notified', function () {
    ['obra' => $obra, 'pedido' => $pedido] = rf04Matrix();
    $inactiveSuprimentos = associatedTo(User::factory()->suprimentos()->inactive()->create(), $obra);
    $inactiveObra = associatedTo(User::factory()->obra()->inactive()->create(), $obra);
    $inactiveGestao = User::factory()->gestao()->inactive()->create();
    $inactiveResponsible = User::factory()->suprimentos()->inactive()->create();
    $pedido->update(['responsible_id' => $inactiveResponsible->id]);

    $ids = $this->resolver->recipientIdsFor(recipientEventOn($pedido, User::factory()->gestao()->create()));

    expect($ids)->not->toContain($inactiveSuprimentos->id)
        ->not->toContain($inactiveObra->id)
        ->not->toContain($inactiveGestao->id)
        ->not->toContain($inactiveResponsible->id);
});

test('a user with an unknown papel is never notified, even when associated or responsible', function () {
    ['obra' => $obra, 'pedido' => $pedido] = rf04Matrix();
    $semPapel = associatedTo(User::factory()->create(), $obra);
    $pedido->update(['responsible_id' => $semPapel->id]);

    $ids = $this->resolver->recipientIdsFor(recipientEventOn($pedido, User::factory()->gestao()->create()));

    expect($ids)->not->toContain($semPapel->id);
});

test('RF-08: on a pedido "Outra", the obra requester and every other active suprimentos are notified', function () {
    $o1 = User::factory()->obra()->create();
    $otherObraUser = associatedTo(User::factory()->obra()->create(), Obra::factory()->create());
    $unassociatedObraUser = User::factory()->obra()->create();
    $s1 = User::factory()->suprimentos()->create();
    $s2 = User::factory()->suprimentos()->create();
    $s3 = User::factory()->suprimentos()->inactive()->create();
    $pedido = Pedido::factory()->outra('Galpão provisório')->create([
        'requester_id' => $o1->id,
        'status_id' => $this->statuses['em_analise']->id,
    ]);

    $ids = $this->resolver->recipientIdsFor(recipientEventOn($pedido, $s1));

    expect($ids)->toEqualCanonicalizing([$o1->id, $s2->id]);
    expect($ids)->not->toContain($s1->id, $s3->id, $otherObraUser->id, $unassociatedObraUser->id);
});

test('RF-08: on a pedido "Outra" requested by suprimentos, no obra user is notified', function () {
    $requester = User::factory()->suprimentos()->create();
    $obraUser = User::factory()->obra()->create();
    $other = User::factory()->suprimentos()->create();
    $gestao = User::factory()->gestao()->create();
    $pedido = Pedido::factory()->outra()->create([
        'requester_id' => $requester->id,
        'status_id' => $this->statuses['em_analise']->id,
    ]);

    $ids = $this->resolver->recipientIdsFor(recipientEventOn($pedido, $gestao));

    expect($ids)->toEqualCanonicalizing([$requester->id, $other->id]);
    expect($ids)->not->toContain($obraUser->id);
});

test('RNF-09: the resolver runs exactly one query, with or without the associated responsible', function () {
    ['obra' => $obra, 'pedido' => $pedido, 's1' => $s1] = rf04Matrix();
    User::factory()->count(3)->gestao()->create();
    $actor = User::factory()->gestao()->create();
    $event = recipientEventOn($pedido, $actor);

    $countQueries = function () use ($event): array {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $ids = $this->resolver->recipientIdsFor($event);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [$queries, $ids];
    };

    [$withoutResponsibleQueries] = $countQueries();

    $pedido->update(['responsible_id' => $s1->id]);
    [$withResponsibleQueries, $ids] = $countQueries();

    expect($withoutResponsibleQueries)->toBe(1);
    expect($withResponsibleQueries)->toBe(1);
    expect(array_count_values($ids)[$s1->id])->toBe(1);
});

test('the resolver returns ascending integer ids', function () {
    ['pedido' => $pedido] = rf04Matrix();

    $ids = $this->resolver->recipientIdsFor(recipientEventOn($pedido, User::factory()->gestao()->create()));

    expect($ids)->each->toBeInt();
    expect($ids)->toBe(collect($ids)->sort()->values()->all());
});
