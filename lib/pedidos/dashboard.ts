import type { Obra, PedidoComRelacoes, Status } from "@/lib/types/domain";
import { isPedidoAtrasado } from "./atraso";
import { isPedidoPendente } from "./pendente";

export interface StatusCount {
  status: Status;
  count: number;
}

export interface ObraCount {
  obra: Obra;
  count: number;
}

export type PrazoSituacao = "dentro_do_prazo" | "vencendo_em_breve" | "atrasado";

export interface PrazoCount {
  situacao: PrazoSituacao;
  count: number;
}

export interface DashboardIndicators {
  volumeTotal: number;
  pendentes: number;
  atrasados: number;
  /** Ordered by `statuses.sort_order` (PRD §22). */
  porStatus: StatusCount[];
  /** Ordered the same way the `obras` argument was given. */
  porObra: ObraCount[];
  /** Always in the fixed order dentro_do_prazo → vencendo_em_breve → atrasado. */
  prazos: PrazoCount[];
}

/**
 * Days before `needed_at` at which a pendente pedido is flagged "vencendo em
 * breve" rather than "dentro do prazo". PRD §22 doesn't pin an exact window,
 * so this constant is the single place that decision lives.
 */
const VENCENDO_EM_BREVE_DIAS = 3;

const MS_PER_DAY = 24 * 60 * 60 * 1000;

/**
 * Classifies a pedido's situation relative to `needed_at` (US-7.1's "Prazos"
 * indicator) — `null` for pedidos that are already concluded (`entregue`/
 * `cancelado`), since the indicator only covers active pedidos. Reuses
 * `isPedidoAtrasado` for the "atrasado" case, so this can never disagree
 * with Kanban/listagens/filtros on what counts as late.
 */
export function classificarPrazo(
  pedido: PedidoComRelacoes,
  today: Date = new Date(),
): PrazoSituacao | null {
  if (!isPedidoPendente(pedido)) {
    return null;
  }

  if (isPedidoAtrasado(pedido, today)) {
    return "atrasado";
  }

  const diasRestantes = diffInDays(pedido.needed_at, today);
  return diasRestantes <= VENCENDO_EM_BREVE_DIAS ? "vencendo_em_breve" : "dentro_do_prazo";
}

function diffInDays(neededAt: string, today: Date): number {
  const needed = Date.parse(`${neededAt}T00:00:00Z`);
  const todayUtc = Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), today.getUTCDate());
  return Math.round((needed - todayUtc) / MS_PER_DAY);
}

/**
 * Computes every Gestão dashboard indicator (Fase 8.1) from a single
 * already-filtered `pedidos` list, so "Volume total", "Pendentes",
 * "Atrasados", "Distribuição por status", "Prazos" and "Visão por obra"
 * always agree on the same scope/filters by construction — they're all
 * derived from the exact same array, never separate queries that could
 * drift apart.
 */
export function computeDashboardIndicators(
  pedidos: PedidoComRelacoes[],
  statuses: Status[],
  obras: Obra[],
  today: Date = new Date(),
): DashboardIndicators {
  const porStatus: StatusCount[] = statuses.map((status) => ({
    status,
    count: pedidos.filter((pedido) => pedido.status_id === status.id).length,
  }));

  const porObra: ObraCount[] = obras.map((obra) => ({
    obra,
    count: pedidos.filter((pedido) => pedido.obra_id === obra.id).length,
  }));

  const situacoes: PrazoSituacao[] = ["dentro_do_prazo", "vencendo_em_breve", "atrasado"];
  const prazos: PrazoCount[] = situacoes.map((situacao) => ({
    situacao,
    count: pedidos.filter((pedido) => classificarPrazo(pedido, today) === situacao).length,
  }));

  return {
    volumeTotal: pedidos.length,
    pendentes: pedidos.filter(isPedidoPendente).length,
    atrasados: pedidos.filter((pedido) => isPedidoAtrasado(pedido, today)).length,
    porStatus,
    porObra,
    prazos,
  };
}
