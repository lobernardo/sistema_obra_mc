import nextEnv from "@next/env";

nextEnv.loadEnvConfig(process.cwd());

import { createClient } from "@supabase/supabase-js";
import type { Database } from "../lib/types/database.ts";
import { getSupabaseServiceRoleKey, getSupabaseUrl } from "../lib/supabase/env.ts";
import { seedDemoData } from "../lib/demo/seed.ts";

/**
 * Administrative seed routine (US-9.1) — populates a realistic, clearly
 * demo-marked dataset. Run with `npm run seed:demo`. Idempotent: safe to
 * re-run against an environment that was already seeded.
 */
async function main() {
  const db = createClient<Database>(getSupabaseUrl(), getSupabaseServiceRoleKey(), {
    auth: { autoRefreshToken: false, persistSession: false },
  });

  const result = await seedDemoData(db);

  console.log(
    `Seed de demonstração concluído: ${result.obras.length} obras, ${result.profiles.length} perfis, ${result.pedidos.length} pedidos (todos is_demo=true).`,
  );
}

main().catch((error: unknown) => {
  console.error("Falha ao executar o seed de demonstração:", error);
  process.exitCode = 1;
});
