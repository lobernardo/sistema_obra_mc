import type { Profile, RoleSlug } from "@/lib/types/domain";
import { getRoleHomePath } from "./roles";

export type RouteAccess = { outcome: "allow" } | { outcome: "redirect"; to: string };

/**
 * Decides whether a profile may render a route reserved for `requiredRole`.
 * Pure and framework-free on purpose — the actual `app/<role>/layout.tsx`
 * server components are thin wrappers that call this and then invoke
 * `redirect()` from `next/navigation`, since that API needs a real Next.js
 * request context and can't run inside Vitest.
 */
export function resolveRouteAccess(profile: Profile | null, requiredRole: RoleSlug): RouteAccess {
  if (!profile) {
    return { outcome: "redirect", to: "/login" };
  }

  if (profile.role.slug !== requiredRole) {
    return { outcome: "redirect", to: getRoleHomePath(profile.role.slug as RoleSlug) };
  }

  return { outcome: "allow" };
}
