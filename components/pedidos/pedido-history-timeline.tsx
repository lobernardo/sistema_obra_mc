import { formatDateTime } from "@/lib/format";
import type { PedidoEventComRelacoes } from "@/lib/types/domain";
import { EmptyState } from "@/components/shared/empty-state";

/**
 * Chronological history for a pedido (US-4.2, US-6.2). Reused verbatim by
 * the Obra, Suprimentos and Gestão detail screens via `PedidoDetailLayout` —
 * behavior never varies by profile, only the surrounding layout does.
 */
export function PedidoHistoryTimeline({ events }: { events: PedidoEventComRelacoes[] }) {
  if (events.length === 0) {
    return <EmptyState title="Nenhum evento registrado" />;
  }

  const ordered = [...events].sort(
    (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime(),
  );

  return (
    <ol className="flex flex-col gap-4">
      {ordered.map((event) => (
        <li key={event.id} className="border-border flex flex-col gap-0.5 border-l-2 pl-4">
          <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
            <span className="text-sm font-medium">{event.eventType.name}</span>
            <time dateTime={event.created_at} className="text-muted-foreground text-xs">
              {formatDateTime(event.created_at)}
            </time>
          </div>
          <p className="text-muted-foreground text-sm">
            {event.previous_value ?? "—"} → {event.new_value ?? "—"}
          </p>
          <p className="text-muted-foreground text-xs">{event.actor.full_name}</p>
        </li>
      ))}
    </ol>
  );
}
