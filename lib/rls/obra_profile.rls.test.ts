import { describe, expect, it } from "vitest";
import {
  createAuthenticatedProfile,
  createObra,
  createTestDb,
  linkObraProfile,
} from "@/lib/pedidos/testing";

describe("RLS: obra_profile", () => {
  const adminDb = createTestDb();

  it("Obra reads only its own associations", async () => {
    const { profile, db } = await createAuthenticatedProfile(adminDb, "obra");
    const obra = await createObra(adminDb);
    await linkObraProfile(adminDb, obra.id, profile.id);

    const { profile: otherProfile } = await createAuthenticatedProfile(adminDb, "obra");
    const otherObra = await createObra(adminDb);
    await linkObraProfile(adminDb, otherObra.id, otherProfile.id);

    const { data } = await db.from("obra_profile").select("*");

    expect(data?.every((row) => row.profile_id === profile.id)).toBe(true);
    expect(data?.map((row) => row.obra_id)).toContain(obra.id);
  });

  it("rejects a direct INSERT into obra_profile from an authenticated client", async () => {
    const { profile, db } = await createAuthenticatedProfile(adminDb, "obra");
    const obra = await createObra(adminDb);

    const { error } = await db
      .from("obra_profile")
      .insert({ obra_id: obra.id, profile_id: profile.id });

    expect(error).not.toBeNull();
  });
});
