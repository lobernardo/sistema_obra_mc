import type { PedidoFilters } from "./queries";

/** Shape of Next.js's Page `searchParams` prop — values may repeat as an array. */
export type PedidoFilterSearchParams = Record<string, string | string[] | undefined>;

function first(value: string | string[] | undefined): string | undefined {
  const resolved = Array.isArray(value) ? value[0] : value;
  return resolved ? resolved : undefined;
}

function parseBoolean(value: string | undefined): boolean | undefined {
  return value === "true" ? true : value === "false" ? false : undefined;
}

/**
 * Parses the "Todos os Pedidos" URL query string into `PedidoFilters`
 * (PRD §17, §25) — the single source of truth for how filter state maps to
 * query params, shared by `PedidosFilterBar`/`DashboardFilterBar` (write it)
 * and the listing/dashboard pages (read it), so combining filters just means
 * combining query params.
 */
export function parsePedidoFilters(params: PedidoFilterSearchParams): PedidoFilters {
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
    atrasado: parseBoolean(first(params.atrasado)),
    pendente: parseBoolean(first(params.pendente)),
  };
}

/** Every key `parsePedidoFilters` reads, in the exact order it's checked. */
const FILTER_KEYS: readonly (keyof PedidoFilters)[] = [
  "obraId",
  "responsibleId",
  "priorityId",
  "statusId",
  "requestedFrom",
  "requestedTo",
  "neededAtFrom",
  "neededAtTo",
  "search",
  "atrasado",
  "pendente",
];

/**
 * Serializes filters back into a query string using the same keys
 * `parsePedidoFilters` reads. Lets the dashboard's drill-down links (US-7.3)
 * carry the exact scope/filters applied to an indicator over to "Todos os
 * Pedidos", plus one extra criterion (e.g. `atrasado=true`) — so the
 * resulting listing is guaranteed to call `listPedidos` with the very same
 * filters used to compute that indicator.
 */
export function serializePedidoFilters(filters: PedidoFilters): string {
  const params = new URLSearchParams();

  for (const key of FILTER_KEYS) {
    const value = filters[key];
    if (value === undefined || value === "") continue;
    params.set(key, String(value));
  }

  return params.toString();
}
