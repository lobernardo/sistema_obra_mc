<?php

use App\Actions\Pedidos\AddPedidoObservacaoAction;
use App\Actions\Pedidos\AttachRomaneioAction;
use App\Actions\Pedidos\CreatePedidoAction;
use App\Actions\Pedidos\FinalizePedidoAction;
use App\Actions\Pedidos\MarkPedidoEntregueByObraAction;
use App\Livewire\Pedidos\NovaSolicitacao;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Models\PedidoEvent;
use App\Models\User;
use App\Services\PedidoAttachmentStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/**
 * RF-01, RF-25, RF-28, RF-31, RF-36 — every new write of this slice is
 * refused for a forbidden papel at every layer. Livewire methods are driven
 * through the real `/livewire/update` transport: a page is opened by an
 * allowed user and its snapshot is replayed by the forbidden one, exactly
 * as a forged request from a stolen or shared page would arrive.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    seedHistoryEventTypes();
    Storage::fake(PedidoAttachmentStorage::DISK);

    $this->obraUser = User::factory()->obra()->create();
    $this->obra = Obra::factory()->create();
    $this->obraUser->obras()->attach($this->obra->id);
    $this->suprimentos = User::factory()->suprimentos()->create();
    $this->suprimentos->obras()->attach($this->obra->id);
    $this->gestao = User::factory()->gestao()->create();

    $this->pedido = Pedido::factory()->for($this->obraUser, 'requester')->create([
        'obra_id' => $this->obra->id,
        'status_id' => $this->statuses['entregue']->id,
    ]);
    app(AttachRomaneioAction::class)->execute($this->suprimentos, $this->pedido, UploadedFile::fake()->createWithContent('romaneio.pdf', anexoPdfBytes()));
});

/**
 * @return array{pedidos: int, events: int, attachments: int, files: int, sequence: string, status_id: int}
 */
function operationsWriteState(): array
{
    return [
        'pedidos' => Pedido::query()->count(),
        'events' => PedidoEvent::query()->count(),
        'attachments' => PedidoAttachment::query()->count(),
        'files' => count(Storage::disk(PedidoAttachmentStorage::DISK)->allFiles()),
        'sequence' => json_encode(DB::selectOne('select last_value, is_called from pedido_code_sequence')),
        'status_id' => Pedido::query()->whereKey(test()->pedido->id)->value('status_id'),
    ];
}

function operationsPageSnapshot(TestResponse $response): string
{
    preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);

    expect($matches)->toHaveCount(2, 'No wire:snapshot found in the rendered page.');

    return htmlspecialchars_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE);
}

/**
 * @param  array<string, mixed>  $updates
 */
function operationsLivewireCall(string $snapshot, string $method, array $updates = []): TestResponse
{
    Livewire::flushState();

    $updateUri = app('router')->getRoutes()->getByName('default-livewire.update')->uri();

    return test()->withHeaders(['X-Livewire' => 'true'])->postJson('/'.$updateUri, [
        '_token' => csrf_token(),
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => $updates,
            'calls' => [['method' => $method, 'params' => []]],
        ]],
    ]);
}

