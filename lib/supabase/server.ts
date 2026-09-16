import "server-only";
import { cookies } from "next/headers";
import { createServerClient } from "@supabase/ssr";
import type { Database } from "@/lib/types/database";
import { getSupabaseAnonKey, getSupabaseUrl } from "./env";

/**
 * Client for use in Server Components, Server Actions and Route Handlers.
 * Reads/writes the session from request cookies, subject to Row Level Security.
 *
 * Server Components can't set cookies, so `setAll` is a no-op there — session
 * refresh in that context relies on middleware. See Supabase SSR docs.
 */
export async function createClient() {
  const cookieStore = await cookies();

  return createServerClient<Database>(getSupabaseUrl(), getSupabaseAnonKey(), {
    cookies: {
      getAll() {
        return cookieStore.getAll();
      },
      setAll(cookiesToSet) {
        try {
          cookiesToSet.forEach(({ name, value, options }) => {
            cookieStore.set(name, value, options);
          });
        } catch {
          // Called from a Server Component — ignored, session refresh
          // happens via middleware instead.
        }
      },
    },
  });
}
