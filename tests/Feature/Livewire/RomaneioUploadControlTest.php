<?php

use App\Enums\PedidoAttachmentKind;
use App\Livewire\Suprimentos\PedidoDetalhe;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Models\User;
use App\Services\PedidoAttachmentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * "Anexar romaneio" in the Suprimentos detail (UI-07, RF-30..RF-32):
 * present on active and Entregue pedidos, absent on Cancelado/Finalizado.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    seedHistoryEventTypes();
    Storage::fake(PedidoAttachmentStorage::DISK);

    $this->suprimentos = User::factory()->suprimentos()->create();
    $this->obra = Obra::factory()->create();
});

function romaneioControlPedido(string $status): Pedido
{
    return Pedido::factory()->create([
        'obra_id' => test()->obra->id,
        'status_id' => test()->statuses[$status]->id,
    ]);
}

test('the control is present on active and Entregue pedidos (UI-07)', function (string $status) {
    $this->actingAs($this->suprimentos);

    Livewire::test(PedidoDetalhe::class, ['pedido' => romaneioControlPedido($status)])
        ->assertSee('Anexar romaneio')
        ->assertSee('PDF, JPG ou PNG; até 10 MB.')
        ->assertSeeHtml('wire:submit="anexarRomaneio"')
        ->assertSeeHtml('accept=".pdf,.jpg,.jpeg,.png"');
})->with(['solicitado', 'aguardando_entrega', 'entregue']);

test('the control is absent on Cancelado and Finalizado pedidos (UI-07)', function (string $status) {
    $this->actingAs($this->suprimentos);

    Livewire::test(PedidoDetalhe::class, ['pedido' => romaneioControlPedido($status)])
        ->assertDontSee('Anexar romaneio')
        ->assertDontSeeHtml('anexarRomaneio');
})->with(['cancelado', 'finalizado']);

test('uploading lists the romaneio with its marker and the history event (RF-30, UI-03)', function () {
    $pedido = romaneioControlPedido('entregue');
    $this->actingAs($this->suprimentos);

    $component = Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])
        ->set('romaneio', UploadedFile::fake()->createWithContent('romaneio-1234.pdf', anexoPdfBytes()))
        ->call('anexarRomaneio')
        ->assertHasNoErrors()
        ->assertSet('romaneio', null)
        ->assertSee('Romaneio "romaneio-1234.pdf" anexado.')
        ->assertSeeHtml('data-attachment-kind="romaneio"')
        ->assertSee('Romaneio anexado');

    $attachment = PedidoAttachment::query()->sole();

    expect($attachment->kind)->toBe(PedidoAttachmentKind::Romaneio)
        ->and($component->html())->toContain(route('pedidos.anexos.download', [$pedido, $attachment]));
});

test('an invalid type shows the PT-BR error and writes nothing', function () {
    $pedido = romaneioControlPedido('aguardando_entrega');
    $this->actingAs($this->suprimentos);

    Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])
        ->set('romaneio', UploadedFile::fake()->createWithContent('romaneio.docx', anexoDocxBytes()))
        ->call('anexarRomaneio')
        ->assertHasErrors(['romaneio'])
        ->assertSee('PDF, JPG ou PNG');

    expect(PedidoAttachment::query()->count())->toBe(0);
});

test('a forged anexarRomaneio by obra or gestao on an open Suprimentos detail → 403, 0 rows (RF-31)', function (string $role) {
    $pedido = romaneioControlPedido('aguardando_entrega');
    $this->actingAs($this->suprimentos);
    $testable = Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])
        ->set('romaneio', UploadedFile::fake()->createWithContent('romaneio.pdf', anexoPdfBytes()));

    $actor = User::factory()->{$role}()->create();

    if ($role === 'obra') {
        $actor->obras()->attach($this->obra->id);
    }

    $this->actingAs($actor);

    $testable->call('anexarRomaneio')->assertForbidden();

    expect(PedidoAttachment::query()->count())->toBe(0)
        ->and(Storage::disk(PedidoAttachmentStorage::DISK)->allFiles())->toBe([]);
})->with(['obra', 'gestao']);
