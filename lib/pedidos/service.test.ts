import { describe, expect, it } from "vitest";
import type { Db } from "@/lib/supabase/types";
import { ConflictError, ForbiddenError, ValidationError } from "./errors";
import {
  cancelPedido,
  createPedido,
  updatePedidoPrevisao,
  updatePedidoPrioridade,
  updatePedidoResponsavel,
  updatePedidoStatus,
} from "./service";
import {
  createObraProfileWithObra,
  createProfile,
  createTestDb,
  getEventsForPedido,
  getPriorityBySlug,
  getStatusBySlug,
} from "./testing";

async function createFixturePedido(db: Db) {
  const { profile: requester, obra } = await createObraProfileWithObra(db);
  const pedido = await createPedido(
    db,
    { obra_id: obra.id, needed_at: "2026-12-01", items_description: "10 sacos de cimento" },
    requester,
  );
  return { requester, obra, pedido };
}

describe("createPedido", () => {
  const db = createTestDb();

  it("creates a pedido in status solicitado with a code assigned", async () => {
    const { profile, obra } = await createObraProfileWithObra(db);

    const pedido = await createPedido(
      db,
      { obra_id: obra.id, needed_at: "2026-12-01", items_description: "20 sacos de cimento" },
      profile,
    );

    expect(pedido.code).toMatch(/^PED-\d{6}$/);
    expect(pedido.obra_id).toBe(obra.id);
    expect(pedido.requester_id).toBe(profile.id);

    const solicitado = await getStatusBySlug(db, "solicitado");
    expect(pedido.status_id).toBe(solicitado.id);
  });

  it("rejects creation missing obra_id, needed_at or items_description", async () => {
    const { profile, obra } = await createObraProfileWithObra(db);

    await expect(
      createPedido(db, { obra_id: "", needed_at: "2026-12-01", items_description: "x" }, profile),
    ).rejects.toThrow(ValidationError);

    await expect(
      createPedido(db, { obra_id: obra.id, needed_at: "", items_description: "x" }, profile),
    ).rejects.toThrow(ValidationError);

    await expect(
      createPedido(
        db,
        { obra_id: obra.id, needed_at: "2026-12-01", items_description: "" },
        profile,
      ),
    ).rejects.toThrow(ValidationError);
  });

  it("rejects creation for an obra outside the requester's scope", async () => {
    const { profile } = await createObraProfileWithObra(db);
    const { obra: otherObra } = await createObraProfileWithObra(db);

    await expect(
      createPedido(
        db,
        { obra_id: otherObra.id, needed_at: "2026-12-01", items_description: "x" },
        profile,
      ),
    ).rejects.toThrow(ForbiddenError);
  });

  it("produces exactly one criacao_pedido event per successful creation", async () => {
    const { profile, obra } = await createObraProfileWithObra(db);

    const pedido = await createPedido(
      db,
      { obra_id: obra.id, needed_at: "2026-12-01", items_description: "x" },
      profile,
    );

    const events = await getEventsForPedido(db, pedido.id);
    expect(events.filter((e) => e.slug === "criacao_pedido")).toHaveLength(1);
  });
});

