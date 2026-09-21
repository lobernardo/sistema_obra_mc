<div class="flex flex-col gap-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="page-title">Acompanhamento</h1>
            <p class="text-sm text-text-muted">Solicitações das obras às quais você está associado.</p>
        </div>
        <a href="{{ route('obra.nova-solicitacao') }}" class="btn-primary">+ Nova Solicitação</a>
    </div>

    <x-pedido-table :pedidos="$pedidos" show-route="obra.pedidos.show" empty-message="Nenhum pedido encontrado para as suas obras." />

    {{ $pedidos->links() }}
</div>
