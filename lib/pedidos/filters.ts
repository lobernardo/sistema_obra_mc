import type { PedidoFilters } from "./queries";

/** Shape of Next.js's Page `searchParams` prop — values may repeat as an array. */
export type PedidoFilterSearchParams = Record<string, string | string[] | undefined>;

function first(value: string | string[] | undefined): string | undefined {
  const resolved = Array.isArray(value) ? value[0] : value;
  return resolved ? resolved : undefined;
}

/**
 * Parses the "Todos os Pedidos" URL query string into `PedidoFilters`
 * (PRD §17, §25) — the single source of truth for how filter state maps to
 * query params, shared by `PedidosFilterBar` (writes it) and the listing
 * page (reads it), so combining filters just means combining query params.
 */
export function parsePedidoFilters(params: PedidoFilterSearchParams): PedidoFilters {
  const atrasado = first(params.atrasado);

  return {
    obraId: first(params.obraId),
    responsibleId: first(params.responsibleId),
    priorityId: first(params.priorityId),
    statusId: first(params.statusId),
    requestedFrom: first(params.requestedFrom),
    requestedTo: first(params.requestedTo),
    neededAtFrom: first(params.neededAtFrom),
    neededAtTo: first(params.neededAtTo),
    search: first(params.search),
    atrasado: atrasado === "true" ? true : atrasado === "false" ? false : undefined,
  };
}
