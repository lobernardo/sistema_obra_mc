@php
    $prazoLabels = [
        'dentro_do_prazo' => 'Dentro do prazo',
        'vencendo_em_breve' => 'Vencendo em breve (até '.\App\Domain\Pedidos\PrazoClassifier::VENCENDO_EM_BREVE_DIAS.' dias)',
        'atrasado' => 'Atrasados',
    ];
    $prazoColors = [
        'dentro_do_prazo' => 'bg-success',
        'vencendo_em_breve' => 'bg-warning',
        'atrasado' => 'bg-atraso',
    ];
    /**
     * RF-25: the donut is painted through this map, whose values are always
     * complete, literal utility class names mirroring `$prazoColors` above.
     * Interpolating the class name is prohibited: the project has
     * no safelist, so Tailwind would never emit the utility and the chart
     * would render black in the production build only.
     */
    $prazoFills = [
        'dentro_do_prazo' => 'fill-success',
        'vencendo_em_breve' => 'fill-warning',
        'atrasado' => 'fill-atraso',
    ];
    $pendentesTotal = max($indicators['pendentes'], 1);
    $volumeTotal = max($indicators['volumeTotal'], 1);

    /**
     * RF-25: annulus wedges computed in PHP from the counts the service already
     * returned — no new query, no JavaScript and no charting dependency. The
     * `viewBox` keeps the drawing fluid down to 390 px without a media query.
     */
    $donutCenter = 50.0;
    $donutOuterRadius = 45.0;
    $donutInnerRadius = 27.0;
    $donutPoint = function (float $angle, float $radius) use ($donutCenter): string {
        $radians = deg2rad($angle);

        return round($donutCenter + $radius * sin($radians), 3).' '.round($donutCenter - $radius * cos($radians), 3);
    };
    $donutSlices = [];
    $donutCursor = 0.0;
    foreach ($indicators['prazos'] as $row) {
        /** A full 360° wedge would collapse (identical endpoints), hence the cap. */
        $sweep = min(min($row['count'] / $pendentesTotal, 1) * 360, 359.99);
        $start = $donutCursor;
        $end = $start + $sweep;
        $largeArc = $sweep > 180 ? 1 : 0;

        $donutSlices[] = [
            'situacao' => $row['situacao'],
            'count' => $row['count'],
            'label' => $prazoLabels[$row['situacao']] ?? $row['situacao'],
            'fill' => $prazoFills[$row['situacao']] ?? 'fill-text-muted',
            'd' => 'M '.$donutPoint($start, $donutOuterRadius)
                .' A '.$donutOuterRadius.' '.$donutOuterRadius.' 0 '.$largeArc.' 1 '.$donutPoint($end, $donutOuterRadius)
                .' L '.$donutPoint($end, $donutInnerRadius)
                .' A '.$donutInnerRadius.' '.$donutInnerRadius.' 0 '.$largeArc.' 0 '.$donutPoint($start, $donutInnerRadius)
                .' Z',
        ];

        $donutCursor = $end;
    }

    /** RF-26: PT-BR descriptions carrying the same numbers the lists render. */
    $porStatusAriaLabel = 'Distribuição por status dos '.$indicators['volumeTotal'].' pedidos no escopo filtrado: '
        .collect($indicators['porStatus'])->map(fn (array $row) => $row['status']->name.' '.$row['count'])->implode('; ').'.';
    $prazosAriaLabel = 'Prazos dos '.$indicators['pendentes'].' pedidos pendentes: '
        .collect($indicators['prazos'])->map(fn (array $row) => ($prazoLabels[$row['situacao']] ?? $row['situacao']).' '.$row['count'])->implode('; ').'.';
    $porObraAriaLabel = 'Visão por obra dos '.$indicators['volumeTotal'].' pedidos no escopo filtrado: '
        .(count($indicators['porObra']) === 0
            ? 'nenhuma obra cadastrada'
            : collect($indicators['porObra'])->map(fn (array $row) => $row['obra']->name.' '.$row['count'])->implode('; ')).'.';
