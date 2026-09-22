<div class="flex flex-col gap-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="page-title">Visão Geral</h1>
            <p class="text-sm text-text-muted">Situação consolidada de todas as obras. Os números usam as mesmas regras de atraso e entrega do Kanban e das listagens.</p>
        </div>
        <a href="{{ route('suprimentos.kanban') }}" data-testid="atalho-kanban" class="btn-primary">Abrir Kanban</a>
    </div>

    {{-- RF-29: os três KPIs vêm prontos do DashboardIndicatorsService. --}}
    <section aria-label="Indicadores" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div data-testid="indicator-volume-total" class="card flex flex-col gap-1">
            <h2 class="text-xs font-semibold tracking-wide text-text-muted uppercase">Total de pedidos</h2>
            <p data-value class="text-3xl font-semibold text-text">{{ $indicators['volumeTotal'] }}</p>
            <span class="text-xs text-text-muted">pedidos registrados no sistema</span>
        </div>

        <div data-testid="indicator-atrasados" class="card flex flex-col gap-1 border-t-4 border-t-atraso">
            <h2 class="text-xs font-semibold tracking-wide text-text-muted uppercase">Atrasados</h2>
            <p data-value class="text-3xl font-semibold text-atraso">{{ $indicators['atrasados'] }}</p>
            <span class="text-xs text-text-muted">data necessária vencida e não entregues</span>
        </div>

        <div data-testid="indicator-entregues-hoje" class="card flex flex-col gap-1 border-t-4 border-t-concluido">
            <h2 class="text-xs font-semibold tracking-wide text-text-muted uppercase">Entregues hoje</h2>
            <p data-value class="text-3xl font-semibold text-concluido">{{ $indicators['entreguesHoje'] }}</p>
            <span class="text-xs text-text-muted">entrega registrada no dia de hoje</span>
        </div>
    </section>

    {{-- RF-28: a contagem de cada um dos 5 status do workflow, sem cancelados. --}}
    <section data-testid="visao-geral-por-status" class="card flex flex-col gap-3">
        <h2 class="section-title">Pedidos por status</h2>
        <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ($statusCounts as $row)
                <li data-status-summary="{{ $row['status']->slug }}" class="flex flex-col gap-2 rounded-lg border border-border p-3">
                    <x-status-badge :status="$row['status']" />
                    <span data-value class="text-2xl font-semibold text-text">{{ $row['count'] }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="section-title">Pedidos mais recentes</h2>
            <a href="{{ route('suprimentos.pedidos.index') }}" data-testid="ver-todos" class="text-sm font-semibold text-primary hover:underline">Ver todos</a>
        </div>

        <x-pedido-table :pedidos="$pedidosRecentes" show-route="suprimentos.pedidos.show" />
    </section>
</div>
