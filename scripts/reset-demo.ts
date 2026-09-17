import nextEnv from "@next/env";

nextEnv.loadEnvConfig(process.cwd());

import { createClient } from "@supabase/supabase-js";
import type { Database } from "../lib/types/database.ts";
import { getSupabaseServiceRoleKey, getSupabaseUrl } from "../lib/supabase/env.ts";
import { resetDemoData } from "../lib/demo/reset.ts";

/**
 * Administrative reset routine (US-9.1) — removes exclusively `is_demo =
 * true` rows, never touching real data. Run with `npm run reset:demo`.
 */
async function main() {
  const db = createClient<Database>(getSupabaseUrl(), getSupabaseServiceRoleKey(), {
    auth: { autoRefreshToken: false, persistSession: false },
  });

  const result = await resetDemoData(db);

  console.log(
    `Reset de demonstração concluído: ${result.pedidosDeleted} pedidos, ${result.pedidoEventsDeleted} pedido_events, ${result.obraProfileDeleted} obra_profile, ${result.profilesDeleted} perfis e ${result.obrasDeleted} obras removidos.`,
  );
}

main().catch((error: unknown) => {
  console.error("Falha ao executar o reset de demonstração:", error);
  process.exitCode = 1;
});