@endphp

<div class="flex flex-col gap-5">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="text-sm text-text-muted">Situação consolidada das solicitações. Os indicadores usam as mesmas regras de atraso e pendência do Kanban e das listagens.</p>
    </div>

    <form wire:submit.prevent class="card grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6" aria-label="Filtros">
        <fieldset class="flex flex-col gap-2 rounded-lg border border-border p-3 xl:col-span-2">
            <legend class="px-1 text-xs font-semibold tracking-wide text-text-muted uppercase">Período (solicitação)</legend>
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1">
                    <label for="requestedFrom" class="text-xs text-text-muted">De</label>
                    <input id="requestedFrom" type="date" wire:model.live="requestedFrom" class="form-control">
                </div>
                <div class="flex flex-col gap-1">
                    <label for="requestedTo" class="text-xs text-text-muted">Até</label>
                    <input id="requestedTo" type="date" wire:model.live="requestedTo" class="form-control">
                </div>
            </div>
        </fieldset>

        <div class="flex flex-col gap-1">
            <label for="obraId" class="form-label">Obra</label>
            <select id="obraId" wire:model.live="obraId" class="form-control">
                <option value="">Todas as obras</option>
                @foreach ($obras as $obra)
                    <option value="{{ $obra->id }}">{{ $obra->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label for="statusId" class="form-label">Status</label>
            <select id="statusId" wire:model.live="statusId" class="form-control">
                <option value="">Todos os status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->id }}">{{ $status->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label for="priorityId" class="form-label">Prioridade</label>
            <select id="priorityId" wire:model.live="priorityId" class="form-control">
                <option value="">Todas as prioridades</option>
                @foreach ($priorities as $priority)
                    <option value="{{ $priority->id }}">{{ $priority->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label for="responsibleId" class="form-label">Responsável</label>
            <select id="responsibleId" wire:model.live="responsibleId" class="form-control">
                <option value="">Todos os responsáveis</option>
                @foreach ($suprimentosUsers as $suprimentosUser)
                    <option value="{{ $suprimentosUser->id }}">{{ $suprimentosUser->name }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <section aria-label="Indicadores" class="flex flex-col gap-4" wire:loading.class="opacity-60">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div data-testid="indicator-volume-total" class="card flex flex-col gap-1">
                <h2 class="text-xs font-semibold tracking-wide text-text-muted uppercase">Volume total</h2>
                <p data-value class="text-3xl font-semibold text-text">{{ $indicators['volumeTotal'] }}</p>
                <span class="text-xs text-text-muted">pedidos no escopo filtrado</span>
            </div>

            <div data-testid="indicator-pendentes" class="card flex flex-col gap-1 border-t-4 border-t-warning">
                <h2 class="text-xs font-semibold tracking-wide text-text-muted uppercase">Pendentes</h2>
                <p data-value class="text-3xl font-semibold text-warning"><a href="{{ $pendentesDrillDownUrl }}" class="hover:underline">{{ $indicators['pendentes'] }}</a></p>
                <span class="text-xs text-text-muted">não entregues nem cancelados — clique para ver</span>
            </div>

            <div data-testid="indicator-atrasados" class="card flex flex-col gap-1 border-t-4 border-t-atraso">
                <h2 class="text-xs font-semibold tracking-wide text-text-muted uppercase">Atrasados</h2>
                <p data-value class="text-3xl font-semibold text-atraso"><a href="{{ $atrasadosDrillDownUrl }}" class="hover:underline">{{ $indicators['atrasados'] }}</a></p>
                <span class="text-xs text-text-muted">data necessária vencida e não entregues — clique para ver</span>
            </div>

            <div data-testid="indicator-entregues" class="card flex flex-col gap-1 border-t-4 border-t-concluido">
                <h2 class="text-xs font-semibold tracking-wide text-text-muted uppercase">Entregues</h2>
                <p data-value class="text-3xl font-semibold text-concluido"><a href="{{ $entreguesDrillDownUrl }}" class="hover:underline">{{ $indicators['entregues'] }}</a></p>
                <span class="text-xs text-text-muted">entrega concluída no escopo filtrado — clique para ver</span>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div data-testid="indicator-por-status" role="img" aria-label="{{ $porStatusAriaLabel }}" class="card flex flex-col gap-3">
                <h2 class="section-title">Distribuição por status</h2>
                <ul class="flex flex-col gap-2">
                    @foreach ($indicators['porStatus'] as $row)
                        <li data-status="{{ $row['status']->slug }}" class="flex flex-col gap-1">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-text">{{ $row['status']->name }}</span>
                                <span class="font-semibold text-text">{{ $row['count'] }}</span>
                            </div>
                            <div class="h-1.5 w-full overflow-hidden rounded bg-background">
                                <div class="h-full rounded bg-primary" style="width: {{ round($row['count'] / $volumeTotal * 100) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div data-testid="indicator-prazos" role="img" aria-label="{{ $prazosAriaLabel }}" class="card flex flex-col gap-3">
                <h2 class="section-title">Prazos</h2>
                <p class="text-xs text-text-muted">Somente pedidos pendentes.</p>

                {{--
                    RF-25: donut em SVG inline. As fatias são preenchidas pelos
                    utilitários literais de `$prazoFills`; a lista numérica
                    abaixo permanece como alternativa textual.
                --}}
                <svg
                    data-testid="donut-prazos"
                    viewBox="0 0 100 100"
                    preserveAspectRatio="xMidYMid meet"
                    class="mx-auto h-32 w-32 max-w-full"
                    aria-hidden="true"
                    focusable="false"
                >
                    <circle cx="50" cy="50" r="36" fill="none" stroke-width="18" class="stroke-background"></circle>
                    @foreach ($donutSlices as $slice)
                        <path
                            data-fatia="{{ $slice['situacao'] }}"
                            data-count="{{ $slice['count'] }}"
                            d="{{ $slice['d'] }}"
                            class="{{ $slice['fill'] }}"
                        ></path>
                    @endforeach
                </svg>

                <ul class="flex flex-col gap-2">
                    @foreach ($indicators['prazos'] as $row)
                        <li data-situacao="{{ $row['situacao'] }}" class="flex flex-col gap-1">
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2 text-text">
                                    <span class="inline-block h-2.5 w-2.5 rounded-full {{ $prazoColors[$row['situacao']] ?? 'bg-text-muted' }}"></span>
                                    {{ $prazoLabels[$row['situacao']] ?? $row['situacao'] }}
                                </span>
                                <span class="font-semibold text-text">{{ $row['count'] }}</span>
                            </div>
                            <div class="h-1.5 w-full overflow-hidden rounded bg-background">
                                <div class="h-full rounded {{ $prazoColors[$row['situacao']] ?? 'bg-text-muted' }}" style="width: {{ round($row['count'] / $pendentesTotal * 100) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div data-testid="indicator-por-obra" role="img" aria-label="{{ $porObraAriaLabel }}" class="card flex flex-col gap-3">
                <h2 class="section-title">Visão por obra</h2>
                <ul class="flex flex-col gap-2">
                    @forelse ($indicators['porObra'] as $row)
                        <li data-obra="{{ $row['obra']->id }}" class="flex flex-col gap-1">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-text">{{ $row['obra']->name }}</span>
                                <span class="font-semibold text-text">{{ $row['count'] }}</span>
                            </div>
                            <div class="h-1.5 w-full overflow-hidden rounded bg-background">
                                <div class="h-full rounded bg-primary" style="width: {{ round($row['count'] / $volumeTotal * 100) }}%"></div>
                            </div>
                        </li>
                    @empty
                        <li class="empty-state py-6">Nenhuma obra cadastrada.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </section>
</div>
