import { describe, expect, it, vi } from "vitest";
import { createObraProfileWithObra, createProfile, createTestDb } from "@/lib/pedidos/testing";
import { createPedido } from "@/lib/pedidos/service";
import type { PrioritySlug, StatusSlug } from "@/lib/types/domain";
import { DEMO_PREFIX } from "./data";
import { resetDemoData } from "./reset";
import { seedDemoData, type SeedResult } from "./seed";

// seedDemoData/resetDemoData each make many sequential real Supabase Admin
// API calls (one per demo auth user, obra, pedido, ...); under concurrent
// suite load the default 5s vitest timeout is too tight for that round-trip
// count even though no single call is slow.
vi.setConfig({ testTimeout: 20000 });

/**
 * Seed and reset share the same global `is_demo = true` scope, so their
 * tests live in one file on purpose: Vitest always runs a single file's
 * tests sequentially in one worker, which guarantees a reset assertion here
 * never races a seed call running concurrently in another file's worker
 * (both would otherwise stomp on the same rows).
 */
describe("demo seed", () => {
  const db = createTestDb();

  it("produces the expected obras/profiles/pedidos, all flagged is_demo, with an atrasado and an entregue pedido", async () => {
    const result = await seedDemoData(db);

    expect(result.obras.length).toBeGreaterThanOrEqual(3);
    expect(result.profiles.length).toBeGreaterThanOrEqual(5);
    expect(result.pedidos.length).toBeGreaterThanOrEqual(7);

    expect(result.obras.every((obra) => obra.is_demo)).toBe(true);
    expect(result.profiles.every((profile) => profile.is_demo)).toBe(true);
    expect(result.pedidos.every((pedido) => pedido.is_demo)).toBe(true);

    expect(result.obras.every((obra) => obra.name.startsWith(DEMO_PREFIX))).toBe(true);
    expect(result.pedidos.every((pedido) => pedido.items_description.startsWith("[DEMO-"))).toBe(
      true,
    );

    const { data: statuses } = await db.from("statuses").select("*");
    const { data: priorities } = await db.from("priorities").select("*");
    const statusSlugById = new Map((statuses ?? []).map((s) => [s.id, s.slug as StatusSlug]));
    const prioritySlugById = new Map((priorities ?? []).map((p) => [p.id, p.slug as PrioritySlug]));

    const statusSlugs = new Set(
      result.pedidos.map((pedido) => statusSlugById.get(pedido.status_id)),
    );
    expect(statusSlugs.size).toBeGreaterThanOrEqual(4);

    const prioritySlugs = new Set(
      result.pedidos
        .filter((pedido) => pedido.priority_id)
        .map((pedido) => prioritySlugById.get(pedido.priority_id!)),
    );
    expect(prioritySlugs).toEqual(new Set<PrioritySlug>(["baixa", "normal", "alta", "urgente"]));

    const responsibleIds = new Set(
      result.pedidos
        .filter((pedido) => pedido.responsible_id)
        .map((pedido) => pedido.responsible_id),
    );
    expect(responsibleIds.size).toBeGreaterThanOrEqual(2);

    const today = new Date().toISOString().slice(0, 10);
    const atrasados = result.pedidos.filter((pedido) => {
      const slug = statusSlugById.get(pedido.status_id);
      return pedido.needed_at < today && slug !== "entregue" && slug !== "cancelado";
    });
    expect(atrasados.length).toBeGreaterThanOrEqual(1);

    const entregues = result.pedidos.filter(
      (pedido) => statusSlugById.get(pedido.status_id) === "entregue",
    );
    expect(entregues.length).toBeGreaterThanOrEqual(1);

    const cancelados = result.pedidos.filter(
      (pedido) => statusSlugById.get(pedido.status_id) === "cancelado",
    );
    expect(cancelados.length).toBeGreaterThanOrEqual(1);
  });

  it("links at least one demo Obra profile to more than one obra via obra_profile", async () => {
    const result = await seedDemoData(db);

    const obraProfileIds = result.profiles
      .filter((profile) => profile.role.slug === "obra")
      .map((profile) => profile.id);

    const { data: links, error } = await db
      .from("obra_profile")
      .select("obra_id, profile_id")
      .in("profile_id", obraProfileIds);

    expect(error).toBeNull();

    const obraCountByProfile = new Map<string, number>();
    for (const link of links ?? []) {
      obraCountByProfile.set(link.profile_id, (obraCountByProfile.get(link.profile_id) ?? 0) + 1);
    }

    expect(Array.from(obraCountByProfile.values()).some((count) => count > 1)).toBe(true);
  });

  it("re-running the seed does not duplicate obras, profiles or pedidos", async () => {
    const first: SeedResult = await seedDemoData(db);
    const second: SeedResult = await seedDemoData(db);

    expect(second.obras.map((o) => o.id).sort()).toEqual(first.obras.map((o) => o.id).sort());
    expect(second.profiles.map((p) => p.id).sort()).toEqual(first.profiles.map((p) => p.id).sort());
    expect(second.pedidos.map((p) => p.id).sort()).toEqual(first.pedidos.map((p) => p.id).sort());

    const { count: obrasCount } = await db
      .from("obras")
      .select("*", { count: "exact", head: true })
      .eq("is_demo", true);
    const { count: profilesCount } = await db
      .from("profiles")
      .select("*", { count: "exact", head: true })
      .eq("is_demo", true);
    const { count: pedidosCount } = await db
      .from("pedidos")
      .select("*", { count: "exact", head: true })
      .eq("is_demo", true);

    expect(obrasCount).toBe(first.obras.length);
    expect(profilesCount).toBe(first.profiles.length);
    expect(pedidosCount).toBe(first.pedidos.length);
  });
});

