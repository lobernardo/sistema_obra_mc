import { createBrowserClient } from "@supabase/ssr";
import type { Database } from "@/lib/types/database";
import { getSupabaseAnonKey, getSupabaseUrl } from "./env";

/**
 * Client for use in Client Components. Authenticated as the signed-in user
 * (anon key + session), subject to Row Level Security.
 */
export function createClient() {
  return createBrowserClient<Database>(getSupabaseUrl(), getSupabaseAnonKey());
}
