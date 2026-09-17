import type { StatusSlug } from "@/lib/types/domain";

/** The two conclusion states a pedido's workflow can end in. */
const CONCLUSAO: ReadonlySet<StatusSlug> = new Set(["entregue", "cancelado"]);

/**
 * Single source of truth for the "pendente" rule (US-7.1): a pedido is
 * pendente when its status is neither `entregue` nor `cancelado`. Reused by
 * `listPedidos`'s `pendente` filter and the dashboard's "Pendentes"
 * indicator, so both can never disagree on what counts as pending.
 */
export function isPedidoPendente(pedido: { status: { slug: string } }): boolean {
  return !CONCLUSAO.has(pedido.status.slug as StatusSlug);
}
