import type { RoleSlug } from "@/lib/types/domain";

/** Where each role lands after login — its area's entry point. */
export const ROLE_HOME_PATH: Record<RoleSlug, string> = {
  obra: "/obra",
  suprimentos: "/suprimentos",
  gestao: "/gestao",
};

export function getRoleHomePath(roleSlug: RoleSlug): string {
  return ROLE_HOME_PATH[roleSlug];
}
