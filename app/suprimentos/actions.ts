"use server";

import { revalidatePath } from "next/cache";
import { createClient } from "@/lib/supabase/server";
import { createAdminClient } from "@/lib/supabase/admin";
import { getCurrentProfile } from "@/lib/auth/session";
import {
  cancelPedido,
  updatePedidoPrevisao,
  updatePedidoPrioridade,
  updatePedidoResponsavel,
  updatePedidoStatus,
} from "@/lib/pedidos/service";
import { ConflictError, ForbiddenError, NotFoundError, ValidationError } from "@/lib/pedidos/errors";
import type { Db } from "@/lib/supabase/types";
import type { Pedido, Profile } from "@/lib/types/domain";

export interface PedidoActionState {
  error?: string;
  pedido?: Pedido;
}

const GENERIC_ERROR_MESSAGE = "Não foi possível concluir a ação. Tente novamente.";

/**
 * `pedido_events` has no client-writable RLS policy by design (Fase 4.2) —
 * every mutation here writes an event alongside the pedido, so it must go
 * through the service-role client, with the RLS-scoped `profile` below as
 * the trusted authorization boundary (same shape as `createSolicitacao`).
 */
async function runPedidoMutation(
  mutate: (db: Db, actor: Profile) => Promise<Pedido>,
): Promise<PedidoActionState> {
  const sessionDb = await createClient();
  const actor = await getCurrentProfile(sessionDb);

  if (!actor) {
    return { error: "Sessão expirada. Faça login novamente." };
  }

  try {
    const adminDb = createAdminClient();
    const pedido = await mutate(adminDb, actor);
    revalidatePedidoPaths(pedido.code);
    return { pedido };
  } catch (err) {
    if (
      err instanceof ValidationError ||
      err instanceof ForbiddenError ||
      err instanceof NotFoundError ||
      err instanceof ConflictError
    ) {
      return { error: err.message };
    }
    return { error: GENERIC_ERROR_MESSAGE };
  }
}

/** Refreshes every screen (Obra, Suprimentos, Gestão) that surfaces this pedido, so a change shows up without a manual reload. */
function revalidatePedidoPaths(code: string): void {
  revalidatePath("/suprimentos");
  revalidatePath("/suprimentos/pedidos");
  revalidatePath(`/suprimentos/pedidos/${code}`);
  revalidatePath("/obra");
  revalidatePath(`/obra/${code}`);
  revalidatePath("/gestao");
  revalidatePath("/gestao/kanban");
  revalidatePath("/gestao/pedidos");
  revalidatePath(`/gestao/pedidos/${code}`);
}

export async function setResponsavel(
  pedidoId: string,
  responsibleId: string | null,
): Promise<PedidoActionState> {
  return runPedidoMutation((db, actor) =>
    updatePedidoResponsavel(db, pedidoId, responsibleId, actor),
  );
}

export async function setPrioridade(
  pedidoId: string,
  priorityId: string,
): Promise<PedidoActionState> {
  return runPedidoMutation((db, actor) => updatePedidoPrioridade(db, pedidoId, priorityId, actor));
}

export async function setPrevisao(
  pedidoId: string,
  expectedDeliveryAt: string | null,
): Promise<PedidoActionState> {
  return runPedidoMutation((db, actor) =>
    updatePedidoPrevisao(db, pedidoId, expectedDeliveryAt, actor),
  );
}

/** Moves a pedido between active statuses, or to `entregue` — used by Kanban drag-and-drop, its accessible select alternative, and "Marcar como Entregue" alike. */
export async function moveStatus(pedidoId: string, statusId: string): Promise<PedidoActionState> {
  return runPedidoMutation((db, actor) => updatePedidoStatus(db, pedidoId, statusId, actor));
}

export async function cancelarPedido(pedidoId: string): Promise<PedidoActionState> {
  return runPedidoMutation((db, actor) => cancelPedido(db, pedidoId, actor));
}
