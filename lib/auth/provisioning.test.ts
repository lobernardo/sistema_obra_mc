import { describe, expect, it } from "vitest";
import { createProfileWithCredentials, createTestDb } from "@/lib/pedidos/testing";

describe("profile provisioning", () => {
  const db = createTestDb();

  it("creates exactly one profiles row with the expected role_id when an auth user is created", async () => {
    const { data: role, error: roleError } = await db
      .from("roles")
      .select("*")
      .eq("slug", "suprimentos")
      .single();
    if (roleError || !role) throw new Error(`Fixture setup failed: ${roleError?.message}`);

    const { profile } = await createProfileWithCredentials(db, "suprimentos");

    expect(profile.role_id).toBe(role.id);

    const { data: rows, error } = await db.from("profiles").select("id").eq("id", profile.id);
    expect(error).toBeNull();
    expect(rows).toHaveLength(1);
  });
});
