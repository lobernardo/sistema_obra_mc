{{--
    "Somente obras ativas" (RF-20, UI-08): single source, so the label and
    the help text are identical on the Suprimentos and Gestão listings.
--}}
<div class="flex flex-col gap-1">
    <label for="activeObrasOnly" class="inline-flex min-h-11 items-center gap-2 rounded-md border border-border px-3 py-2 text-sm font-medium text-text lg:min-h-0">
        <input id="activeObrasOnly" type="checkbox" wire:model.live="activeObrasOnly" aria-describedby="activeObrasOnly-help" class="h-4 w-4 rounded border-border accent-primary focus:ring-2 focus:ring-focus/40">
        Somente obras ativas
    </label>
    <p id="activeObrasOnly-help" class="text-xs text-text-muted">Oculta pedidos de obras concluídas; pedidos "Outra" (sem obra) continuam listados.</p>
</div>
