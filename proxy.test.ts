import { createServerClient } from "@supabase/ssr";
import { NextRequest } from "next/server";
import { describe, expect, it } from "vitest";
import { getSupabaseAnonKey, getSupabaseUrl } from "@/lib/supabase/env";
import { createProfileWithCredentials, createTestDb } from "@/lib/pedidos/testing";
import { proxy } from "./proxy";

function requestFor(path: string, cookieHeader?: string): NextRequest {
  return new NextRequest(new URL(path, "http://localhost:3000"), {
    headers: cookieHeader ? { cookie: cookieHeader } : undefined,
  });
}

/**
 * Signs in through the exact cookie interface `proxy.ts`/`lib/supabase/server.ts`
 * use, capturing whatever cookies `@supabase/ssr` sets — rather than guessing
 * their name/shape — and serializes them into a `Cookie` header.
 */
async function sessionCookieHeader(email: string, password: string): Promise<string> {
  const jar = new Map<string, string>();

  const supabase = createServerClient(getSupabaseUrl(), getSupabaseAnonKey(), {
    cookies: {
      getAll() {
        return Array.from(jar.entries()).map(([name, value]) => ({ name, value }));
      },
      setAll(cookiesToSet) {
        cookiesToSet.forEach(({ name, value }) => jar.set(name, value));
      },
    },
  });

  const { error } = await supabase.auth.signInWithPassword({ email, password });
  if (error) {
    throw new Error(`Fixture setup failed: could not sign in test user (${error.message}).`);
  }

  return Array.from(jar.entries())
    .map(([name, value]) => `${name}=${value}`)
    .join("; ");
}

describe("proxy", () => {
  it("redirects an unauthenticated request to a protected route to /login", async () => {
    const response = await proxy(requestFor("/obra"));

    expect(response.status).toBe(307);
    expect(new URL(response.headers.get("location")!).pathname).toBe("/login");
  });

  it("does not redirect an unauthenticated request to the login page itself", async () => {
    const response = await proxy(requestFor("/login"));

    expect(response.headers.get("location")).toBeNull();
  });

  it("does not redirect an authenticated request", async () => {
    const adminDb = createTestDb();
    const { email, password } = await createProfileWithCredentials(adminDb, "obra");

    const cookieHeader = await sessionCookieHeader(email, password);
    const response = await proxy(requestFor("/obra", cookieHeader));

    expect(response.headers.get("location")).toBeNull();
  });
});
