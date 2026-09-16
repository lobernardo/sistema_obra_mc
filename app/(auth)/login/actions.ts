"use server";

import { createClient } from "@/lib/supabase/server";
import { signIn } from "@/lib/auth/service";
import { UnauthorizedError } from "@/lib/auth/errors";
import { getRoleHomePath } from "@/lib/auth/roles";
import type { RoleSlug } from "@/lib/types/domain";

export interface LoginState {
  error?: string;
  redirectTo?: string;
}

export async function login(_prevState: LoginState, formData: FormData): Promise<LoginState> {
  const email = String(formData.get("email") ?? "").trim();
  const password = String(formData.get("password") ?? "");

  const db = await createClient();

  try {
    const { profile } = await signIn(db, { email, password });
    return { redirectTo: getRoleHomePath(profile.role.slug as RoleSlug) };
  } catch (err) {
    if (err instanceof UnauthorizedError) {
      return { error: err.message };
    }
    throw err;
  }
}
