import { describe, expect, it } from "vitest";
import { createPedido } from "@/lib/pedidos/service";
import {
  createAuthenticatedProfile,
  createObra,
  createTestDb,
  getPriorityBySlug,
  getStatusBySlug,
  linkObraProfile,
} from "@/lib/pedidos/testing";

async function fixturePedido(adminDb: ReturnType<typeof createTestDb>) {
  const { profile: requester, db: obraDb } = await createAuthenticatedProfile(adminDb, "obra");
  const obra = await createObra(adminDb);
  await linkObraProfile(adminDb, obra.id, requester.id);

  const pedido = await createPedido(
    adminDb,
    { obra_id: obra.id, needed_at: "2026-12-01", items_description: "10 sacos de cimento" },
    requester,
  );

  return { requester, obraDb, obra, pedido };
}

describe("RLS: pedidos", () => {
  const adminDb = createTestDb();

  it("Obra sees only pedidos of its own obras", async () => {
    const { obraDb, pedido } = await fixturePedido(adminDb);
    const { obra: otherObra } = await fixturePedido(adminDb);

    const { data } = await obraDb.from("pedidos").select("*");

    expect(data?.map((p) => p.id)).toContain(pedido.id);
    expect(data?.every((p) => p.obra_id !== otherObra.id)).toBe(true);
  });

  it("rejects Obra altering needed_at, items_description or obra_id of its own pedido", async () => {
    const { obraDb, pedido } = await fixturePedido(adminDb);
    const { obra: otherObra } = await fixturePedido(adminDb);

    await obraDb.from("pedidos").update({ needed_at: "2027-01-01" }).eq("id", pedido.id);
    await obraDb.from("pedidos").update({ items_description: "tampered" }).eq("id", pedido.id);
    await obraDb.from("pedidos").update({ obra_id: otherObra.id }).eq("id", pedido.id);

    const { data: unchanged } = await adminDb
      .from("pedidos")
      .select("*")
      .eq("id", pedido.id)
      .single();

    expect(unchanged?.needed_at).toBe(pedido.needed_at);
    expect(unchanged?.items_description).toBe(pedido.items_description);
    expect(unchanged?.obra_id).toBe(pedido.obra_id);
  });

  it("lets Suprimentos update status_id, priority_id, responsible_id and expected_delivery_at on any pedido", async () => {
    const { pedido } = await fixturePedido(adminDb);
    const { db: suprimentosDb, profile: responsavel } = await createAuthenticatedProfile(
      adminDb,
      "suprimentos",
    );
    const emAnalise = await getStatusBySlug(adminDb, "em_analise");
    const alta = await getPriorityBySlug(adminDb, "alta");

    const { data: updated, error } = await suprimentosDb
      .from("pedidos")
      .update({
        status_id: emAnalise.id,
        priority_id: alta.id,
        responsible_id: responsavel.id,
        expected_delivery_at: "2026-12-20",
      })
      .eq("id", pedido.id)
      .select()
      .single();

    expect(error).toBeNull();
    expect(updated?.status_id).toBe(emAnalise.id);
    expect(updated?.priority_id).toBe(alta.id);
    expect(updated?.responsible_id).toBe(responsavel.id);
    expect(updated?.expected_delivery_at).toBe("2026-12-20");
  });

  it("rejects any UPDATE on pedidos from Gestão", async () => {
    const { pedido } = await fixturePedido(adminDb);
    const { db: gestaoDb } = await createAuthenticatedProfile(adminDb, "gestao");
    const emAnalise = await getStatusBySlug(adminDb, "em_analise");

    await gestaoDb.from("pedidos").update({ status_id: emAnalise.id }).eq("id", pedido.id);

    const { data: unchanged } = await adminDb
      .from("pedidos")
      .select("status_id")
      .eq("id", pedido.id)
      .single();

    expect(unchanged?.status_id).toBe(pedido.status_id);
  });

  it("lets Obra insert a pedido only for an obra it belongs to", async () => {
    const { profile, db: obraDb } = await createAuthenticatedProfile(adminDb, "obra");
    const own = await createObra(adminDb);
    await linkObraProfile(adminDb, own.id, profile.id);
    const other = await createObra(adminDb);

    const solicitado = await getStatusBySlug(adminDb, "solicitado");
    const { data: code } = await obraDb.rpc("next_pedido_code");

    const { error: okError } = await obraDb.from("pedidos").insert({
      code: code as unknown as string,
      obra_id: own.id,
      requester_id: profile.id,
      needed_at: "2026-12-01",
      items_description: "item",
      status_id: solicitado.id,
    });
    expect(okError).toBeNull();

    const { data: code2 } = await obraDb.rpc("next_pedido_code");
    const { error: forbiddenError } = await obraDb.from("pedidos").insert({
      code: code2 as unknown as string,
      obra_id: other.id,
      requester_id: profile.id,
      needed_at: "2026-12-01",
      items_description: "item",
      status_id: solicitado.id,
    });
    expect(forbiddenError).not.toBeNull();
  });
});
