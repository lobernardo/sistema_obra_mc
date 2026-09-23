<div class="flex flex-col gap-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('obra.pedidos.index') }}" class="text-sm text-primary hover:underline">← Acompanhamento</a>
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

    @if ($canMarkEntregue)
        <section aria-label="Entrega" class="card flex flex-col gap-3">
            @if (! $confirmingEntrega)
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-text-muted">Recebeu o material? Marque o pedido como entregue. A entrega fica registrada no histórico.</p>
                    <button type="button" wire:click="confirmarEntrega" data-testid="entrega-button" class="btn-primary">Marcar como entregue</button>
                </div>
            @else
                <div role="alertdialog" aria-label="Confirmar entrega" data-testid="entrega-confirm-dialog"
                    class="flex flex-col gap-3 rounded-lg border border-primary/30 bg-background p-4">
                    <p class="text-sm font-medium">Confirma que o material deste pedido foi entregue? Depois disso o pedido não poderá voltar a um status ativo.</p>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" wire:click="marcarComoEntregue" data-testid="entrega-confirm" class="btn-primary">Confirmar entrega</button>
                        <button type="button" wire:click="abortarEntrega" data-testid="entrega-abort" class="btn-secondary">Voltar</button>
                    </div>
                </div>
            @endif
        </section>
    @endif

    <x-pedido-observacao-form />

    <section aria-label="Histórico" class="card">
        <h2 class="section-title mb-4">Histórico</h2>
        <x-pedido-history-timeline :events="$events" :pedido="$pedido" />
    </section>
</div>
