import { describe, expect, it } from "vitest";
import type { Profile } from "@/lib/types/domain";
import { resolveRouteAccess } from "./guard";

function fixtureProfile(roleSlug: "obra" | "suprimentos" | "gestao"): Profile {
  const now = new Date().toISOString();
  return {
    id: `profile-${roleSlug}`,
    full_name: `Test ${roleSlug}`,
    role_id: `role-${roleSlug}`,
    is_active: true,
    is_demo: false,
    created_at: now,
    updated_at: now,
    role: {
      id: `role-${roleSlug}`,
      name: roleSlug,
      slug: roleSlug,
      description: null,
      is_active: true,
      created_at: now,
      updated_at: now,
    },
  };
}

describe("resolveRouteAccess", () => {
  it("redirects unauthenticated requests to the login page", () => {
    expect(resolveRouteAccess(null, "suprimentos")).toEqual({ outcome: "redirect", to: "/login" });
  });

  it("allows a profile to access a route matching its own role", () => {
    expect(resolveRouteAccess(fixtureProfile("obra"), "obra")).toEqual({ outcome: "allow" });
  });

  it("blocks an Obra profile from a Suprimentos route, redirecting to its own home", () => {
    expect(resolveRouteAccess(fixtureProfile("obra"), "suprimentos")).toEqual({
      outcome: "redirect",
      to: "/obra",
    });
  });

  it("blocks a Gestão profile from a Suprimentos write action, redirecting to its own home", () => {
    expect(resolveRouteAccess(fixtureProfile("gestao"), "suprimentos")).toEqual({
      outcome: "redirect",
      to: "/gestao",
    });
  });
});
