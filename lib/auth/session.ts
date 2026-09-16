import type { Db } from "@/lib/supabase/types";
import type { Profile } from "@/lib/types/domain";

/**
 * Resolves the currently authenticated profile, or `null` when there's no
 * session. Uses `auth.getUser()` (which revalidates the token against the
 * Auth server) rather than `getSession()` (which only decodes the cookie),
 * per Supabase's guidance for trusting a user's identity server-side.
 */
export async function getCurrentProfile(db: Db): Promise<Profile | null> {
  const {
    data: { user },
  } = await db.auth.getUser();

  if (!user) {
    return null;
  }

  const { data: profile, error } = await db
    .from("profiles")
    .select("*, role:roles(*)")
    .eq("id", user.id)
    .maybeSingle()
    .overrideTypes<Profile, { merge: false }>();

  if (error) {
    throw new Error(`Failed to load current profile: ${error.message}`);
  }

  return profile;
}
