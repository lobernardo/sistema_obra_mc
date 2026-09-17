"use server";

import { redirect } from "next/navigation";
import { createClient } from "@/lib/supabase/server";
import { signIn } from "@/lib/auth/service";
import { UnauthorizedError } from "@/lib/auth/errors";
import { getRoleHomePath } from "@/lib/auth/roles";
import type { RoleSlug } from "@/lib/types/domain";

export interface LoginState {
  error?: string;
}

export async function login(_prevState: LoginState, formData: FormData): Promise<LoginState> {
  const email = String(formData.get("email") ?? "").trim();
  const password = String(formData.get("password") ?? "");

  const db = await createClient();

  try {
    const { profile } = await signIn(db, { email, password });
    // `redirect()` is the redirect primitive for Server Actions (see
    // node_modules/next/dist/docs/01-app/02-guides/server-actions.md — "A
    // single response carries data and UI") — it streams the destination's
    // RSC Payload in the same round trip. A client-side `router.push` driven
    // off a returned `redirectTo` string never fires reliably here: setting
    // the session cookie above already forces Next to seed a re-render of
    // *this* route in the same response, which races the client effect.
    redirect(getRoleHomePath(profile.role.slug as RoleSlug));
  } catch (err) {
    if (err instanceof UnauthorizedError) {
      return { error: err.message };
    }
    throw err;
  }
}
