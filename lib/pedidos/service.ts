import type { Db } from "@/lib/supabase/types";
import type {
  EventType,
  EventTypeSlug,
  Pedido,
  Priority,
  Profile,
  RoleSlug,
  Status,
  StatusSlug,
} from "@/lib/types/domain";
import { ConflictError, ForbiddenError, NotFoundError, ValidationError } from "./errors";

const ACTIVE_NON_FINAL_STATUSES: readonly StatusSlug[] = [
  "solicitado",
  "em_analise",
  "em_compra_preparacao",
  "aguardando_entrega",
];

export interface CreatePedidoInput {
  obra_id: string;
  needed_at: string;
  items_description: string;
}

/**
 * Creates a pedido for the given (Obra) requester and, in the same database
 * transaction, its `criacao_pedido` history event — see `create_pedido` in
 * the migrations. If the event insert fails, the whole call rolls back, so a
 * pedido can never exist without its creation event.
 */
export async function createPedido(
  db: Db,
  input: CreatePedidoInput,
  requester: Profile,
): Promise<Pedido> {
  if (!input.obra_id || !input.needed_at || !input.items_description) {
    throw new ValidationError("obra_id, needed_at e items_description são obrigatórios.");
  }

  const acessivel = await isObraAcessivel(db, input.obra_id, requester.id);
  if (!acessivel) {
    throw new ForbiddenError("A obra informada não está associada ao solicitante.");
  }

  const { data, error } = await db.rpc("create_pedido", {
    p_obra_id: input.obra_id,
    p_requester_id: requester.id,
    p_needed_at: input.needed_at,
    p_items_description: input.items_description,
  });

  if (error || !data) {
    throw new Error(`Failed to create pedido: ${error?.message}`);
  }

  return data;
}

/** Only `suprimentos` may act; reassigning the same responsible is a no-op. */
export async function updatePedidoResponsavel(
  db: Db,
  pedidoId: string,
  responsibleId: string | null,
  actor: Profile,
): Promise<Pedido> {
  requireRole(actor, "suprimentos");

  const pedido = await getPedidoRow(db, pedidoId);
  if (pedido.responsible_id === responsibleId) {
    return pedido;
  }

  const updated = await updatePedidoRow(db, pedidoId, { responsible_id: responsibleId });

  await insertEvent(db, {
    pedidoId,
    eventTypeSlug: "alteracao_responsavel",
    previousValue: pedido.responsible_id,
    newValue: responsibleId,
    actorId: actor.id,
  });

  return updated;
}

/** Only `suprimentos` may act; `priorityId` must be a valid priorities row. */
export async function updatePedidoPrioridade(
  db: Db,
  pedidoId: string,
  priorityId: string,
  actor: Profile,
): Promise<Pedido> {
  requireRole(actor, "suprimentos");

  const priority = await getPriorityById(db, priorityId);
  if (!priority) {
    throw new ValidationError("Prioridade inválida.");
  }

  const pedido = await getPedidoRow(db, pedidoId);
  if (pedido.priority_id === priorityId) {
    return pedido;
  }

  const previousPriority = pedido.priority_id
    ? await getPriorityById(db, pedido.priority_id)
    : null;

  const updated = await updatePedidoRow(db, pedidoId, { priority_id: priorityId });

  await insertEvent(db, {
    pedidoId,
    eventTypeSlug: "alteracao_prioridade",
    previousValue: previousPriority?.slug ?? null,
    newValue: priority.slug,
    actorId: actor.id,
  });

  return updated;
}

/** Only `suprimentos` may act; setting the same date is a no-op. */
export async function updatePedidoPrevisao(
  db: Db,
  pedidoId: string,
  expectedDeliveryAt: string | null,
  actor: Profile,
): Promise<Pedido> {
  requireRole(actor, "suprimentos");

  const pedido = await getPedidoRow(db, pedidoId);
  if (pedido.expected_delivery_at === expectedDeliveryAt) {
    return pedido;
  }

  const updated = await updatePedidoRow(db, pedidoId, {
    expected_delivery_at: expectedDeliveryAt,
  });

  await insertEvent(db, {
    pedidoId,
    eventTypeSlug: "alteracao_previsao",
    previousValue: pedido.expected_delivery_at,
    newValue: expectedDeliveryAt,
    actorId: actor.id,
  });

  return updated;
}

/**
 * Moves a pedido between the active workflow statuses, or to `entregue`.
 * Only `suprimentos` may act. `cancelado` is rejected here — use
 * `cancelPedido`. Terminal pedidos (`entregue`/`cancelado`) can't be moved.
 */
export async function updatePedidoStatus(
  db: Db,
  pedidoId: string,
  newStatusId: string,
  actor: Profile,
): Promise<Pedido> {
  requireRole(actor, "suprimentos");

  const newStatus = await getStatusById(db, newStatusId);
  if (!newStatus) {
    throw new ValidationError("status_id inválido.");
  }
  if (newStatus.slug === "cancelado") {
    throw new ValidationError("Use cancelPedido para cancelar um pedido.");
  }
  if (
    !ACTIVE_NON_FINAL_STATUSES.includes(newStatus.slug as StatusSlug) &&
    newStatus.slug !== "entregue"
  ) {
    throw new ValidationError("Transição de status inválida.");
  }

  const pedido = await getPedidoRow(db, pedidoId);
  const currentStatus = await getStatusById(db, pedido.status_id);
  if (!currentStatus) {
    throw new Error(`Status atual do pedido ${pedidoId} não encontrado.`);
  }
  if (currentStatus.slug === "entregue" || currentStatus.slug === "cancelado") {
    throw new ConflictError("Pedido em status terminal não pode ser alterado.");
  }

  const updated = await updatePedidoRow(db, pedidoId, { status_id: newStatusId });

  await insertEvent(db, {
    pedidoId,
    eventTypeSlug: newStatus.slug === "entregue" ? "entrega" : "mudanca_status",
    previousValue: currentStatus.slug,
    newValue: newStatus.slug,
    actorId: actor.id,
  });

  return updated;
}

