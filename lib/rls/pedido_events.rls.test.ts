import { describe, expect, it } from "vitest";
import { createPedido } from "@/lib/pedidos/service";
import {
  createAuthenticatedProfile,
  createObra,
  createTestDb,
  linkObraProfile,
} from "@/lib/pedidos/testing";

describe("RLS: pedido_events", () => {
  const adminDb = createTestDb();

  it("Obra reads the history of its own pedido, but not of an unassociated obra's pedido", async () => {
    const { profile: requester, db: obraDb } = await createAuthenticatedProfile(adminDb, "obra");
    const obra = await createObra(adminDb);
    await linkObraProfile(adminDb, obra.id, requester.id);
    const pedido = await createPedido(
      adminDb,
      { obra_id: obra.id, needed_at: "2026-12-01", items_description: "item" },
      requester,
    );

    const { profile: otherRequester } = await createAuthenticatedProfile(adminDb, "obra");
    const otherObra = await createObra(adminDb);
    await linkObraProfile(adminDb, otherObra.id, otherRequester.id);
    const otherPedido = await createPedido(
      adminDb,
      { obra_id: otherObra.id, needed_at: "2026-12-01", items_description: "item" },
      otherRequester,
    );

    const { data: own } = await obraDb.from("pedido_events").select("*").eq("pedido_id", pedido.id);
    expect(own).not.toHaveLength(0);

    const { data: unassociated } = await obraDb
      .from("pedido_events")
      .select("*")
      .eq("pedido_id", otherPedido.id);
    expect(unassociated).toEqual([]);
  });

  it("rejects a direct INSERT into pedido_events from an authenticated client", async () => {
    const { profile: requester, db: obraDb } = await createAuthenticatedProfile(adminDb, "obra");
    const obra = await createObra(adminDb);
    await linkObraProfile(adminDb, obra.id, requester.id);
    const pedido = await createPedido(
      adminDb,
      { obra_id: obra.id, needed_at: "2026-12-01", items_description: "item" },
      requester,
    );
    const { data: eventType } = await adminDb
      .from("event_types")
      .select("*")
      .eq("slug", "mudanca_status")
      .single();

    const { error } = await obraDb.from("pedido_events").insert({
      pedido_id: pedido.id,
      event_type_id: eventType!.id,
      actor_id: requester.id,
    });

    expect(error).not.toBeNull();
  });
});
