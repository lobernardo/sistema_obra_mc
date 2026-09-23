<?php

namespace App\Livewire\Suprimentos;

use App\Actions\Pedidos\AddPedidoObservacaoAction;
use App\Actions\Pedidos\AttachRomaneioAction;
use App\Actions\Pedidos\CancelPedidoAction;
use App\Actions\Pedidos\FinalizePedidoAction;
use App\Actions\Pedidos\UpdatePedidoPrevisaoAction;
use App\Actions\Pedidos\UpdatePedidoPrioridadeAction;
use App\Actions\Pedidos\UpdatePedidoResponsavelAction;
use App\Actions\Pedidos\UpdatePedidoStatusAction;
use App\Enums\PedidoAttachmentKind;
use App\Enums\StatusSlug;
use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Models\PedidoEvent;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Detalhe do pedido (Suprimentos), with the 5 operational controls (RF-12,
 * UI-02): responsável, prioridade, previsão, status and cancelamento — each
 * wired to its own Action so authorization and the terminal-state guard
 * (RF-13b) are enforced identically to every other entry point. Controls are
 * hidden once the pedido reaches a terminal status; "Adicionar observação"
 * stays available in every status (RF-26, UI-05).
 *
 * "Anexar romaneio" and "Finalizar pedido" (UI-07, RF-30..RF-37) are shown
 * while the status is active or Entregue (`StatusSlug::finalizableFrom()`),
 * so on an Entregue pedido they stay available while the operational
 * controls are hidden. The Finalizar button is disabled until a romaneio
 * exists — a complement only: `FinalizePedidoAction` refuses a forged call
 * with the RF-35 message, rendered in a `role="alert"` region.
 */
#[Layout('layouts.app')]
class PedidoDetalhe extends Component
{
    use WithFileUploads;

    public Pedido $pedido;

    public ?int $responsible_id = null;

    public ?int $priority_id = null;

    public string $expected_delivery_at = '';

    public ?int $status_id = null;

    public bool $confirmingCancel = false;

    public string $observacao = '';

    /** @var TemporaryUploadedFile|null */
    public $romaneio = null;

    public bool $confirmingFinalizacao = false;

    public ?string $feedback = null;

    public function mount(Pedido $pedido): void
    {
        $this->authorize('is-suprimentos');
        $this->authorize('view', $pedido);

        $this->pedido = $pedido->loadMissing(['obra', 'status', 'priority', 'responsible', 'requester']);
        $this->responsible_id = $pedido->responsible_id;
        $this->priority_id = $pedido->priority_id;
        $this->expected_delivery_at = $pedido->expected_delivery_at?->toDateString() ?? '';
        $this->status_id = $pedido->status_id;
    }

    public function updateResponsavel(UpdatePedidoResponsavelAction $action): void
    {
        $this->authorize('setResponsavel', $this->pedido);

        $this->pedido = $action->execute(Auth::user(), $this->pedido, $this->responsible_id);
        $this->feedback = 'Responsável atualizado.';
    }

    public function updatePrioridade(UpdatePedidoPrioridadeAction $action): void
    {
        $this->authorize('setPrioridade', $this->pedido);

        $this->pedido = $action->execute(Auth::user(), $this->pedido, $this->priority_id);
        $this->feedback = 'Prioridade atualizada.';
    }

    public function updatePrevisao(UpdatePedidoPrevisaoAction $action): void
    {
        $this->authorize('setPrevisao', $this->pedido);

        $this->pedido = $action->execute(Auth::user(), $this->pedido, $this->expected_delivery_at);
        $this->feedback = 'Previsão de entrega atualizada.';
    }

    public function updateStatus(UpdatePedidoStatusAction $action): void
    {
        $this->authorize('updateStatus', $this->pedido);

        $this->pedido = $action->execute(Auth::user(), $this->pedido, $this->status_id);
        $this->status_id = $this->pedido->status_id;
        $this->feedback = 'Status atualizado para '.$this->pedido->status->name.'.';
    }

    public function confirmCancel(): void
    {
        $this->confirmingCancel = true;
    }

    public function abortCancel(): void
    {
        $this->confirmingCancel = false;
    }

    public function cancelarPedido(CancelPedidoAction $action): void
    {
        $this->authorize('cancelar', $this->pedido);

        $this->pedido = $action->execute(Auth::user(), $this->pedido);
        $this->confirmingCancel = false;
        $this->feedback = 'Pedido cancelado.';
    }

    public function adicionarObservacao(AddPedidoObservacaoAction $action): void
    {
        $this->authorize('addObservacao', $this->pedido);

        $action->execute(Auth::user(), $this->pedido, $this->observacao);
        $this->reset('observacao');
        $this->feedback = 'Observação adicionada.';
    }

    public function anexarRomaneio(AttachRomaneioAction $action): void
    {
        $this->authorize('anexarRomaneio', $this->pedido);

        $attachment = $action->execute(Auth::user(), $this->pedido, $this->romaneio);

        $this->reset('romaneio');
        $this->pedido->unsetRelation('attachments');
        $this->feedback = 'Romaneio "'.$attachment->original_name.'" anexado.';
    }

    public function confirmarFinalizacao(): void
    {
        $this->confirmingFinalizacao = true;
    }

    public function abortarFinalizacao(): void
    {
        $this->confirmingFinalizacao = false;
    }

    public function finalizarPedido(FinalizePedidoAction $action): void
    {
        $this->authorize('finalizar', $this->pedido);

        $this->confirmingFinalizacao = false;
        $this->pedido = $action->execute(Auth::user(), $this->pedido);
        $this->status_id = $this->pedido->status_id;
        $this->feedback = 'Pedido finalizado.';
    }

    /**
     * @return Collection<int, PedidoEvent>
     */
    public function events(): Collection
    {
        return $this->pedido->events()
            ->with(['eventType', 'actor'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function render()
    {
        $this->pedido->loadMissing(['obra', 'status', 'priority', 'responsible', 'requester', 'attachments.uploader']);

        $statusSlug = StatusSlug::from($this->pedido->status->slug);

        return view('livewire.suprimentos.pedido-detalhe', [
            'events' => $this->events(),
            'isTerminal' => $statusSlug->isTerminal(),
            'isFinalizable' => in_array($statusSlug, StatusSlug::finalizableFrom(), true),
            'hasRomaneio' => $this->pedido->attachments
                ->contains(fn (PedidoAttachment $attachment): bool => $attachment->kind === PedidoAttachmentKind::Romaneio),
            'suprimentosUsers' => User::query()->suprimentos()->orderBy('name')->get(),
            'priorities' => Priority::ordered()->get(),
            'statuses' => Status::query()
                ->whereNotIn('slug', [StatusSlug::Cancelado->value, StatusSlug::Finalizado->value])
                ->ordered()
                ->get(),
        ]);
    }
}
