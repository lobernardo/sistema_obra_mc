import { describe, expect, it } from "vitest";
import {
  createAuthenticatedProfile,
  createObra,
  createTestDb,
  linkObraProfile,
} from "@/lib/pedidos/testing";

describe("RLS: obras", () => {
  const adminDb = createTestDb();

  it("Obra sees only its associated obras; an unassociated obra returns empty", async () => {
    const { profile, db } = await createAuthenticatedProfile(adminDb, "obra");
    const own = await createObra(adminDb);
    await linkObraProfile(adminDb, own.id, profile.id);
    const other = await createObra(adminDb);

    const { data: all, error } = await db.from("obras").select("*");
    expect(error).toBeNull();
    expect(all?.map((o) => o.id)).toContain(own.id);
    expect(all?.map((o) => o.id)).not.toContain(other.id);

    const { data: unassociated } = await db.from("obras").select("*").eq("id", other.id);
    expect(unassociated).toEqual([]);
  });

  it("Suprimentos and Gestão see every obra", async () => {
    const obra = await createObra(adminDb);
    const { db: suprimentosDb } = await createAuthenticatedProfile(adminDb, "suprimentos");
    const { db: gestaoDb } = await createAuthenticatedProfile(adminDb, "gestao");

    const { data: forSuprimentos } = await suprimentosDb.from("obras").select("*");
    const { data: forGestao } = await gestaoDb.from("obras").select("*");

    expect(forSuprimentos?.map((o) => o.id)).toContain(obra.id);
    expect(forGestao?.map((o) => o.id)).toContain(obra.id);
  });
});
