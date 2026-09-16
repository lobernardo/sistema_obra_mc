import type { SupabaseClient } from "@supabase/supabase-js";
import type { Database } from "@/lib/types/database";

/** Any Supabase client (admin, server or browser) typed against our schema. */
export type Db = SupabaseClient<Database>;