/**
 * Cancels a pedido from any active non-final status. Only `suprimentos` may
 * act. Irreversible — no function moves a `cancelado` pedido back.
 */
export async function cancelPedido(db: Db, pedidoId: string, actor: Profile): Promise<Pedido> {
  requireRole(actor, "suprimentos");

  const pedido = await getPedidoRow(db, pedidoId);
  const currentStatus = await getStatusById(db, pedido.status_id);
  if (!currentStatus) {
    throw new Error(`Status atual do pedido ${pedidoId} não encontrado.`);
  }
  if (currentStatus.slug === "entregue" || currentStatus.slug === "cancelado") {
    throw new ConflictError("Pedido em status terminal não pode ser cancelado.");
  }

  const canceladoStatus = await getStatusBySlug(db, "cancelado");
  const updated = await updatePedidoRow(db, pedidoId, { status_id: canceladoStatus.id });

  await insertEvent(db, {
    pedidoId,
    eventTypeSlug: "cancelamento",
    previousValue: currentStatus.slug,
    newValue: canceladoStatus.slug,
    actorId: actor.id,
  });

  return updated;
}

function requireRole(actor: Profile, roleSlug: RoleSlug): void {
  if (actor.role.slug !== roleSlug) {
    throw new ForbiddenError(`Apenas o perfil "${roleSlug}" pode executar esta ação.`);
  }
}

async function isObraAcessivel(db: Db, obraId: string, profileId: string): Promise<boolean> {
  const { count, error } = await db
    .from("obra_profile")
    .select("*", { count: "exact", head: true })
    .eq("obra_id", obraId)
    .eq("profile_id", profileId);

  if (error) {
    throw new Error(`Failed to check obra access: ${error.message}`);
  }

  return (count ?? 0) > 0;
}

async function getPedidoRow(db: Db, pedidoId: string): Promise<Pedido> {
  const { data, error } = await db.from("pedidos").select("*").eq("id", pedidoId).maybeSingle();

  if (error) {
    throw new Error(`Failed to fetch pedido: ${error.message}`);
  }
  if (!data) {
    throw new NotFoundError(`Pedido ${pedidoId} não encontrado.`);
  }

  return data;
}

async function updatePedidoRow(
  db: Db,
  pedidoId: string,
  changes: Partial<
    Pick<Pedido, "responsible_id" | "priority_id" | "expected_delivery_at" | "status_id">
  >,
): Promise<Pedido> {
  const { data, error } = await db
    .from("pedidos")
    .update(changes)
    .eq("id", pedidoId)
    .select()
    .single();

  if (error || !data) {
    throw new Error(`Failed to update pedido: ${error?.message}`);
  }

  return data;
}

async function getStatusById(db: Db, statusId: string): Promise<Status | null> {
  const { data, error } = await db.from("statuses").select("*").eq("id", statusId).maybeSingle();

  if (error) {
    throw new Error(`Failed to fetch status: ${error.message}`);
  }

  return data;
}

async function getStatusBySlug(db: Db, slug: StatusSlug): Promise<Status> {
  const { data, error } = await db.from("statuses").select("*").eq("slug", slug).maybeSingle();

  if (error) {
    throw new Error(`Failed to fetch status: ${error.message}`);
  }
  if (!data) {
    throw new Error(`Status "${slug}" não encontrado nos lookups.`);
  }

  return data;
}

async function getPriorityById(db: Db, priorityId: string): Promise<Priority | null> {
  const { data, error } = await db
    .from("priorities")
    .select("*")
    .eq("id", priorityId)
    .maybeSingle();

  if (error) {
    throw new Error(`Failed to fetch priority: ${error.message}`);
  }

  return data;
}

async function getEventTypeBySlug(db: Db, slug: EventTypeSlug): Promise<EventType> {
  const { data, error } = await db.from("event_types").select("*").eq("slug", slug).maybeSingle();

  if (error) {
    throw new Error(`Failed to fetch event type: ${error.message}`);
  }
  if (!data) {
    throw new Error(`Event type "${slug}" não encontrado nos lookups.`);
  }

  return data;
}

async function insertEvent(
  db: Db,
  params: {
    pedidoId: string;
    eventTypeSlug: EventTypeSlug;
    previousValue: string | null;
    newValue: string | null;
    actorId: string;
  },
): Promise<void> {
  const eventType = await getEventTypeBySlug(db, params.eventTypeSlug);

  const { error } = await db.from("pedido_events").insert({
    pedido_id: params.pedidoId,
    event_type_id: eventType.id,
    previous_value: params.previousValue,
    new_value: params.newValue,
    actor_id: params.actorId,
  });

  if (error) {
    throw new Error(`Failed to record pedido event: ${error.message}`);
  }
}
