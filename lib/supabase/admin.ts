import "server-only";
import { createClient as createSupabaseClient } from "@supabase/supabase-js";
import { getSupabaseServiceRoleKey, getSupabaseUrl } from "./env";

/**
 * Service-role client. Bypasses Row Level Security entirely — restricted to
 * administrative/seed routines that never run in a request triggered by an
 * untrusted user. Never import this from a Client Component or expose it to
 * the browser.
 */
export function createAdminClient() {
  return createSupabaseClient(getSupabaseUrl(), getSupabaseServiceRoleKey(), {
    auth: {
      autoRefreshToken: false,
      persistSession: false,
    },
  });
}
