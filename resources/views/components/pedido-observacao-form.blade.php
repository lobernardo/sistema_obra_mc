{{-- "Observação / ocorrência" (UI-09, RF-10..RF-13, CT-04): free text, no category; rendered by the Obra and Suprimentos/Gestão detail screens in every status. --}}
<section aria-label="Observação / ocorrência" class="card">
    <form wire:submit="adicionarObservacao" class="flex flex-col gap-2">
        <label for="observacao" class="section-title">Observação / ocorrência</label>
        <textarea id="observacao" wire:model="observacao" rows="3" maxlength="{{ \App\Actions\Pedidos\AddPedidoObservacaoAction::MAX_LENGTH }}"
            aria-describedby="observacao-hint" class="form-control"></textarea>
        <p id="observacao-hint" class="text-xs text-text-muted">Registre falta de produto, troca, atraso, problema de entrega ou qualquer informação relevante. Até 2000 caracteres. Fica no histórico e avisa os envolvidos; não pode ser alterada.</p>
        @error('observacao') <span role="alert" class="form-error">{{ $message }}</span> @enderror
        <div><button type="submit" wire:loading.attr="disabled" class="btn-secondary">Registrar observação / ocorrência</button></div>
    </form>
</section>
