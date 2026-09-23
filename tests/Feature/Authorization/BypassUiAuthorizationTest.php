<?php

use App\Actions\Obras\AcceptObraInvitationAction;
use App\Actions\Obras\CreateObraAction;
use App\Actions\Obras\GenerateObraInvitationAction;
use App\Actions\Obras\RevokeObraInvitationAction;
use App\Actions\Obras\UpdateObraAction;
use App\Actions\Pedidos\AddPedidoObservacaoAction;
use App\Actions\Pedidos\AttachRomaneioAction;
use App\Actions\Pedidos\CancelPedidoAction;
use App\Actions\Pedidos\CreatePedidoAction;
use App\Actions\Pedidos\FinalizePedidoAction;
use App\Actions\Pedidos\MarkPedidoEntregueByObraAction;
use App\Actions\Pedidos\UpdatePedidoPrevisaoAction;
use App\Actions\Pedidos\UpdatePedidoPrioridadeAction;
use App\Actions\Pedidos\UpdatePedidoResponsavelAction;
use App\Actions\Pedidos\UpdatePedidoStatusAction;
use App\Actions\Usuarios\AttachUserObrasAction;
use App\Actions\Usuarios\DetachUserObraAction;
use App\Models\Obra;
use App\Models\ObraInvitation;
use App\Models\Pedido;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
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
    $requester->obras()->attach(Obra::factory()->create()->id);
    $foreignObra = Obra::factory()->create();

    $action = app(CreatePedidoAction::class);

    expect(fn () => $action->execute($requester, [
        'obra_selection' => $foreignObra->id,
        'needed_at' => now()->addDays(10)->toDateString(),
        'descricao' => 'Itens forjados via payload direto.',
    ]))->toThrow(function (ValidationException $exception): void {
        expect($exception->errors())->toBe([
            'obra_id' => ['A obra informada não está associada ao solicitante.'],
        ]);
    });

    expect(Pedido::query()->where('obra_id', $foreignObra->id)->exists())->toBeFalse();
});

test('createSolicitacao is rejected when called directly by a gestao actor (RF-01)', function () {
    $actor = User::factory()->gestao()->create();

    expect(fn () => app(CreatePedidoAction::class)->execute($actor, [
        'obra_selection' => 'outra',
        'needed_at' => now()->addDays(10)->toDateString(),
        'descricao' => 'Itens forjados via payload direto.',
    ]))->toThrow(AuthorizationException::class);

    expect(Pedido::query()->count())->toBe(0);
});

test('the obra, convite and association Actions are rejected when called directly by an obra actor (RF-07)', function (Closure $call) {
    $actor = User::factory()->obra()->create();
    $obra = Obra::factory()->create();

    expect(fn () => $call($actor, $obra))->toThrow(AuthorizationException::class);
})->with([
    'createObra' => [fn (User $actor) => app(CreateObraAction::class)->execute($actor, ['name' => 'Forjada', 'responsavel' => '', 'status' => 'em_andamento'])],
    'updateObra' => [fn (User $actor, Obra $obra) => app(UpdateObraAction::class)->execute($actor, $obra, ['name' => 'Forjada', 'responsavel' => '', 'status' => 'concluido'])],
    'generateInvitation' => [fn (User $actor, Obra $obra) => app(GenerateObraInvitationAction::class)->execute($actor, $obra)],
    'revokeInvitation' => [fn (User $actor, Obra $obra) => app(RevokeObraInvitationAction::class)->execute($actor, ObraInvitation::factory()->for($obra)->create())],
    'attachUserObras' => [fn (User $actor, Obra $obra) => app(AttachUserObrasAction::class)->execute($actor, User::factory()->obra()->create(), ['obra_ids' => [$obra->id]])],
    'detachUserObra' => [fn (User $actor, Obra $obra) => app(DetachUserObraAction::class)->execute($actor, User::factory()->obra()->create(), ['obra_id' => $obra->id])],
]);

test('acceptAsExistingAccount is rejected when called directly for a non-obra account, without consuming the convite (RF-31)', function (string $role) {
    $invitation = ObraInvitation::factory()->create();

    expect(fn () => app(AcceptObraInvitationAction::class)->acceptAsExistingAccount(User::factory()->{$role}()->create(), $invitation->id))
        ->toThrow(ValidationException::class, AcceptObraInvitationAction::ROLE_MISMATCH_MESSAGE);

    expect($invitation->fresh()->used_at)->toBeNull();
})->with(['gestao', 'suprimentos']);

test('attachRomaneio is rejected when called directly by a non-suprimentos actor (RF-31)', function (string $role) {
    seedWorkflowStatuses();
    $actor = User::factory()->{$role}()->create();
    $pedido = Pedido::factory()->create(['status_id' => Status::query()->where('slug', 'entregue')->value('id')]);
    $actor->obras()->syncWithoutDetaching([$pedido->obra_id]);

    expect(fn () => app(AttachRomaneioAction::class)->execute($actor, $pedido, UploadedFile::fake()->createWithContent('romaneio.pdf', anexoPdfBytes())))
        ->toThrow(AuthorizationException::class);

    expect($pedido->attachments()->count())->toBe(0);
})->with('non suprimentos roles');

test('finalizar is rejected when called directly by a non-suprimentos actor (RF-36)', function (string $role) {
    seedWorkflowStatuses();
    $actor = User::factory()->{$role}()->create();
    $pedido = Pedido::factory()->create(['status_id' => Status::query()->where('slug', 'entregue')->value('id')]);
    $actor->obras()->syncWithoutDetaching([$pedido->obra_id]);

    expect(fn () => app(FinalizePedidoAction::class)->execute($actor, $pedido))
        ->toThrow(AuthorizationException::class);

    expect($pedido->fresh()->status->slug)->toBe('entregue');
})->with('non suprimentos roles');

test('marcarEntregue is rejected when called directly by a non-obra actor or an obra user without view (RF-28)', function (string $role) {
    seedWorkflowStatuses();
    $actor = User::factory()->{$role}()->create();
    $pedido = Pedido::factory()->create(['status_id' => Status::query()->where('slug', 'aguardando_entrega')->value('id')]);

    expect(fn () => app(MarkPedidoEntregueByObraAction::class)->execute($actor, $pedido))
        ->toThrow(AuthorizationException::class);

    expect($pedido->fresh()->status->slug)->toBe('aguardando_entrega');
})->with(['obra', 'suprimentos', 'gestao']);

test('addObservacao is rejected when called directly by gestao or an obra user without view (RF-25)', function (string $role) {
    seedWorkflowStatuses();
    $actor = User::factory()->{$role}()->create();
    $pedido = Pedido::factory()->create(['status_id' => Status::query()->where('slug', 'em_analise')->value('id')]);

    expect(fn () => app(AddPedidoObservacaoAction::class)->execute($actor, $pedido, 'Forjada'))
        ->toThrow(AuthorizationException::class);

    expect($pedido->events()->count())->toBe(0);
})->with(['obra', 'gestao']);
