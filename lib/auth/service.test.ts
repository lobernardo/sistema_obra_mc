import { describe, expect, it } from "vitest";
import { createAnonDb, createProfileWithCredentials, createTestDb } from "@/lib/pedidos/testing";
import { UnauthorizedError } from "./errors";
import { signIn, signOut } from "./service";

describe("signIn", () => {
  const adminDb = createTestDb();

  it("establishes a session and resolves the profile for valid credentials", async () => {
    const { profile, email, password } = await createProfileWithCredentials(adminDb, "obra");

    const sessionDb = createAnonDb();
    const result = await signIn(sessionDb, { email, password });

    expect(result.profile.id).toBe(profile.id);
    expect(result.profile.role.slug).toBe("obra");

    const {
      data: { session },
    } = await sessionDb.auth.getSession();
    expect(session).not.toBeNull();
  });

  it("rejects invalid credentials without establishing a session", async () => {
    const { email } = await createProfileWithCredentials(adminDb, "suprimentos");

    const sessionDb = createAnonDb();
    await expect(signIn(sessionDb, { email, password: "wrong-password" })).rejects.toThrow(
      UnauthorizedError,
    );

    const {
      data: { session },
    } = await sessionDb.auth.getSession();
    expect(session).toBeNull();
  });

  it("rejects a missing email or password", async () => {
    const sessionDb = createAnonDb();
    await expect(signIn(sessionDb, { email: "", password: "x" })).rejects.toThrow(
      UnauthorizedError,
    );
    await expect(signIn(sessionDb, { email: "a@test.local", password: "" })).rejects.toThrow(
      UnauthorizedError,
    );
  });
});

describe("signOut", () => {
  const adminDb = createTestDb();

  it("ends the session on the given client", async () => {
    const { email, password } = await createProfileWithCredentials(adminDb, "obra");

    const sessionDb = createAnonDb();
    await signIn(sessionDb, { email, password });

    await signOut(sessionDb);

    const {
      data: { session },
    } = await sessionDb.auth.getSession();
    expect(session).toBeNull();
  });
});