describe("updatePedidoResponsavel", () => {
  const db = createTestDb();

  it("updates responsible_id and records an alteracao_responsavel event", async () => {
    const { pedido } = await createFixturePedido(db);
    const suprimentos = await createProfile(db, "suprimentos");
    const responsavel = await createProfile(db, "suprimentos");

    const updated = await updatePedidoResponsavel(db, pedido.id, responsavel.id, suprimentos);

    expect(updated.responsible_id).toBe(responsavel.id);

    const events = await getEventsForPedido(db, pedido.id);
    const event = events.find((e) => e.slug === "alteracao_responsavel");
    expect(event).toMatchObject({ previous_value: null, new_value: responsavel.id });
  });

  it("rejects actors with role obra or gestao", async () => {
    const { pedido, requester } = await createFixturePedido(db);
    const gestao = await createProfile(db, "gestao");
    const responsavel = await createProfile(db, "suprimentos");

    await expect(updatePedidoResponsavel(db, pedido.id, responsavel.id, requester)).rejects.toThrow(
      ForbiddenError,
    );
    await expect(updatePedidoResponsavel(db, pedido.id, responsavel.id, gestao)).rejects.toThrow(
      ForbiddenError,
    );
  });

  it("does not record an event when reassigning the same responsible", async () => {
    const { pedido } = await createFixturePedido(db);
    const suprimentos = await createProfile(db, "suprimentos");
    const responsavel = await createProfile(db, "suprimentos");

    await updatePedidoResponsavel(db, pedido.id, responsavel.id, suprimentos);
    await updatePedidoResponsavel(db, pedido.id, responsavel.id, suprimentos);

    const events = await getEventsForPedido(db, pedido.id);
    expect(events.filter((e) => e.slug === "alteracao_responsavel")).toHaveLength(1);
  });
});

describe("updatePedidoPrioridade", () => {
  const db = createTestDb();

  it("persists a valid priority change and records the event", async () => {
    const { pedido } = await createFixturePedido(db);
    const suprimentos = await createProfile(db, "suprimentos");
    const alta = await getPriorityBySlug(db, "alta");

    const updated = await updatePedidoPrioridade(db, pedido.id, alta.id, suprimentos);

    expect(updated.priority_id).toBe(alta.id);

    const events = await getEventsForPedido(db, pedido.id);
    const event = events.find((e) => e.slug === "alteracao_prioridade");
    expect(event).toMatchObject({ previous_value: null, new_value: "alta" });
  });

  it("rejects non-suprimentos actors", async () => {
    const { pedido, requester } = await createFixturePedido(db);
    const alta = await getPriorityBySlug(db, "alta");

    await expect(updatePedidoPrioridade(db, pedido.id, alta.id, requester)).rejects.toThrow(
      ForbiddenError,
    );
  });

  it("rejects an invalid priority id", async () => {
    const { pedido } = await createFixturePedido(db);
    const suprimentos = await createProfile(db, "suprimentos");

    await expect(
      updatePedidoPrioridade(db, pedido.id, "00000000-0000-0000-0000-000000000000", suprimentos),
    ).rejects.toThrow(ValidationError);
  });
});

describe("updatePedidoPrevisao", () => {
  const db = createTestDb();

  it("persists the expected delivery date and records the event", async () => {
    const { pedido } = await createFixturePedido(db);
    const suprimentos = await createProfile(db, "suprimentos");

    const updated = await updatePedidoPrevisao(db, pedido.id, "2026-12-15", suprimentos);
    expect(updated.expected_delivery_at).toBe("2026-12-15");

    const events = await getEventsForPedido(db, pedido.id);
    const event = events.find((e) => e.slug === "alteracao_previsao");
    expect(event).toMatchObject({ previous_value: null, new_value: "2026-12-15" });
  });

  it("rejects non-suprimentos actors", async () => {
    const { pedido, requester } = await createFixturePedido(db);

    await expect(updatePedidoPrevisao(db, pedido.id, "2026-12-15", requester)).rejects.toThrow(
      ForbiddenError,
    );
  });
});

