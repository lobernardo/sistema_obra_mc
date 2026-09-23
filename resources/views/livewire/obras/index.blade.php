<div class="flex flex-col gap-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Obras</h1>
            <p class="text-sm text-text-muted">Cadastro das obras, com responsável e status.</p>
        </div>
        <a href="{{ route('obras.create') }}" class="btn-primary">Nova obra</a>
    </div>

    @if (session('status'))
        <div role="status" class="alert-success">
            {{ session('status') }}
        </div>
    @endif

    <div wire:loading.class="opacity-60" class="overflow-x-auto rounded-lg border border-border bg-surface shadow-sm transition-opacity">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Responsável</th>
                    <th>Status</th>
                    {{-- `relative` keeps the absolutely-positioned sr-only label inside the scrollable wrapper (no document overflow on mobile, UI-21). --}}
                    <th class="relative"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($obras as $obra)
                    <tr wire:key="obra-{{ $obra->id }}" data-obra-id="{{ $obra->id }}">
                        <td class="font-medium">{{ $obra->name }}</td>
                        <td>{{ $obra->responsavel ?? '—' }}</td>
                        <td data-obra-status>{{ $obra->status->label() }}</td>
                        <td>
                            <div class="flex justify-end">
                                <a href="{{ route('obras.edit', $obra) }}" class="btn-secondary px-3 py-1.5 whitespace-nowrap">Editar</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="p-3"><div class="empty-state">Nenhuma obra cadastrada.</div></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $obras->links() }}
</div>
