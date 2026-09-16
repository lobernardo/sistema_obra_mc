import { describe, expect, it } from "vitest";
import { createPedido } from "./service";
import { getPedidoByIdOrCode, listObrasAcessiveis, listPedidos } from "./queries";
import { createObraProfileWithObra, createProfile, createTestDb } from "./testing";

describe("listObrasAcessiveis", () => {
  const db = createTestDb();

  it("returns only the obras linked to an Obra profile", async () => {
    const { profile, obra } = await createObraProfileWithObra(db);
    const { obra: otherObra } = await createObraProfileWithObra(db);

    const obras = await listObrasAcessiveis(db, profile);

    expect(obras.map((o) => o.id)).toContain(obra.id);
    expect(obras.map((o) => o.id)).not.toContain(otherObra.id);
  });

  it("returns every obra for suprimentos and gestao profiles", async () => {
    const { obra } = await createObraProfileWithObra(db);
    const suprimentos = await createProfile(db, "suprimentos");
    const gestao = await createProfile(db, "gestao");

    const forSuprimentos = await listObrasAcessiveis(db, suprimentos);
    const forGestao = await listObrasAcessiveis(db, gestao);

    expect(forSuprimentos.map((o) => o.id)).toContain(obra.id);
    expect(forGestao.map((o) => o.id)).toContain(obra.id);
  });
});

describe("listPedidos", () => {
  const db = createTestDb();

  it("filters by obraId and by search text", async () => {
    const { profile, obra } = await createObraProfileWithObra(db);
    const { profile: otherProfile, obra: otherObra } = await createObraProfileWithObra(db);

    const pedido = await createPedido(
      db,
      { obra_id: obra.id, needed_at: "2026-12-01", items_description: "20 sacos de cimento" },
      profile,
    );
    await createPedido(
      db,
      { obra_id: otherObra.id, needed_at: "2026-12-01", items_description: "outro item" },
      otherProfile,
    );

    const byObra = await listPedidos(db, { obraId: obra.id });
    expect(byObra.map((p) => p.id)).toContain(pedido.id);
    expect(byObra.every((p) => p.obra_id === obra.id)).toBe(true);

    const bySearch = await listPedidos(db, { search: pedido.code });
    expect(bySearch.map((p) => p.id)).toEqual([pedido.id]);

    const resolved = byObra.find((p) => p.id === pedido.id);
    expect(resolved?.obra.id).toBe(obra.id);
    expect(resolved?.status.slug).toBe("solicitado");
    expect(resolved?.requester.id).toBe(profile.id);
  });

  it("filters using the shared atraso rule", async () => {
    const { profile, obra } = await createObraProfileWithObra(db);

    const atrasado = await createPedido(
      db,
      { obra_id: obra.id, needed_at: "2020-01-01", items_description: "item atrasado" },
      profile,
    );
    const emDia = await createPedido(
      db,
      { obra_id: obra.id, needed_at: "2099-01-01", items_description: "item em dia" },
      profile,
    );

    const atrasados = await listPedidos(db, { obraId: obra.id, atrasado: true });
    const emDiaLista = await listPedidos(db, { obraId: obra.id, atrasado: false });

    expect(atrasados.map((p) => p.id)).toContain(atrasado.id);
    expect(atrasados.map((p) => p.id)).not.toContain(emDia.id);
    expect(emDiaLista.map((p) => p.id)).toContain(emDia.id);
    expect(emDiaLista.map((p) => p.id)).not.toContain(atrasado.id);
  });
});

describe("getPedidoByIdOrCode", () => {
  const db = createTestDb();

  it("resolves relations and orders history events chronologically, by id or by code", async () => {
    const { profile, obra } = await createObraProfileWithObra(db);

    const pedido = await createPedido(
      db,
      { obra_id: obra.id, needed_at: "2026-12-01", items_description: "5 vergalhões" },
      profile,
    );

    const byCode = await getPedidoByIdOrCode(db, pedido.code);
    const byId = await getPedidoByIdOrCode(db, pedido.id);

    expect(byCode?.id).toBe(pedido.id);
    expect(byId?.id).toBe(pedido.id);
    expect(byCode?.obra.id).toBe(obra.id);
    expect(byCode?.requester.id).toBe(profile.id);
    expect(byCode?.events).toHaveLength(1);
    expect(byCode?.events[0]?.eventType.slug).toBe("criacao_pedido");
    expect(byCode?.events[0]?.actor.id).toBe(profile.id);
  });

  it("returns null for an unknown code", async () => {
    const result = await getPedidoByIdOrCode(db, "PED-999999");
    expect(result).toBeNull();
  });
});