describe("demo reset", () => {
  const db = createTestDb();

  it("removes exclusively is_demo=true rows from obras/profiles/pedidos (cascading to pedido_events/obra_profile) and preserves real data", async () => {
    const seeded = await seedDemoData(db);

    const { profile: realProfile, obra: realObra } = await createObraProfileWithObra(db);
    const realPedido = await createPedido(
      db,
      {
        obra_id: realObra.id,
        needed_at: "2099-01-01",
        items_description: "Pedido real — não deve ser afetado pelo reset de demonstração.",
      },
      realProfile,
    );

    const result = await resetDemoData(db);

    expect(result.pedidosDeleted).toBe(seeded.pedidos.length);
    expect(result.profilesDeleted).toBe(seeded.profiles.length);
    expect(result.obrasDeleted).toBe(seeded.obras.length);

    const { count: demoObras } = await db
      .from("obras")
      .select("*", { count: "exact", head: true })
      .eq("is_demo", true);
    const { count: demoProfiles } = await db
      .from("profiles")
      .select("*", { count: "exact", head: true })
      .eq("is_demo", true);
    const { count: demoPedidos } = await db
      .from("pedidos")
      .select("*", { count: "exact", head: true })
      .eq("is_demo", true);

    expect(demoObras).toBe(0);
    expect(demoProfiles).toBe(0);
    expect(demoPedidos).toBe(0);

    const { data: realObraRow } = await db
      .from("obras")
      .select("*")
      .eq("id", realObra.id)
      .maybeSingle();
    const { data: realProfileRow } = await db
      .from("profiles")
      .select("*")
      .eq("id", realProfile.id)
      .maybeSingle();
    const { data: realPedidoRow } = await db
      .from("pedidos")
      .select("*")
      .eq("id", realPedido.id)
      .maybeSingle();
    const { data: realPedidoEvents } = await db
      .from("pedido_events")
      .select("*")
      .eq("pedido_id", realPedido.id);
    const { data: realObraProfileLink } = await db
      .from("obra_profile")
      .select("*")
      .eq("obra_id", realObra.id)
      .eq("profile_id", realProfile.id);

    expect(realObraRow).not.toBeNull();
    expect(realProfileRow).not.toBeNull();
    expect(realPedidoRow).not.toBeNull();
    expect(realPedidoEvents?.length).toBeGreaterThan(0);
    expect(realObraProfileLink?.length).toBe(1);
  });

  it("is a no-op that leaves real data untouched when there is no demo data left to remove", async () => {
    await resetDemoData(db);

    const realProfile = await createProfile(db, "gestao");

    const result = await resetDemoData(db);

    expect(result).toMatchObject({
      pedidosDeleted: 0,
      pedidoEventsDeleted: 0,
      profilesDeleted: 0,
      obrasDeleted: 0,
    });

    const { data: realProfileRow } = await db
      .from("profiles")
      .select("*")
      .eq("id", realProfile.id)
      .maybeSingle();
    expect(realProfileRow).not.toBeNull();
  });
});
