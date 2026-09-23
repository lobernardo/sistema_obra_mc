<div class="flex flex-col gap-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('suprimentos.pedidos.index') }}" class="text-sm text-primary hover:underline">← Todos os Pedidos</a>
            <h1 class="page-title">Pedido {{ $pedido->code }}</h1>
        </div>
        <x-status-badge :status="$pedido->status" class="text-sm" />
    </div>

    @if ($feedback)
        <div role="status" class="alert-success">
            {{ $feedback }}
        </div>
    @endif

    <section aria-label="Dados do pedido" class="card">
        <x-pedido-summary :pedido="$pedido" />
    </section>

    @unless ($isTerminal)
        <section aria-label="Operação" class="card flex flex-col gap-5">
            <div>
                <h2 class="section-title">Operação</h2>
                <p class="text-sm text-text-muted">Cada alteração é persistida e registrada no histórico.</p>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <form wire:submit="updateResponsavel" aria-label="Responsável" class="flex flex-col gap-2 rounded-lg border border-border p-4">
                    <label for="responsible_id" class="form-label">Responsável</label>
                    <select id="responsible_id" wire:model="responsible_id" class="form-control">
                        <option value="">Selecione um responsável</option>
                        @foreach ($suprimentosUsers as $suprimentosUser)
                            <option value="{{ $suprimentosUser->id }}">{{ $suprimentosUser->name }}</option>
                        @endforeach
                    </select>
                    @error('responsible_id') <span role="alert" class="form-error">{{ $message }}</span> @enderror
                    <div><button type="submit" wire:loading.attr="disabled" class="btn-secondary">Salvar responsável</button></div>
                </form>

                <form wire:submit="updatePrioridade" aria-label="Prioridade" class="flex flex-col gap-2 rounded-lg border border-border p-4">
                    <label for="priority_id" class="form-label">Prioridade</label>
                    <select id="priority_id" wire:model="priority_id" class="form-control">
                        <option value="">Selecione a prioridade</option>
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->id }}">{{ $priority->name }}</option>
                        @endforeach
                    </select>
                    @error('priority_id') <span role="alert" class="form-error">{{ $message }}</span> @enderror
                    <div><button type="submit" wire:loading.attr="disabled" class="btn-secondary">Salvar prioridade</button></div>
                </form>

                <form wire:submit="updatePrevisao" aria-label="Previsão de entrega" class="flex flex-col gap-2 rounded-lg border border-border p-4">
                    <label for="expected_delivery_at" class="form-label">Previsão de entrega</label>
                    <input id="expected_delivery_at" type="date" wire:model="expected_delivery_at" class="form-control">
                    @error('expected_delivery_at') <span role="alert" class="form-error">{{ $message }}</span> @enderror
                    <div><button type="submit" wire:loading.attr="disabled" class="btn-secondary">Salvar previsão</button></div>
                </form>

                <form wire:submit="updateStatus" aria-label="Status" class="flex flex-col gap-2 rounded-lg border border-border p-4">
                    <label for="status_id" class="form-label">Status</label>
                    <select id="status_id" wire:model="status_id" class="form-control">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->id }}">{{ $status->name }}</option>
                        @endforeach
                    </select>
                    @error('status_id') <span role="alert" class="form-error">{{ $message }}</span> @enderror
                    <div><button type="submit" wire:loading.attr="disabled" class="btn-primary">Mover status</button></div>
                </form>
            </div>

            <div aria-label="Cancelamento" class="flex flex-col gap-3 border-t border-border pt-4">
                @if (! $confirmingCancel)
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-text-muted">O cancelamento é irreversível e fica registrado no histórico.</p>
                        <button type="button" wire:click="confirmCancel" data-testid="cancel-button" class="btn-danger">Cancelar pedido</button>
                    </div>
                @else
                    <div role="alertdialog" aria-label="Confirmar cancelamento" data-testid="cancel-confirm-dialog"
                        class="flex flex-col gap-3 rounded-lg border border-error/30 bg-error-soft p-4">
                        <p class="text-sm font-medium text-error">Tem certeza que deseja cancelar este pedido? Esta ação é irreversível.</p>
                        <div class="flex flex-wrap gap-3">
                            <button type="button" wire:click="cancelarPedido" data-testid="cancel-confirm" class="btn-danger">Confirmar cancelamento</button>
                            <button type="button" wire:click="abortCancel" data-testid="cancel-abort" class="btn-secondary">Voltar</button>
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @else
        <div class="alert-info">
            Este pedido está em status terminal ({{ $pedido->status->name }}) e não aceita mais alterações operacionais.
        </div>
    @endunless

    @if ($isFinalizable)
        <section aria-label="Romaneio e finalização" class="card flex flex-col gap-5">
            <div>
                <h2 class="section-title">Romaneio e finalização</h2>
                <p class="text-sm text-text-muted">O pedido só pode ser finalizado depois que um romaneio for anexado.</p>
            </div>

            <form wire:submit="anexarRomaneio" aria-label="Anexar romaneio" class="flex flex-col gap-2 rounded-lg border border-border p-4">
                <label for="romaneio" class="form-label">Anexar romaneio</label>
                <input id="romaneio" type="file" wire:model="romaneio" accept=".pdf,.jpg,.jpeg,.png"
                    aria-describedby="romaneio-hint" class="form-control">
                <p id="romaneio-hint" class="text-xs text-text-muted">PDF, JPG ou PNG; até 10 MB.</p>
                @error('romaneio') <span role="alert" class="form-error">{{ $message }}</span> @enderror
                <div><button type="submit" wire:loading.attr="disabled" class="btn-secondary">Enviar romaneio</button></div>
            </form>

            <div aria-label="Finalização" class="flex flex-col gap-3 border-t border-border pt-4">
                @error('finalizar')
                    <div role="alert" class="alert-error" data-testid="finalizar-error">{{ $message }}</div>
                @enderror

                @if (! $confirmingFinalizacao)
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        @if ($hasRomaneio)
                            <p class="text-sm text-text-muted">Finalizar conclui o pedido operacionalmente. Esta ação é irreversível.</p>
                        @else
                            <p id="finalizar-hint" class="text-sm text-text-muted">Anexe o romaneio antes de finalizar.</p>
                        @endif
                        <button type="button" wire:click="confirmarFinalizacao" data-testid="finalizar-button" class="btn-primary"
                            @disabled(! $hasRomaneio) @unless ($hasRomaneio) aria-describedby="finalizar-hint" @endunless>Finalizar pedido</button>
                    </div>
                @else
                    <div role="alertdialog" aria-label="Confirmar finalização" data-testid="finalizar-confirm-dialog"
                        class="flex flex-col gap-3 rounded-lg border border-primary/30 bg-background p-4">
                        <p class="text-sm font-medium">Tem certeza que deseja finalizar este pedido? Depois de finalizado ele não aceita mais alterações operacionais.</p>
                        <div class="flex flex-wrap gap-3">
                            <button type="button" wire:click="finalizarPedido" data-testid="finalizar-confirm" class="btn-primary">Confirmar finalização</button>
                            <button type="button" wire:click="abortarFinalizacao" data-testid="finalizar-abort" class="btn-secondary">Voltar</button>
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @endif

    <x-pedido-observacao-form />

    <section aria-label="Histórico" class="card">
        <h2 class="section-title mb-4">Histórico</h2>
        <x-pedido-history-timeline :events="$events" :pedido="$pedido" />
    </section>
</div>
