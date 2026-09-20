@php
    $prazoLabels = [
        'dentro_do_prazo' => 'Dentro do prazo',
        'vencendo_em_breve' => 'Vencendo em breve (até '.\App\Domain\Pedidos\PrazoClassifier::VENCENDO_EM_BREVE_DIAS.' dias)',
        'atrasado' => 'Atrasados',
    ];
    $prazoColors = [
        'dentro_do_prazo' => 'bg-emerald-500',
        'vencendo_em_breve' => 'bg-amber-500',
        'atrasado' => 'bg-red-500',
    ];
    $pendentesTotal = max($indicators['pendentes'], 1);
    $volumeTotal = max($indicators['volumeTotal'], 1);
@endphp

<div class="flex flex-col gap-5">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="text-sm text-slate-500">Situação consolidada das solicitações. Os indicadores usam as mesmas regras de atraso e pendência do Kanban e das listagens.</p>
    </div>

    <form wire:submit.prevent class="card grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6" aria-label="Filtros">
        <fieldset class="flex flex-col gap-2 rounded-lg border border-slate-200 p-3 xl:col-span-2">
            <legend class="px-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">Período (solicitação)</legend>
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1">
                    <label for="requestedFrom" class="text-xs text-slate-600">De</label>
                    <input id="requestedFrom" type="date" wire:model.live="requestedFrom" class="form-control">
                </div>
                <div class="flex flex-col gap-1">
                    <label for="requestedTo" class="text-xs text-slate-600">Até</label>
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
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div data-testid="indicator-volume-total" class="card flex flex-col gap-1">
                <h2 class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Volume total</h2>
                <p data-value class="text-3xl font-semibold text-slate-900">{{ $indicators['volumeTotal'] }}</p>
                <span class="text-xs text-slate-500">pedidos no escopo filtrado</span>
            </div>

            <div data-testid="indicator-pendentes" class="card flex flex-col gap-1 border-amber-200">
                <h2 class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Pendentes</h2>
                <p data-value class="text-3xl font-semibold text-amber-700"><a href="{{ $pendentesDrillDownUrl }}" class="hover:underline">{{ $indicators['pendentes'] }}</a></p>
                <span class="text-xs text-slate-500">não entregues nem cancelados — clique para ver</span>
            </div>

            <div data-testid="indicator-atrasados" class="card flex flex-col gap-1 border-red-200">
                <h2 class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Atrasados</h2>
                <p data-value class="text-3xl font-semibold text-red-700"><a href="{{ $atrasadosDrillDownUrl }}" class="hover:underline">{{ $indicators['atrasados'] }}</a></p>
                <span class="text-xs text-slate-500">data necessária vencida e não entregues — clique para ver</span>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div data-testid="indicator-por-status" class="card flex flex-col gap-3">
                <h2 class="section-title">Distribuição por status</h2>
                <ul class="flex flex-col gap-2">
                    @foreach ($indicators['porStatus'] as $row)
                        <li data-status="{{ $row['status']->slug }}" class="flex flex-col gap-1">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-700">{{ $row['status']->name }}</span>
                                <span class="font-semibold text-slate-900">{{ $row['count'] }}</span>
                            </div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-sky-500" style="width: {{ round($row['count'] / $volumeTotal * 100) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div data-testid="indicator-prazos" class="card flex flex-col gap-3">
                <h2 class="section-title">Prazos</h2>
                <p class="text-xs text-slate-500">Somente pedidos pendentes.</p>
                <ul class="flex flex-col gap-2">
                    @foreach ($indicators['prazos'] as $row)
                        <li data-situacao="{{ $row['situacao'] }}" class="flex flex-col gap-1">
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2 text-slate-700">
                                    <span class="inline-block h-2.5 w-2.5 rounded-full {{ $prazoColors[$row['situacao']] ?? 'bg-slate-400' }}"></span>
                                    {{ $prazoLabels[$row['situacao']] ?? $row['situacao'] }}
                                </span>
                                <span class="font-semibold text-slate-900">{{ $row['count'] }}</span>
                            </div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full {{ $prazoColors[$row['situacao']] ?? 'bg-slate-400' }}" style="width: {{ round($row['count'] / $pendentesTotal * 100) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div data-testid="indicator-por-obra" class="card flex flex-col gap-3">
                <h2 class="section-title">Visão por obra</h2>
                <ul class="flex flex-col gap-2">
                    @forelse ($indicators['porObra'] as $row)
                        <li data-obra="{{ $row['obra']->id }}" class="flex flex-col gap-1">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-700">{{ $row['obra']->name }}</span>
                                <span class="font-semibold text-slate-900">{{ $row['count'] }}</span>
                            </div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-violet-500" style="width: {{ round($row['count'] / $volumeTotal * 100) }}%"></div>
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">Nenhuma obra cadastrada.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </section>
</div>
