import type { Db } from "@/lib/supabase/types";
import type { Obra, PedidoComHistorico, PedidoComRelacoes, Profile } from "@/lib/types/domain";
import { isPedidoAtrasado } from "./atraso";

const PEDIDO_SELECT = `
  *,
  obra:obras(*),
  status:statuses(*),
  priority:priorities(*),
  requester:profiles!pedidos_requester_id_fkey(*, role:roles(*)),
  responsible:profiles!pedidos_responsible_id_fkey(*, role:roles(*))
`;

const PEDIDO_WITH_HISTORICO_SELECT = `
  ${PEDIDO_SELECT},
  events:pedido_events(
    *,
    eventType:event_types(*),
    actor:profiles(*, role:roles(*))
  )
`;

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export interface PedidoFilters {
  obraId?: string;
  responsibleId?: string;
  priorityId?: string;
  statusId?: string;
  /** Range over `requested_at` (inclusive), ISO date/datetime strings. */
  requestedFrom?: string;
  requestedTo?: string;
  /** Range over `needed_at` (inclusive), ISO date strings. */
  neededAtFrom?: string;
  neededAtTo?: string;
  /** Free-text match over the pedido code and items description. */
  search?: string;
  /** Filters using the shared atraso rule — see `isPedidoAtrasado`. */
  atrasado?: boolean;
}

/**
 * Lists pedidos with `obra`, `status`, `priority`, `requester` and
 * `responsible` resolved via join, optionally filtered.
 */
export async function listPedidos(
  db: Db,
  filters: PedidoFilters = {},
): Promise<PedidoComRelacoes[]> {
  let query = db.from("pedidos").select(PEDIDO_SELECT);

  if (filters.obraId) query = query.eq("obra_id", filters.obraId);
  if (filters.responsibleId) query = query.eq("responsible_id", filters.responsibleId);
  if (filters.priorityId) query = query.eq("priority_id", filters.priorityId);
  if (filters.statusId) query = query.eq("status_id", filters.statusId);
  if (filters.requestedFrom) query = query.gte("requested_at", filters.requestedFrom);
  if (filters.requestedTo) query = query.lte("requested_at", filters.requestedTo);
  if (filters.neededAtFrom) query = query.gte("needed_at", filters.neededAtFrom);
  if (filters.neededAtTo) query = query.lte("needed_at", filters.neededAtTo);
  if (filters.search) {
    const term = filters.search.replace(/[%,]/g, "");
    query = query.or(`code.ilike.%${term}%,items_description.ilike.%${term}%`);
  }

  const { data, error } = await query.overrideTypes<PedidoComRelacoes[], { merge: false }>();

  if (error) {
    throw new Error(`Failed to list pedidos: ${error.message}`);
  }

  const pedidos = data ?? [];

  if (filters.atrasado === undefined) {
    return pedidos;
  }

  return pedidos.filter((pedido) => isPedidoAtrasado(pedido) === filters.atrasado);
}

/**
 * Lists the obras a profile can access: only the obras linked via
 * `obra_profile` when the profile's role is `obra`; every obra for
 * `suprimentos`/`gestao`, who operate/view across all obras.
 */
export async function listObrasAcessiveis(db: Db, profile: Profile): Promise<Obra[]> {
  if (profile.role.slug === "obra") {
    const { data, error } = await db
      .from("obra_profile")
      .select("obra:obras(*)")
      .eq("profile_id", profile.id)
      .overrideTypes<{ obra: Obra }[], { merge: false }>();

    if (error) {
      throw new Error(`Failed to list obras for profile: ${error.message}`);
    }

    return (data ?? []).map((row) => row.obra).sort((a, b) => a.name.localeCompare(b.name));
  }

  const { data, error } = await db
    .from("obras")
    .select("*")
    .order("name")
    .overrideTypes<Obra[], { merge: false }>();

  if (error) {
    throw new Error(`Failed to list obras: ${error.message}`);
  }

  return data ?? [];
}

/**
 * Fetches a single pedido by `id` or `code`, with relations resolved and its
 * history events ordered chronologically. Returns `null` when not found.
 */
export async function getPedidoByIdOrCode(
  db: Db,
  idOrCode: string,
): Promise<PedidoComHistorico | null> {
  const column = UUID_PATTERN.test(idOrCode) ? "id" : "code";

  const { data, error } = await db
    .from("pedidos")
    .select(PEDIDO_WITH_HISTORICO_SELECT)
    .eq(column, idOrCode)
    .order("created_at", { referencedTable: "pedido_events", ascending: true })
    .maybeSingle()
    .overrideTypes<PedidoComHistorico, { merge: false }>();

  if (error) {
    throw new Error(`Failed to fetch pedido: ${error.message}`);
  }

  return data;
}
