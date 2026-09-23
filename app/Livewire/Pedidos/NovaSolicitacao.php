<?php

namespace App\Livewire\Pedidos;

use App\Actions\Pedidos\CreatePedidoAction;
use App\Enums\PedidoAttachmentKind;
use App\Enums\RoleSlug;
use App\Models\Obra;
use App\Models\Pedido;
use App\Services\PedidoAttachmentStorage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Nova Solicitação shared by Obra and Suprimentos (RF-01, RF-02, RF-04,
 * RF-07, UI-01, CT-01, CT-05).
 *
 * The obra select lists only the requester's associated active obras
 * (`Obra::active()`), by name, followed by "Outra"; with none, the view shows
 * the papel-aware empty state of `CreatePedidoAction::noActiveObraMessage()`
 * and no form. `CreatePedidoAction` owns validation and persistence and
 * re-checks everything server-side, so a forged `obra_selection` is refused
 * even when it bypasses the select's options.
 *
 * Anexos (RF-14, UI-01, RNF-07): the file input has no `wire:model`; Alpine
 * uploads the chosen files one per request into `novoAnexo`, so no upload
 * request ever carries more than one ≤ 10 MB file. `updatedNovoAnexo()`
 * inspects each file for early feedback and appends it to `anexos`; the
 * Action inspects them all again on submit. No `temporaryUrl()`/preview is
 * ever rendered.
 */
#[Layout('layouts.app')]
class NovaSolicitacao extends Component
{
    use WithFileUploads;

    public string $obra_selection = '';

    public string $obra_reference = '';

    public string $descricao = '';

    public string $needed_at = '';

    /** @var list<TemporaryUploadedFile> */
    public array $anexos = [];

    /** @var TemporaryUploadedFile|null */
    public $novoAnexo = null;

    public ?string $code = null;

    public ?string $createdDataPrevista = null;

    public function mount(): void
    {
        $this->authorize('create-pedido');
    }

    public function submit(CreatePedidoAction $action): void
    {
        $this->authorize('create', Pedido::class);

        $pedido = $action->execute(Auth::user(), [
            'obra_selection' => $this->obra_selection,
            'obra_reference' => $this->obra_reference,
            'descricao' => $this->descricao,
            'needed_at' => $this->needed_at,
            'anexos' => $this->anexos,
        ]);

        $this->code = $pedido->code;
        $this->createdDataPrevista = $pedido->dataPrevistaLabel();

        $this->reset(['obra_selection', 'obra_reference', 'descricao', 'needed_at', 'anexos', 'novoAnexo']);
    }

    /**
     * Appends one freshly uploaded file to the list, after the same
     * inspection the Action applies on submit; a refused file never joins
     * the list.
     */
    public function updatedNovoAnexo(): void
    {
        $file = $this->novoAnexo;
        $this->novoAnexo = null;
        $this->resetErrorBag('novoAnexo');

        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }

        if (count($this->anexos) >= PedidoAttachmentStorage::MAX_ANEXOS_POR_PEDIDO) {
            $this->addError('novoAnexo', 'Envie no máximo '.PedidoAttachmentStorage::MAX_ANEXOS_POR_PEDIDO.' anexos.');

            return;
        }

        try {
            app(PedidoAttachmentStorage::class)->inspect($file, PedidoAttachmentKind::Anexo, 'novoAnexo');
        } catch (ValidationException $exception) {
            foreach ($exception->errors()['novoAnexo'] ?? [] as $message) {
                $this->addError('novoAnexo', $message);
            }

            return;
        }

        $this->anexos[] = $file;
    }

    /**
     * Drops a chosen file before submit (UI-01).
     */
    public function removerAnexo(int $index): void
    {
        if (! array_key_exists($index, $this->anexos)) {
            return;
        }

        unset($this->anexos[$index]);
        $this->anexos = array_values($this->anexos);
        $this->resetErrorBag([
            'novoAnexo',
            ...array_filter($this->getErrorBag()->keys(), fn (string $key): bool => str_starts_with($key, 'anexos')),
        ]);
    }

    /**
     * @return Collection<int, Obra>
     */
    public function obras(): Collection
    {
        return Auth::user()->obras()->active()->orderBy('name')->get();
    }

    /**
     * The requester's own pedido listing (UI-01): Acompanhamento for Obra,
     * Todos os Pedidos for Suprimentos.
     */
    public function listingRoute(): string
    {
        return Auth::user()->role?->slug === RoleSlug::Suprimentos->value
            ? route('suprimentos.pedidos.index')
            : route('obra.pedidos.index');
    }

    public function render()
    {
        return view('livewire.pedidos.nova-solicitacao', [
            'obras' => $this->obras(),
            'emptyStateMessage' => CreatePedidoAction::noActiveObraMessage(Auth::user()),
            'listingUrl' => $this->listingRoute(),
            'maxAnexos' => PedidoAttachmentStorage::MAX_ANEXOS_POR_PEDIDO,
            'isOutra' => $this->obra_selection === CreatePedidoAction::OUTRA_SELECTION,
        ]);
    }
}