describe("updatePedidoStatus", () => {
  const db = createTestDb();

  it("transitions between active non-final statuses and records mudanca_status", async () => {
    const { pedido } = await createFixturePedido(db);
    const suprimentos = await createProfile(db, "suprimentos");
    const emAnalise = await getStatusBySlug(db, "em_analise");

    const updated = await updatePedidoStatus(db, pedido.id, emAnalise.id, suprimentos);
    expect(updated.status_id).toBe(emAnalise.id);

    const events = await getEventsForPedido(db, pedido.id);
    const event = events.find((e) => e.slug === "mudanca_status");
    expect(event).toMatchObject({ previous_value: "solicitado", new_value: "em_analise" });
  });

  it("transitions to entregue and records an entrega event (not mudanca_status)", async () => {
    const { pedido } = await createFixturePedido(db);
    const suprimentos = await createProfile(db, "suprimentos");
    const entregue = await getStatusBySlug(db, "entregue");

    const updated = await updatePedidoStatus(db, pedido.id, entregue.id, suprimentos);
    expect(updated.status_id).toBe(entregue.id);

    const events = await getEventsForPedido(db, pedido.id);
    const event = events.find((e) => e.slug === "entrega");
    expect(event).toMatchObject({ previous_value: "solicitado", new_value: "entregue" });
    expect(events.some((e) => e.slug === "mudanca_status")).toBe(false);
  });

  it("rejects setting status to cancelado", async () => {
    const { pedido } = await createFixturePedido(db);
    const suprimentos = await createProfile(db, "suprimentos");
    const cancelado = await getStatusBySlug(db, "cancelado");

    await expect(updatePedidoStatus(db, pedido.id, cancelado.id, suprimentos)).rejects.toThrow(
      ValidationError,
    );
  });

  it("rejects changing the status of an already entregue or cancelado pedido", async () => {
    const { pedido: entreguePedido } = await createFixturePedido(db);
    const { pedido: canceladoPedido } = await createFixturePedido(db);
    const suprimentos = await createProfile(db, "suprimentos");
    const entregue = await getStatusBySlug(db, "entregue");
    const emAnalise = await getStatusBySlug(db, "em_analise");

    await updatePedidoStatus(db, entreguePedido.id, entregue.id, suprimentos);
    await cancelPedido(db, canceladoPedido.id, suprimentos);

    await expect(
      updatePedidoStatus(db, entreguePedido.id, emAnalise.id, suprimentos),
    ).rejects.toThrow(ConflictError);
    await expect(
      updatePedidoStatus(db, canceladoPedido.id, emAnalise.id, suprimentos),
    ).rejects.toThrow(ConflictError);
  });

  it("rejects non-suprimentos actors", async () => {
    const { pedido, requester } = await createFixturePedido(db);
    const emAnalise = await getStatusBySlug(db, "em_analise");

    await expect(updatePedidoStatus(db, pedido.id, emAnalise.id, requester)).rejects.toThrow(
      ForbiddenError,
    );
  });
});

describe("cancelPedido", () => {
  const db = createTestDb();

  it("cancels an active pedido and records a cancelamento event", async () => {
    const { pedido } = await createFixturePedido(db);
    const suprimentos = await createProfile(db, "suprimentos");
    const cancelado = await getStatusBySlug(db, "cancelado");

    const updated = await cancelPedido(db, pedido.id, suprimentos);
    expect(updated.status_id).toBe(cancelado.id);

    const events = await getEventsForPedido(db, pedido.id);
    expect(events.some((e) => e.slug === "cancelamento")).toBe(true);
  });

  it("rejects canceling an already entregue pedido", async () => {
    const { pedido } = await createFixturePedido(db);
    const suprimentos = await createProfile(db, "suprimentos");
    const entregue = await getStatusBySlug(db, "entregue");

    await updatePedidoStatus(db, pedido.id, entregue.id, suprimentos);

    await expect(cancelPedido(db, pedido.id, suprimentos)).rejects.toThrow(ConflictError);
  });

  it("rejects canceling an already cancelado pedido", async () => {
    const { pedido } = await createFixturePedido(db);
    const suprimentos = await createProfile(db, "suprimentos");

    await cancelPedido(db, pedido.id, suprimentos);

    await expect(cancelPedido(db, pedido.id, suprimentos)).rejects.toThrow(ConflictError);
  });

  it("rejects non-suprimentos actors", async () => {
    const { pedido, requester } = await createFixturePedido(db);

    await expect(cancelPedido(db, pedido.id, requester)).rejects.toThrow(ForbiddenError);
  });
});
