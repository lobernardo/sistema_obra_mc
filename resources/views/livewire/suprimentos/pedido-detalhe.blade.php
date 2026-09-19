<div>
    <h1>Pedido {{ $pedido->code }}</h1>

    <dl>
        <dt>Obra</dt>
        <dd>{{ $pedido->obra->name }}</dd>

        <dt>Status</dt>
        <dd>{{ $pedido->status->name }}</dd>

        <dt>Prioridade</dt>
        <dd>{{ $pedido->priority?->name ?? '—' }}</dd>

        <dt>Responsável</dt>
        <dd>{{ $pedido->responsible?->name ?? '—' }}</dd>

        <dt>Data necessária</dt>
        <dd>{{ $pedido->needed_at->format('d/m/Y') }}</dd>

        <dt>Previsão de entrega</dt>
        <dd>{{ $pedido->expected_delivery_at?->format('d/m/Y') ?? '—' }}</dd>

        <dt>Descrição dos itens</dt>
        <dd>{{ $pedido->items_description }}</dd>
    </dl>

    @unless ($isTerminal)
        <section aria-label="Responsável">
            <form wire:submit="updateResponsavel">
                <label for="responsible_id">Responsável</label>
                <select id="responsible_id" wire:model="responsible_id">
                    <option value="">Selecione um responsável</option>
                    @foreach ($suprimentosUsers as $suprimentosUser)
                        <option value="{{ $suprimentosUser->id }}">{{ $suprimentosUser->name }}</option>
                    @endforeach
                </select>
                @error('responsible_id') <span role="alert">{{ $message }}</span> @enderror
                <button type="submit">Salvar responsável</button>
            </form>
        </section>

        <section aria-label="Prioridade">
            <form wire:submit="updatePrioridade">
                <label for="priority_id">Prioridade</label>
                <select id="priority_id" wire:model="priority_id">
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->id }}">{{ $priority->name }}</option>
                    @endforeach
                </select>
                @error('priority_id') <span role="alert">{{ $message }}</span> @enderror
                <button type="submit">Salvar prioridade</button>
            </form>
        </section>

        <section aria-label="Previsão de entrega">
            <form wire:submit="updatePrevisao">
                <label for="expected_delivery_at">Previsão de entrega</label>
                <input id="expected_delivery_at" type="date" wire:model="expected_delivery_at">
                @error('expected_delivery_at') <span role="alert">{{ $message }}</span> @enderror
                <button type="submit">Salvar previsão</button>
            </form>
        </section>

        <section aria-label="Status">
            <form wire:submit="updateStatus">
                <label for="status_id">Status</label>
                <select id="status_id" wire:model="status_id">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->id }}">{{ $status->name }}</option>
                    @endforeach
                </select>
                @error('status_id') <span role="alert">{{ $message }}</span> @enderror
                <button type="submit">Mover status</button>
            </form>
        </section>

        <section aria-label="Cancelamento">
            @if (! $confirmingCancel)
                <button type="button" wire:click="confirmCancel" data-testid="cancel-button">Cancelar pedido</button>
            @else
                <div role="alertdialog" aria-label="Confirmar cancelamento" data-testid="cancel-confirm-dialog">
                    <p>Tem certeza que deseja cancelar este pedido? Esta ação é irreversível.</p>
                    <button type="button" wire:click="cancelarPedido" data-testid="cancel-confirm">Confirmar cancelamento</button>
                    <button type="button" wire:click="abortCancel" data-testid="cancel-abort">Voltar</button>
                </div>
            @endif
        </section>
    @endunless

    <h2>Histórico</h2>
    <x-pedido-history-timeline :events="$events" />
</div>
