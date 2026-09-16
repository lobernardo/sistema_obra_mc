import type { StatusSlug } from "@/lib/types/domain";

/** Statuses that exempt a pedido from ever being considered atrasado. */
const NAO_ATRASAVEL: ReadonlySet<StatusSlug> = new Set(["entregue", "cancelado"]);

/**
 * Single source of truth for the atraso ("late") rule (US-8.1): a pedido is
 * atrasado when its `needed_at` has passed and its status is neither
 * `entregue` nor `cancelado`. Computed at query time — never persisted — so
 * every consumer (Kanban, listings, filters, dashboard) reuses this exact
 * function and can never disagree or go stale.
 */
export function isPedidoAtrasado(
  pedido: { needed_at: string; status: { slug: string } },
  today: Date = new Date(),
): boolean {
  if (NAO_ATRASAVEL.has(pedido.status.slug as StatusSlug)) {
    return false;
  }

  return pedido.needed_at < isoDate(today);
}

function isoDate(date: Date): string {
  return date.toISOString().slice(0, 10);
}
