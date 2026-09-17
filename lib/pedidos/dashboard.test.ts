import { describe, expect, it } from "vitest";
import { classificarPrazo, computeDashboardIndicators } from "./dashboard";
import { isPedidoAtrasado } from "./atraso";
import type { Obra, PedidoComRelacoes, Status } from "@/lib/types/domain";

const now = "2026-01-01T10:00:00Z";
const TODAY = new Date("2026-09-16T12:00:00Z");

function fixtureStatus(slug: string, name: string, sortOrder: number): Status {
  return {
    id: `status-${slug}`,
    name,
    slug,
    description: null,
    sort_order: sortOrder,
    is_active: true,
    created_at: now,
    updated_at: now,
  };
}

const STATUSES: Status[] = [
  fixtureStatus("solicitado", "Solicitado", 1),
  fixtureStatus("em_analise", "Em análise", 2),
  fixtureStatus("em_compra_preparacao", "Em compra/preparação", 3),
  fixtureStatus("aguardando_entrega", "Aguardando entrega", 4),
  fixtureStatus("entregue", "Entregue", 5),
  fixtureStatus("cancelado", "Cancelado", 6),
];

function fixtureObra(id: string, name: string): Obra {
  return { id, name, is_active: true, is_demo: false, created_at: now, updated_at: now };
}

const OBRA_A = fixtureObra("obra-a", "Obra A");
const OBRA_B = fixtureObra("obra-b", "Obra B");

function statusBySlug(slug: string): Status {
  return STATUSES.find((s) => s.slug === slug)!;
}

function fixturePedido(overrides: Partial<PedidoComRelacoes> & { statusSlug?: string } = {}) {
  const { statusSlug = "solicitado", ...rest } = overrides;
  const status = statusBySlug(statusSlug);

  const pedido: PedidoComRelacoes = {
    id: `pedido-${Math.random()}`,
    code: "PED-000001",
    obra_id: OBRA_A.id,
    requester_id: "requester-1",
    requested_at: now,
    needed_at: "2026-12-01",
    items_description: "item",
    status_id: status.id,
    priority_id: null,
    responsible_id: null,
    expected_delivery_at: null,
    is_demo: false,
    created_at: now,
    updated_at: now,
    obra: OBRA_A,
    status,
    priority: null,
    requester: {
      id: "requester-1",
      full_name: "Maria Obra",
      role_id: "role-obra",
      is_active: true,
      is_demo: false,
      created_at: now,
      updated_at: now,
      role: {
        id: "role-obra",
        name: "Obra",
        slug: "obra",
        description: null,
        is_active: true,
        created_at: now,
        updated_at: now,
      },
    },
    responsible: null,
    ...rest,
  };

  return pedido;
}

describe("classificarPrazo", () => {
  it("returns null for pedidos já concluídos (entregue/cancelado)", () => {
    const entregue = fixturePedido({ statusSlug: "entregue", needed_at: "2020-01-01" });
    const cancelado = fixturePedido({ statusSlug: "cancelado", needed_at: "2020-01-01" });

    expect(classificarPrazo(entregue, TODAY)).toBeNull();
    expect(classificarPrazo(cancelado, TODAY)).toBeNull();
  });

  it("classifies as atrasado exactly when isPedidoAtrasado is true", () => {
    const pedido = fixturePedido({ statusSlug: "em_analise", needed_at: "2020-01-01" });

    expect(classificarPrazo(pedido, TODAY)).toBe("atrasado");
    expect(isPedidoAtrasado(pedido, TODAY)).toBe(true);
  });

  it("classifies as vencendo_em_breve when needed_at is within the warning window", () => {
    const pedido = fixturePedido({ statusSlug: "em_analise", needed_at: "2026-09-18" });

    expect(classificarPrazo(pedido, TODAY)).toBe("vencendo_em_breve");
  });

  it("classifies as dentro_do_prazo when needed_at is comfortably in the future", () => {
    const pedido = fixturePedido({ statusSlug: "em_analise", needed_at: "2026-12-01" });

    expect(classificarPrazo(pedido, TODAY)).toBe("dentro_do_prazo");
  });
});

