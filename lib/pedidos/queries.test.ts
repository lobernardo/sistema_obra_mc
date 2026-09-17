import { describe, expect, it } from "vitest";
import { cancelPedido, createPedido, updatePedidoStatus } from "./service";
import {
  getPedidoByIdOrCode,
  listObrasAcessiveis,
  listPedidos,
  listPriorities,
  listStatuses,
  listSuprimentosProfiles,
} from "./queries";
import {
  createObraProfileWithObra,
  createProfile,
  createTestDb,
  getStatusBySlug,
} from "./testing";

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

  it("filters using the shared pendente rule (excludes entregue and cancelado)", async () => {
    const { profile, obra } = await createObraProfileWithObra(db);
    const suprimentos = await createProfile(db, "suprimentos");

    const solicitado = await createPedido(
      db,
      { obra_id: obra.id, needed_at: "2026-12-01", items_description: "pendente" },
      profile,
    );
    const paraEntregar = await createPedido(
      db,
      { obra_id: obra.id, needed_at: "2026-12-01", items_description: "sera entregue" },
      profile,
    );
    const entregueStatus = await getStatusBySlug(db, "entregue");
    await updatePedidoStatus(db, paraEntregar.id, entregueStatus.id, suprimentos);

    const paraCancelar = await createPedido(
      db,
      { obra_id: obra.id, needed_at: "2026-12-01", items_description: "sera cancelado" },
      profile,
    );
    await cancelPedido(db, paraCancelar.id, suprimentos);

    const pendentes = await listPedidos(db, { obraId: obra.id, pendente: true });
    const naoPendentes = await listPedidos(db, { obraId: obra.id, pendente: false });

    expect(pendentes.map((p) => p.id)).toContain(solicitado.id);
    expect(pendentes.map((p) => p.id)).not.toContain(paraEntregar.id);
    expect(pendentes.map((p) => p.id)).not.toContain(paraCancelar.id);

    expect(naoPendentes.map((p) => p.id)).toContain(paraEntregar.id);
    expect(naoPendentes.map((p) => p.id)).toContain(paraCancelar.id);
    expect(naoPendentes.map((p) => p.id)).not.toContain(solicitado.id);
  });

  it("combines two or more filters, returning only pedidos matching every criterion at once", async () => {
    const { profile, obra } = await createObraProfileWithObra(db);
    const { profile: otherProfile, obra: otherObra } = await createObraProfileWithObra(db);
    const suprimentos = await createProfile(db, "suprimentos");

    const matching = await createPedido(
      db,
      { obra_id: obra.id, needed_at: "2026-12-01", items_description: "item" },
      profile,
    );
    const emAnalise = await getStatusBySlug(db, "em_analise");
    await updatePedidoStatus(db, matching.id, emAnalise.id, suprimentos);

    // Same obra, different status — must be excluded by the status filter.
    await createPedido(
      db,
      { obra_id: obra.id, needed_at: "2026-12-01", items_description: "outro item" },
      profile,
    );
    // Same status, different obra — must be excluded by the obra filter.
    const otherPedido = await createPedido(
      db,
      { obra_id: otherObra.id, needed_at: "2026-12-01", items_description: "item" },
      otherProfile,
    );
    await updatePedidoStatus(db, otherPedido.id, emAnalise.id, suprimentos);

    const result = await listPedidos(db, { obraId: obra.id, statusId: emAnalise.id });

    expect(result.map((p) => p.id)).toEqual([matching.id]);
  });
});

describe("listStatuses", () => {
  const db = createTestDb();

  it("returns every status ordered by sort_order", async () => {
    const statuses = await listStatuses(db);

    expect(statuses.map((s) => s.slug)).toEqual([
      "solicitado",
      "em_analise",
      "em_compra_preparacao",
      "aguardando_entrega",
      "entregue",
      "cancelado",
    ]);
  });
});

describe("listPriorities", () => {
  const db = createTestDb();

  it("returns every priority ordered by sort_order", async () => {
    const priorities = await listPriorities(db);

    expect(priorities.map((p) => p.slug)).toEqual(["baixa", "normal", "alta", "urgente"]);
  });
});

describe("listSuprimentosProfiles", () => {
  const db = createTestDb();

  it("returns only active suprimentos profiles, ordered by name", async () => {
    const suprimentos = await createProfile(db, "suprimentos", { fullName: "Zeca Suprimentos" });
    const otherSuprimentos = await createProfile(db, "suprimentos", { fullName: "Ana Suprimentos" });
    const obraProfile = await createProfile(db, "obra");
    const gestaoProfile = await createProfile(db, "gestao");

    const result = await listSuprimentosProfiles(db);
    const ids = result.map((p) => p.id);

    expect(ids).toContain(suprimentos.id);
    expect(ids).toContain(otherSuprimentos.id);
    expect(ids).not.toContain(obraProfile.id);
    expect(ids).not.toContain(gestaoProfile.id);

    const indexOfAna = ids.indexOf(otherSuprimentos.id);
    const indexOfZeca = ids.indexOf(suprimentos.id);
    expect(indexOfAna).toBeLessThan(indexOfZeca);
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
