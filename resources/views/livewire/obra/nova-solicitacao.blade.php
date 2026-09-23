<div class="mx-auto flex max-w-2xl flex-col gap-5">
    <div>
        <h1 class="page-title">Nova Solicitação</h1>
        <p class="text-sm text-text-muted">Informe a obra, a data em que os itens são necessários e a lista de itens e quantidades.</p>
    </div>

    @if ($code)
        <div role="status" class="flex flex-wrap items-center justify-between gap-3 alert-success">
            <span>Solicitação criada com sucesso! Código: <strong>{{ $code }}</strong></span>
            <a href="{{ route('obra.pedidos.index') }}" class="font-semibold underline">Ver acompanhamento</a>
        </div>
    @endif

    @if ($obras->isEmpty())
        <p role="status" class="alert-info">Nenhuma obra ativa está associada ao seu usuário. Fale com a Gestão ou com Suprimentos.</p>
        <a href="{{ route('obra.pedidos.index') }}" class="btn-secondary">Voltar</a>
    @else
        <form wire:submit="submit" class="card flex flex-col gap-5">
            <div class="flex flex-col gap-1">
                <label for="obra_id" class="form-label">Obra</label>
                <select id="obra_id" wire:model="obra_id" required class="form-control">
                    <option value="">Selecione uma obra</option>
                    @foreach ($obras as $obra)
                        <option value="{{ $obra->id }}">{{ $obra->name }}</option>
                    @endforeach
                </select>
                @error('obra_id') <span role="alert" class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="flex flex-col gap-1">
                <label for="needed_at" class="form-label">Data necessária</label>
                <input id="needed_at" type="date" wire:model="needed_at" required class="form-control sm:max-w-xs">
                @error('needed_at') <span role="alert" class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="flex flex-col gap-1">
                <label for="items_description" class="form-label">Itens e quantidades</label>
                <textarea id="items_description" wire:model="items_description" required rows="6" class="form-control"
                    placeholder="Ex.: 20 sacos de cimento&#10;15 tubos PVC 100mm&#10;5 caixas de parafuso"></textarea>
                <span class="text-xs text-text-muted">Campo livre — descreva um item por linha.</span>
                @error('items_description') <span role="alert" class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-border pt-4">
                <a href="{{ route('obra.pedidos.index') }}" class="btn-secondary">Voltar</a>
                <button type="submit" wire:loading.attr="disabled" class="btn-primary">Enviar solicitação</button>
            </div>
        </form>
    @endif
</div>
