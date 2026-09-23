<?php

use App\Actions\Pedidos\AttachRomaneioAction;
use App\Enums\EventTypeSlug;
use App\Livewire\Suprimentos\PedidoDetalhe;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Models\PedidoEvent;
use App\Models\User;
use App\Services\PedidoAttachmentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * "Finalizar pedido" in the Suprimentos detail (UI-07, RF-33..RF-37).
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    seedHistoryEventTypes();
    Storage::fake(PedidoAttachmentStorage::DISK);

    $this->suprimentos = User::factory()->suprimentos()->create();
    $this->obra = Obra::factory()->create();
});

function finalizarControlPedido(string $status, bool $withRomaneio = false): Pedido
{
    $pedido = Pedido::factory()->create([
        'obra_id' => test()->obra->id,
        'status_id' => test()->statuses[$status]->id,
    ]);

    if ($withRomaneio) {
        app(AttachRomaneioAction::class)->execute(
            test()->suprimentos,
            $pedido,
            UploadedFile::fake()->createWithContent('romaneio.pdf', anexoPdfBytes()),
        );
    }

    return $pedido;
}

function finalizarButtonTag(string $html): string
{
    preg_match('/<button[^>]*data-testid="finalizar-button"[^>]*>/', $html, $matches);

    expect($matches)->toHaveCount(1);

    return $matches[0];
}

test('without a romaneio the button is disabled and the hint is visible (UI-07)', function () {
    $this->actingAs($this->suprimentos);

    $html = Livewire::test(PedidoDetalhe::class, ['pedido' => finalizarControlPedido('aguardando_entrega')])
        ->assertSee('Anexe o romaneio antes de finalizar.')
        ->html();

    expect(finalizarButtonTag($html))->toContain('disabled')
        ->and(finalizarButtonTag($html))->toContain('aria-describedby="finalizar-hint"');
});

test('with a romaneio the button is enabled (UI-07)', function () {
    $this->actingAs($this->suprimentos);

    $html = Livewire::test(PedidoDetalhe::class, ['pedido' => finalizarControlPedido('aguardando_entrega', true)])
        ->assertDontSee('Anexe o romaneio antes de finalizar.')
        ->html();

    expect(finalizarButtonTag($html))->not->toContain('disabled');
});

test('a forged finalizarPedido without romaneio shows the RF-35 message in role="alert" and changes nothing', function () {
    $pedido = finalizarControlPedido('entregue');
    $this->actingAs($this->suprimentos);

    $html = Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])
        ->call('finalizarPedido')
        ->assertHasErrors(['finalizar'])
        ->html();

    expect($html)->toMatch('/<div role="alert"[^>]*>\s*Não foi possível finalizar o pedido\. Anexe o romaneio antes de finalizar\.\s*<\/div>/u');
    expect($pedido->fresh()->status_id)->toBe($this->statuses['entregue']->id)
        ->and(PedidoEvent::query()->where('pedido_id', $pedido->id)->count())->toBe(0);
});

test('with a romaneio, confirming in two steps finalizes and shows the Finalizado badge (RF-34, UI-07)', function () {
    $pedido = finalizarControlPedido('entregue', true);
    $this->actingAs($this->suprimentos);

    $component = Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])
        ->call('confirmarFinalizacao')
        ->assertSet('confirmingFinalizacao', true)
        ->assertSeeHtml('data-testid="finalizar-confirm-dialog"');

    expect($pedido->fresh()->status_id)->toBe($this->statuses['entregue']->id);

    $component->call('finalizarPedido')
        ->assertHasNoErrors()
        ->assertSee('Pedido finalizado.')
        ->assertSeeHtml('data-status="finalizado"')
        ->assertDontSeeHtml('data-testid="finalizar-button"')
        ->assertDontSee('Anexar romaneio');

    expect($pedido->fresh()->status_id)->toBe($this->statuses['finalizado']->id)
        ->and(PedidoEvent::query()->where('pedido_id', $pedido->id)
            ->whereHas('eventType', fn ($query) => $query->where('slug', EventTypeSlug::Finalizacao->value))
            ->count())->toBe(1);
});

test('abortarFinalizacao closes the confirmation without changing anything', function () {
    $pedido = finalizarControlPedido('aguardando_entrega', true);
    $this->actingAs($this->suprimentos);

    Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])
        ->call('confirmarFinalizacao')
        ->call('abortarFinalizacao')
        ->assertSet('confirmingFinalizacao', false)
        ->assertSeeHtml('data-testid="finalizar-button"');

    expect($pedido->fresh()->status_id)->toBe($this->statuses['aguardando_entrega']->id);
});

test('on Entregue the operational controls stay hidden while romaneio and Finalizar are present (UI-07)', function () {
    $this->actingAs($this->suprimentos);

    Livewire::test(PedidoDetalhe::class, ['pedido' => finalizarControlPedido('entregue')])
        ->assertSee('Anexar romaneio')
        ->assertSeeHtml('data-testid="finalizar-button"')
        ->assertDontSeeHtml('wire:submit="updateStatus"')
        ->assertDontSeeHtml('wire:submit="updateResponsavel"')
        ->assertDontSeeHtml('wire:submit="updatePrioridade"')
        ->assertDontSeeHtml('wire:submit="updatePrevisao"')
        ->assertDontSeeHtml('wire:click="confirmCancel"');
});

test('Finalizar is absent on Cancelado and Finalizado; the status select never offers Finalizado (UI-07, RF-36)', function (string $status) {
    $this->actingAs($this->suprimentos);

    Livewire::test(PedidoDetalhe::class, ['pedido' => finalizarControlPedido($status)])
        ->assertDontSeeHtml('data-testid="finalizar-button"')
        ->assertDontSeeHtml('finalizarPedido');
})->with(['cancelado', 'finalizado']);

test('the status select of an active pedido has no Finalizado option (UI-07)', function () {
    $this->actingAs($this->suprimentos);

    $html = Livewire::test(PedidoDetalhe::class, ['pedido' => finalizarControlPedido('em_analise')])->html();

    preg_match('/<select id="status_id".*?<\/select>/s', $html, $select);

    expect($select[0])->not->toContain('value="'.$this->statuses['finalizado']->id.'"');
});

test('a forged finalizarPedido by obra or gestao on an open Suprimentos detail → 403 (RF-36)', function (string $role) {
    $pedido = finalizarControlPedido('entregue', true);
    $this->actingAs($this->suprimentos);
    $testable = Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido]);

    $actor = User::factory()->{$role}()->create();

    if ($role === 'obra') {
        $actor->obras()->attach($this->obra->id);
    }

    $this->actingAs($actor);

    $testable->call('finalizarPedido')->assertForbidden();

    expect($pedido->fresh()->status_id)->toBe($this->statuses['entregue']->id)
        ->and(PedidoAttachment::query()->count())->toBe(1);
})->with(['obra', 'gestao']);
