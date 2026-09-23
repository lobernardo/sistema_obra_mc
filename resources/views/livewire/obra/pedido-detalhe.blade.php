<div class="flex flex-col gap-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('obra.pedidos.index') }}" class="text-sm text-primary hover:underline">← Acompanhamento</a>
            <h1 class="page-title">Pedido {{ $pedido->code }}</h1>
        </div>
        <x-status-badge :status="$pedido->status" class="text-sm" />
    </div>

    <section aria-label="Dados do pedido" class="card">
        <x-pedido-summary :pedido="$pedido" />
    </section>

    <x-pedido-observacao-form />

    <section aria-label="Histórico" class="card">
        <h2 class="section-title mb-4">Histórico</h2>
        <x-pedido-history-timeline :events="$events" :pedido="$pedido" />
    </section>
</div>
