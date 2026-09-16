import type { Db } from "@/lib/supabase/types";
import type { Profile } from "@/lib/types/domain";
import { UnauthorizedError } from "./errors";

export interface SignInInput {
  email: string;
  password: string;
}

/**
 * Signs in with email/password via Supabase Auth on the given client — in
 * production this is the cookie-bound server client, so a successful call
 * also persists the session. Invalid credentials never establish a session;
 * they surface as `UnauthorizedError` with a message safe to show the user.
 */
export async function signIn(db: Db, credentials: SignInInput): Promise<{ profile: Profile }> {
  if (!credentials.email || !credentials.password) {
    throw new UnauthorizedError("Informe e-mail e senha.");
  }

  const { data, error } = await db.auth.signInWithPassword(credentials);

  if (error || !data.user) {
    throw new UnauthorizedError("E-mail ou senha inválidos.");
  }

  const { data: profile, error: profileError } = await db
    .from("profiles")
    .select("*, role:roles(*)")
    .eq("id", data.user.id)
    .maybeSingle()
    .overrideTypes<Profile, { merge: false }>();

  if (profileError || !profile) {
    await db.auth.signOut();
    throw new UnauthorizedError("Não foi possível carregar o perfil do usuário.");
  }

  return { profile };
}