test('each new Livewire method replayed through /livewire/update by a forbidden papel → 403 and 0 rows', function (string $opener, Closure $route, string $method, array $updates, string $forbidden) {
    $this->actingAs($this->{$opener});
    $snapshot = operationsPageSnapshot($this->get($route($this->pedido))->assertOk());

    $before = operationsWriteState();

    $this->actingAs($this->{$forbidden});

    operationsLivewireCall($snapshot, $method, $updates)->assertForbidden();

    expect(operationsWriteState())->toBe($before);
})->with([
    'submit by gestao' => ['obraUser', fn () => route('obra.nova-solicitacao'), 'submit', ['obra_selection' => 'outra', 'descricao' => 'Forjado', 'needed_at' => '2026-10-01'], 'gestao'],
    'adicionarObservacao (Obra detail) by gestao' => ['obraUser', fn (Pedido $pedido) => route('obra.pedidos.show', $pedido), 'adicionarObservacao', ['observacao' => 'Forjada'], 'gestao'],
    'adicionarObservacao (Suprimentos detail) by gestao' => ['suprimentos', fn (Pedido $pedido) => route('suprimentos.pedidos.show', $pedido), 'adicionarObservacao', ['observacao' => 'Forjada'], 'gestao'],
    'marcarComoEntregue by gestao' => ['obraUser', fn (Pedido $pedido) => route('obra.pedidos.show', $pedido), 'marcarComoEntregue', [], 'gestao'],
    'marcarComoEntregue by suprimentos' => ['obraUser', fn (Pedido $pedido) => route('obra.pedidos.show', $pedido), 'marcarComoEntregue', [], 'suprimentos'],
    'anexarRomaneio by obra' => ['suprimentos', fn (Pedido $pedido) => route('suprimentos.pedidos.show', $pedido), 'anexarRomaneio', [], 'obraUser'],
    'anexarRomaneio by gestao' => ['suprimentos', fn (Pedido $pedido) => route('suprimentos.pedidos.show', $pedido), 'anexarRomaneio', [], 'gestao'],
    'finalizarPedido by obra' => ['suprimentos', fn (Pedido $pedido) => route('suprimentos.pedidos.show', $pedido), 'finalizarPedido', [], 'obraUser'],
    'finalizarPedido by gestao' => ['suprimentos', fn (Pedido $pedido) => route('suprimentos.pedidos.show', $pedido), 'finalizarPedido', [], 'gestao'],
]);

test('an obra user of another obra replaying marcarComoEntregue and adicionarObservacao → 403 and 0 rows (RF-25, RF-28)', function (string $method, array $updates) {
    $pedido = Pedido::factory()->for($this->obraUser, 'requester')->create([
        'obra_id' => $this->obra->id,
        'status_id' => $this->statuses['aguardando_entrega']->id,
    ]);
    $outsider = User::factory()->obra()->create();
    $outsider->obras()->attach(Obra::factory()->create()->id);

    $this->actingAs($this->obraUser);
    $snapshot = operationsPageSnapshot($this->get(route('obra.pedidos.show', $pedido))->assertOk());
    $before = PedidoEvent::query()->count();

    $this->actingAs($outsider);

    operationsLivewireCall($snapshot, $method, $updates)->assertForbidden();

    expect(PedidoEvent::query()->count())->toBe($before)
        ->and($pedido->fresh()->status_id)->toBe($this->statuses['aguardando_entrega']->id);
})->with([
    'marcarComoEntregue' => ['marcarComoEntregue', []],
    'adicionarObservacao' => ['adicionarObservacao', ['observacao' => 'Forjada']],
]);

test('each new Action called directly with a forbidden actor → AuthorizationException and 0 rows', function (Closure $call, string $actor) {
    $before = operationsWriteState();

    expect(fn () => $call($this->{$actor}, $this->pedido->fresh()))->toThrow(AuthorizationException::class);

    expect(operationsWriteState())->toBe($before);
})->with([
    'observação by gestao' => [fn (User $actor, Pedido $pedido) => app(AddPedidoObservacaoAction::class)->execute($actor, $pedido, 'Forjada'), 'gestao'],
    'obra entregue by gestao' => [fn (User $actor, Pedido $pedido) => app(MarkPedidoEntregueByObraAction::class)->execute($actor, $pedido), 'gestao'],
    'obra entregue by suprimentos' => [fn (User $actor, Pedido $pedido) => app(MarkPedidoEntregueByObraAction::class)->execute($actor, $pedido), 'suprimentos'],
    'romaneio by obra' => [fn (User $actor, Pedido $pedido) => app(AttachRomaneioAction::class)->execute($actor, $pedido, UploadedFile::fake()->createWithContent('r.pdf', anexoPdfBytes())), 'obraUser'],
    'romaneio by gestao' => [fn (User $actor, Pedido $pedido) => app(AttachRomaneioAction::class)->execute($actor, $pedido, UploadedFile::fake()->createWithContent('r.pdf', anexoPdfBytes())), 'gestao'],
    'finalizar by obra' => [fn (User $actor, Pedido $pedido) => app(FinalizePedidoAction::class)->execute($actor, $pedido), 'obraUser'],
    'finalizar by gestao' => [fn (User $actor, Pedido $pedido) => app(FinalizePedidoAction::class)->execute($actor, $pedido), 'gestao'],
    'creation by gestao' => [fn (User $actor) => app(CreatePedidoAction::class)->execute($actor, ['obra_selection' => 'outra', 'descricao' => 'Forjado', 'needed_at' => '2026-10-01']), 'gestao'],
]);

