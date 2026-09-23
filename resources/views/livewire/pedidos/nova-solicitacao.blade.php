<div class="mx-auto flex max-w-2xl flex-col gap-5">
    <div>
        <h1 class="page-title">Nova Solicitação</h1>
        <p class="text-sm text-text-muted">Informe a obra, a descrição do que precisa e a data em Preciso para.</p>
    </div>

    @if ($code)
        <div role="status" class="flex flex-wrap items-center justify-between gap-3 alert-success">
            <span>
                Solicitação criada com sucesso! Código: <strong>{{ $code }}</strong>
                <span class="block">Data prevista: {{ $createdDataPrevista }}</span>
            </span>
            <a href="{{ $listingUrl }}" class="font-semibold underline">Ver pedidos</a>
        </div>
    @endif

    @if ($obras->isEmpty())
        <p role="status" class="alert-info">{{ $emptyStateMessage }}</p>
        <a href="{{ $listingUrl }}" class="btn-secondary">Voltar</a>
    @else
        <form wire:submit="submit" class="card flex flex-col gap-5">
            <div class="flex flex-col gap-1">
                <label for="obra_selection" class="form-label">Obra</label>
                <select id="obra_selection" wire:model.live="obra_selection" required class="form-control">
                    <option value="">Selecione uma obra</option>
                    @foreach ($obras as $obra)
                        <option value="{{ $obra->id }}">{{ $obra->name }}</option>
                    @endforeach
                    <option value="{{ \App\Actions\Pedidos\CreatePedidoAction::OUTRA_SELECTION }}">Outra</option>
                </select>
                @error('obra_id') <span role="alert" class="form-error">{{ $message }}</span> @enderror
            </div>

            @if ($isOutra)
                <div class="flex flex-col gap-1">
                    <label for="obra_reference" class="form-label">Referência <span class="font-normal text-text-muted">(opcional)</span></label>
                    <input id="obra_reference" type="text" wire:model="obra_reference" maxlength="255" class="form-control"
                        placeholder="Ex.: Galpão provisório">
                    @error('obra_reference') <span role="alert" class="form-error">{{ $message }}</span> @enderror
                </div>
            @endif

            <div class="flex flex-col gap-1">
                <label for="descricao" class="form-label">Descrição</label>
                <textarea id="descricao" wire:model="descricao" required rows="6" class="form-control"
                    placeholder="Ex.: 20 sacos de cimento&#10;15 tubos PVC 100mm&#10;5 caixas de parafuso"></textarea>
                <span class="text-xs text-text-muted">Campo livre — descreva um item por linha.</span>
                @error('descricao') <span role="alert" class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="flex flex-col gap-1">
                <label for="needed_at" class="form-label">Preciso para</label>
                <input id="needed_at" type="date" wire:model="needed_at" required class="form-control sm:max-w-xs">
                @error('needed_at') <span role="alert" class="form-error">{{ $message }}</span> @enderror
            </div>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <dt class="form-label">Data da solicitação</dt>
                    <dd data-field="requested_at" class="text-sm text-text">{{ \App\Support\LocalTime::today()->format('d/m/Y') }}</dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="form-label">Data prevista</dt>
                    <dd data-field="data_prevista" class="text-sm text-text">{{ \App\Models\Pedido::presentDataPrevista(\App\Domain\Pedidos\DataPrevistaCalculator::forRequestedAt(now())) }}</dd>
                    <dd class="text-xs text-text-muted">Calculada automaticamente: {{ \App\Domain\Pedidos\DataPrevistaCalculator::DIAS_UTEIS }} dias úteis após a data da solicitação.</dd>
                </div>
            </dl>

            <div class="flex items-center justify-end gap-3 border-t border-border pt-4">
                <a href="{{ $listingUrl }}" class="btn-secondary">Voltar</a>
                <button type="submit" wire:loading.attr="disabled" class="btn-primary">Enviar solicitação</button>
            </div>
        </form>
    @endif
</div>
