{{-- "Adicionar observação" (RF-24, UI-05): rendered by the Obra and Suprimentos detail screens in every status. --}}
<section aria-label="Adicionar observação" class="card">
    <form wire:submit="adicionarObservacao" class="flex flex-col gap-2">
        <label for="observacao" class="section-title">Adicionar observação</label>
        <textarea id="observacao" wire:model="observacao" rows="3" maxlength="{{ \App\Actions\Pedidos\AddPedidoObservacaoAction::MAX_LENGTH }}"
            aria-describedby="observacao-hint" class="form-control"></textarea>
        <p id="observacao-hint" class="text-xs text-text-muted">Até 2000 caracteres. A observação fica registrada no histórico e não pode ser alterada.</p>
        @error('observacao') <span role="alert" class="form-error">{{ $message }}</span> @enderror
        <div><button type="submit" wire:loading.attr="disabled" class="btn-secondary">Adicionar observação</button></div>
    </form>
</section>
