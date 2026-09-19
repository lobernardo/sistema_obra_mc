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

    <h2>Histórico</h2>
    <x-pedido-history-timeline :events="$events" />
</div>
