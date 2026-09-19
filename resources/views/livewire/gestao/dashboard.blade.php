<div>
    <h1>Dashboard</h1>

    <form wire:submit.prevent>
        <fieldset>
            <legend>Período</legend>
            <label for="requestedFrom">De</label>
            <input id="requestedFrom" type="date" wire:model.live="requestedFrom">
            <label for="requestedTo">Até</label>
            <input id="requestedTo" type="date" wire:model.live="requestedTo">
        </fieldset>

        <div>
            <label for="obraId">Obra</label>
            <select id="obraId" wire:model.live="obraId">
                <option value="">Todas as obras</option>
                @foreach ($obras as $obra)
                    <option value="{{ $obra->id }}">{{ $obra->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="statusId">Status</label>
            <select id="statusId" wire:model.live="statusId">
                <option value="">Todos os status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->id }}">{{ $status->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="priorityId">Prioridade</label>
            <select id="priorityId" wire:model.live="priorityId">
                <option value="">Todas as prioridades</option>
                @foreach ($priorities as $priority)
                    <option value="{{ $priority->id }}">{{ $priority->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="responsibleId">Responsável</label>
            <select id="responsibleId" wire:model.live="responsibleId">
                <option value="">Todos os responsáveis</option>
                @foreach ($suprimentosUsers as $suprimentosUser)
                    <option value="{{ $suprimentosUser->id }}">{{ $suprimentosUser->name }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <section aria-label="Indicadores">
        <div data-testid="indicator-volume-total">
            <h2>Volume total</h2>
            <p data-value>{{ $indicators['volumeTotal'] }}</p>
        </div>

        <div data-testid="indicator-pendentes">
            <h2>Pendentes</h2>
            <p data-value>
                <a href="{{ $pendentesDrillDownUrl }}">{{ $indicators['pendentes'] }}</a>
            </p>
        </div>

        <div data-testid="indicator-atrasados">
            <h2>Atrasados</h2>
            <p data-value>
                <a href="{{ $atrasadosDrillDownUrl }}">{{ $indicators['atrasados'] }}</a>
            </p>
        </div>

        <div data-testid="indicator-por-status">
            <h2>Distribuição por status</h2>
            <ul>
                @foreach ($indicators['porStatus'] as $row)
                    <li data-status="{{ $row['status']->slug }}">{{ $row['status']->name }}: {{ $row['count'] }}</li>
                @endforeach
            </ul>
        </div>

        <div data-testid="indicator-prazos">
            <h2>Prazos</h2>
            <ul>
                @foreach ($indicators['prazos'] as $row)
                    <li data-situacao="{{ $row['situacao'] }}">{{ $row['situacao'] }}: {{ $row['count'] }}</li>
                @endforeach
            </ul>
        </div>

        <div data-testid="indicator-por-obra">
            <h2>Visão por obra</h2>
            <ul>
                @foreach ($indicators['porObra'] as $row)
                    <li data-obra="{{ $row['obra']->id }}">{{ $row['obra']->name }}: {{ $row['count'] }}</li>
                @endforeach
            </ul>
        </div>
    </section>
</div>
