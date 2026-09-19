<div>
    <h1>Nova Solicitação</h1>

    @if ($code)
        <div role="status">
            Solicitação criada com sucesso! Código: <strong>{{ $code }}</strong>
        </div>
    @endif

    <form wire:submit="submit">
        <div>
            <label for="obra_id">Obra</label>
            <select id="obra_id" wire:model="obra_id" required>
                <option value="">Selecione uma obra</option>
                @foreach ($obras as $obra)
                    <option value="{{ $obra->id }}">{{ $obra->name }}</option>
                @endforeach
            </select>
            @error('obra_id') <span role="alert">{{ $message }}</span> @enderror
        </div>

        <div>
            <label for="needed_at">Data necessária</label>
            <input id="needed_at" type="date" wire:model="needed_at" required>
            @error('needed_at') <span role="alert">{{ $message }}</span> @enderror
        </div>

        <div>
            <label for="items_description">Descrição dos itens</label>
            <textarea id="items_description" wire:model="items_description" required></textarea>
            @error('items_description') <span role="alert">{{ $message }}</span> @enderror
        </div>

        <button type="submit">Enviar solicitação</button>
    </form>
</div>
