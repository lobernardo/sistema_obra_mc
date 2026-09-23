<div class="mx-auto flex max-w-2xl flex-col gap-5">
    <div>
        <h1 class="page-title">{{ $obra ? 'Editar obra' : 'Nova obra' }}</h1>
        <p class="text-sm text-text-muted">Informe nome, responsável (opcional) e status da obra.</p>
    </div>

    <form wire:submit="save" class="card flex flex-col gap-5">
        <div class="flex flex-col gap-1">
            <label for="name" class="form-label">Nome</label>
            <input id="name" type="text" wire:model="name" required maxlength="255" autocomplete="off" class="form-control">
            @error('name') <span role="alert" class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="responsavel" class="form-label">Responsável <span class="font-normal text-text-muted">(opcional)</span></label>
            <input id="responsavel" type="text" wire:model="responsavel" maxlength="255" autocomplete="off" class="form-control">
            @error('responsavel') <span role="alert" class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="status" class="form-label">Status</label>
            <select id="status" wire:model.live="status" required class="form-control sm:max-w-xs">
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                @endforeach
            </select>
            @error('status') <span role="alert" class="form-error">{{ $message }}</span> @enderror
        </div>

        @if ($isConcluidoSelected)
            <div role="status" class="alert-info" data-concluido-notice>
                Obras concluídas deixam de receber novas solicitações. Nenhum pedido, histórico ou associação é excluído.
            </div>
        @endif

        <div class="flex items-center justify-end gap-3 border-t border-border pt-4">
            <a href="{{ route('obras.index') }}" class="btn-secondary">Cancelar</a>
            <button type="submit" wire:loading.attr="disabled" class="btn-primary">{{ $obra ? 'Salvar alterações' : 'Criar obra' }}</button>
        </div>
    </form>
</div>