test('gestao creation is denied at every layer and never advances pedido_code_sequence (RF-01)', function () {
    $gestao = $this->gestao;
    $gestao->obras()->attach($this->obra->id);
    $before = operationsWriteState();

    $this->actingAs($gestao);

    $this->get(route('obra.nova-solicitacao'))->assertForbidden();
    $this->get(route('suprimentos.nova-solicitacao'))->assertForbidden();

    Livewire::test(NovaSolicitacao::class)->assertForbidden();

    expect(Gate::forUser($gestao)->allows('create-pedido'))->toBeFalse()
        ->and(Gate::forUser($gestao)->allows('create', Pedido::class))->toBeFalse();

    expect(fn () => app(CreatePedidoAction::class)->execute($gestao, [
        'obra_selection' => (string) $this->obra->id,
        'descricao' => 'Forjado',
        'needed_at' => '2026-10-01',
    ]))->toThrow(AuthorizationException::class);

    expect(operationsWriteState())->toBe($before);
});

test('the policy abilities of the new operations grant exactly the allowed papéis', function () {
    $outsider = User::factory()->obra()->create();

    expect(Gate::forUser($this->obraUser)->allows('marcarEntregue', $this->pedido))->toBeTrue()
        ->and(Gate::forUser($outsider)->allows('marcarEntregue', $this->pedido))->toBeFalse()
        ->and(Gate::forUser($this->suprimentos)->allows('marcarEntregue', $this->pedido))->toBeFalse()
        ->and(Gate::forUser($this->gestao)->allows('marcarEntregue', $this->pedido))->toBeFalse();

    foreach (['anexarRomaneio', 'finalizar'] as $ability) {
        expect(Gate::forUser($this->suprimentos)->allows($ability, $this->pedido))->toBeTrue()
            ->and(Gate::forUser($this->obraUser)->allows($ability, $this->pedido))->toBeFalse()
            ->and(Gate::forUser($this->gestao)->allows($ability, $this->pedido))->toBeFalse();
    }
});

test('an inactive user\'s open detail page is cut on the next Livewire call and writes nothing', function (string $actor, Closure $route, string $method, array $updates) {
    $pedido = Pedido::factory()->for($this->obraUser, 'requester')->create([
        'obra_id' => $this->obra->id,
        'status_id' => $this->statuses['aguardando_entrega']->id,
    ]);
    $user = $this->{$actor};

    $this->actingAs($user);
    $snapshot = operationsPageSnapshot($this->get($route($pedido))->assertOk());
    $before = PedidoEvent::query()->count();

    $user->update(['is_active' => false]);

    operationsLivewireCall($snapshot, $method, $updates)->assertRedirect(route('login'));

    expect(Auth::check())->toBeFalse()
        ->and(PedidoEvent::query()->count())->toBe($before)
        ->and($pedido->fresh()->status_id)->toBe($this->statuses['aguardando_entrega']->id);
})->with([
    'obra marcarComoEntregue' => ['obraUser', fn (Pedido $pedido) => route('obra.pedidos.show', $pedido), 'marcarComoEntregue', []],
    'obra adicionarObservacao' => ['obraUser', fn (Pedido $pedido) => route('obra.pedidos.show', $pedido), 'adicionarObservacao', ['observacao' => 'Tarde demais']],
    'suprimentos finalizarPedido' => ['suprimentos', fn (Pedido $pedido) => route('suprimentos.pedidos.show', $pedido), 'finalizarPedido', []],
]);