describe("computeDashboardIndicators", () => {
  it("volumeTotal equals the count of pedidos given", () => {
    const pedidos = [
      fixturePedido({ statusSlug: "solicitado" }),
      fixturePedido({ statusSlug: "entregue" }),
      fixturePedido({ statusSlug: "cancelado" }),
    ];

    const indicators = computeDashboardIndicators(pedidos, STATUSES, [OBRA_A], TODAY);

    expect(indicators.volumeTotal).toBe(3);
  });

  it("pendentes excludes exactly entregue and cancelado", () => {
    const pedidos = [
      fixturePedido({ statusSlug: "solicitado" }),
      fixturePedido({ statusSlug: "em_analise" }),
      fixturePedido({ statusSlug: "entregue" }),
      fixturePedido({ statusSlug: "cancelado" }),
    ];

    const indicators = computeDashboardIndicators(pedidos, STATUSES, [OBRA_A], TODAY);

    expect(indicators.pendentes).toBe(2);
  });

  it("atrasados matches isPedidoAtrasado for every pedido in scope", () => {
    const pedidos = [
      fixturePedido({ statusSlug: "em_analise", needed_at: "2020-01-01" }),
      fixturePedido({ statusSlug: "entregue", needed_at: "2020-01-01" }),
      fixturePedido({ statusSlug: "em_analise", needed_at: "2099-01-01" }),
    ];

    const indicators = computeDashboardIndicators(pedidos, STATUSES, [OBRA_A], TODAY);
    const expected = pedidos.filter((p) => isPedidoAtrasado(p, TODAY)).length;

    expect(indicators.atrasados).toBe(expected);
    expect(indicators.atrasados).toBe(1);
  });

  it("porStatus sums to volumeTotal, ordered by sort_order", () => {
    const pedidos = [
      fixturePedido({ statusSlug: "solicitado" }),
      fixturePedido({ statusSlug: "solicitado" }),
      fixturePedido({ statusSlug: "em_analise" }),
      fixturePedido({ statusSlug: "cancelado" }),
    ];

    const indicators = computeDashboardIndicators(pedidos, STATUSES, [OBRA_A], TODAY);

    expect(indicators.porStatus.map((s) => s.status.slug)).toEqual([
      "solicitado",
      "em_analise",
      "em_compra_preparacao",
      "aguardando_entrega",
      "entregue",
      "cancelado",
    ]);
    expect(indicators.porStatus.reduce((sum, s) => sum + s.count, 0)).toBe(indicators.volumeTotal);
    expect(indicators.porStatus.find((s) => s.status.slug === "solicitado")?.count).toBe(2);
  });

  it("porObra sums to volumeTotal", () => {
    const pedidos = [
      fixturePedido({ obra_id: OBRA_A.id, obra: OBRA_A }),
      fixturePedido({ obra_id: OBRA_A.id, obra: OBRA_A }),
      fixturePedido({ obra_id: OBRA_B.id, obra: OBRA_B }),
    ];

    const indicators = computeDashboardIndicators(pedidos, STATUSES, [OBRA_A, OBRA_B], TODAY);

    expect(indicators.porObra.reduce((sum, o) => sum + o.count, 0)).toBe(indicators.volumeTotal);
    expect(indicators.porObra.find((o) => o.obra.id === OBRA_A.id)?.count).toBe(2);
    expect(indicators.porObra.find((o) => o.obra.id === OBRA_B.id)?.count).toBe(1);
  });

  it("prazos classification is consistent with isPedidoAtrasado for the atrasado case", () => {
    const pedidos = [
      fixturePedido({ statusSlug: "em_analise", needed_at: "2020-01-01" }),
      fixturePedido({ statusSlug: "em_analise", needed_at: "2099-01-01" }),
      fixturePedido({ statusSlug: "entregue", needed_at: "2020-01-01" }),
    ];

    const indicators = computeDashboardIndicators(pedidos, STATUSES, [OBRA_A], TODAY);
    const atrasadoCount = indicators.prazos.find((p) => p.situacao === "atrasado")?.count;

    expect(atrasadoCount).toBe(pedidos.filter((p) => isPedidoAtrasado(p, TODAY)).length);
  });
});
