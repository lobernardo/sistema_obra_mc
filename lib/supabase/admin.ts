import "server-only";
import { createClient as createSupabaseClient } from "@supabase/supabase-js";
import type { Database } from "@/lib/types/database";
import { getSupabaseServiceRoleKey, getSupabaseUrl } from "./env";

/**
 * Service-role client. Bypasses Row Level Security entirely — restricted to
 * administrative/seed routines that never run in a request triggered by an
 * untrusted user. Never import this from a Client Component or expose it to
 * the browser.
 */
export function createAdminClient() {
  return createSupabaseClient<Database>(getSupabaseUrl(), getSupabaseServiceRoleKey(), {
    auth: {
      autoRefreshToken: false,
      persistSession: false,
    },
  });
}
