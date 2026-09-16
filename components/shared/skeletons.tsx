import { Skeleton } from "@/components/ui/skeleton";

/** Placeholder rows while a table/listagem of pedidos is loading. */
export function ListSkeleton({ rows = 5 }: { rows?: number }) {
  return (
    <div className="flex flex-col gap-2" role="status" aria-label="Carregando lista">
      {Array.from({ length: rows }, (_, i) => (
        <Skeleton key={i} className="h-10 w-full" />
      ))}
    </div>
  );
}

/** Placeholder for a single Kanban card while pedidos are loading. */
export function KanbanCardSkeleton() {
  return (
    <div
      className="border-border flex flex-col gap-2 rounded-lg border p-3"
      role="status"
      aria-label="Carregando pedido"
    >
      <Skeleton className="h-4 w-1/2" />
      <Skeleton className="h-3 w-full" />
      <Skeleton className="h-3 w-2/3" />
    </div>
  );
}

/** Placeholder for a single dashboard indicator card while data is loading. */
export function DashboardIndicatorSkeleton() {
  return (
    <div
      className="border-border flex flex-col gap-2 rounded-lg border p-4"
      role="status"
      aria-label="Carregando indicador"
    >
      <Skeleton className="h-3 w-1/3" />
      <Skeleton className="h-7 w-1/4" />
    </div>
  );
}
