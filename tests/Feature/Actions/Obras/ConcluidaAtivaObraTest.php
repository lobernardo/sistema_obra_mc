<?php

use App\Actions\Obras\AcceptObraInvitationAction;
use App\Actions\Obras\GenerateObraInvitationAction;
use App\Actions\Pedidos\CreatePedidoAction;
use App\Actions\Usuarios\AttachUserObrasAction;
use App\Enums\ObraStatus;
use App\Livewire\Pedidos\NovaSolicitacao;
use App\Livewire\Suprimentos\TodosPedidos;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\User;
use Livewire\Livewire;

/**
 * RF-04 and RF-13 of `obras-ativacao-exclusao`: Status is descriptive only.
 * A Concluído obra that is still active (`is_active = true`) accepts new
 * pedidos, convites (generation and acceptance) and associations, and the
 * "Somente obras ativas" filter reads only `is_active`.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    EventType::factory()->criacaoPedido()->create();

    $this->obra = Obra::factory()->concluida()->create(['name' => 'Residencial Concluído Ativo']);

    expect($this->obra->status)->toBe(ObraStatus::Concluido);
    expect($this->obra->isActive())->toBeTrue();
});

test('a pedido is created on a Concluído + active obra and the obra is offered in Nova Solicitação (RF-04)', function () {
    $requester = User::factory()->obra()->create();
    $requester->obras()->attach($this->obra->id);

    $html = Livewire::actingAs($requester)->test(NovaSolicitacao::class)->html();

    expect($html)->toContain('Residencial Concluído Ativo');

    $pedido = app(CreatePedidoAction::class)->execute($requester, [
        'obra_selection' => $this->obra->id,
        'needed_at' => '2026-07-01',
        'descricao' => 'Cimento e areia',
    ]);

    expect($pedido->obra_id)->toBe($this->obra->id);
    expect(Pedido::query()->where('obra_id', $this->obra->id)->count())->toBe(1);
});

test('a convite is generated and accepted on a Concluído + active obra (RF-04)', function () {
    $creator = User::factory()->suprimentos()->create();
    $invitee = User::factory()->obra()->create();

    $result = app(GenerateObraInvitationAction::class)->execute($creator, $this->obra);
    $token = parse_url($result['url'], PHP_URL_FRAGMENT);

    $action = app(AcceptObraInvitationAction::class);

    expect($action->resolveByToken($token)->is($result['invitation']))->toBeTrue();

    $action->acceptAsExistingAccount($invitee, $result['invitation']->id);

    expect($result['invitation']->fresh()->used_at)->not->toBeNull();
    expect($result['invitation']->fresh()->used_by)->toBe($invitee->id);
    expect($invitee->obras()->pluck('obras.id')->all())->toBe([$this->obra->id]);
});

test('a Concluído + active obra is attached as a new association (RF-04)', function () {
    $actor = User::factory()->gestao()->create();
    $target = User::factory()->obra()->create();

    app(AttachUserObrasAction::class)->execute($actor, $target, ['obra_ids' => [$this->obra->id]]);

    expect($target->obras()->pluck('obras.id')->all())->toBe([$this->obra->id]);
});

test('obrasAtivas=1 excludes inactive + em_andamento, includes active + concluido and "Outra" (RF-13)', function () {
    $this->actingAs(User::factory()->suprimentos()->create());

    $inativaEmAndamento = Obra::factory()->emAndamento()->inactive()->create();

    $daInativa = Pedido::factory()->create(['obra_id' => $inativaEmAndamento->id, 'status_id' => $this->statuses['solicitado']->id]);
    $daConcluidaAtiva = Pedido::factory()->create(['obra_id' => $this->obra->id, 'status_id' => $this->statuses['solicitado']->id]);
    $outra = Pedido::factory()->outra()->create(['status_id' => $this->statuses['solicitado']->id]);

    $component = Livewire::withQueryParams(['obrasAtivas' => 1])->test(TodosPedidos::class)->assertSet('activeObrasOnly', true);
    $codes = collect($component->viewData('pedidos')->items())->pluck('code')->all();

    expect($codes)->toContain($daConcluidaAtiva->code, $outra->code)
        ->not->toContain($daInativa->code);

    $allCodes = collect($component->set('activeObrasOnly', false)->viewData('pedidos')->items())->pluck('code')->all();

    expect($allCodes)->toContain($daInativa->code, $daConcluidaAtiva->code, $outra->code);
});
