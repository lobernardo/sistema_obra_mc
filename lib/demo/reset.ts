import type { Db } from "@/lib/supabase/types";

/**
 * See the note atop `lib/demo/seed.ts`: this module must stay executable by
 * plain `node` (via `scripts/reset-demo.ts`), so every value import here
 * stays relative/bare — only `import type` may use the `@/...` alias.
 */

export interface ResetResult {
  pedidosDeleted: number;
  pedidoEventsDeleted: number;
  obraProfileDeleted: number;
  profilesDeleted: number;
  obrasDeleted: number;
}

/**
 * Deletes exclusively `is_demo = true` rows from `pedidos`, `profiles` and
 * `obras`, cascading by hand to `pedido_events` and `obra_profile` first
 * (neither FK is `ON DELETE CASCADE`). Real (`is_demo = false`) rows are
 * never touched — nothing here is scoped by anything but the flag itself.
 */
export async function resetDemoData(db: Db): Promise<ResetResult> {
  const pedidoIds = await selectDemoIds(db, "pedidos");

  let pedidoEventsDeleted = 0;
  if (pedidoIds.length > 0) {
    const { data: deletedEvents, error: eventsError } = await db
      .from("pedido_events")
      .delete()
      .in("pedido_id", pedidoIds)
      .select("id");

    if (eventsError) {
      throw new Error(`Falha ao remover pedido_events demo: ${eventsError.message}`);
    }
    pedidoEventsDeleted = deletedEvents?.length ?? 0;

    const { error: pedidosError } = await db.from("pedidos").delete().in("id", pedidoIds);
    if (pedidosError) {
      throw new Error(`Falha ao remover pedidos demo: ${pedidosError.message}`);
    }
  }

  const profileIds = await selectDemoIds(db, "profiles");
  const obraIds = await selectDemoIds(db, "obras");

  let obraProfileDeleted = 0;
  if (profileIds.length > 0) {
    const { data, error } = await db
      .from("obra_profile")
      .delete()
      .in("profile_id", profileIds)
      .select("obra_id");
    if (error) {
      throw new Error(`Falha ao remover obra_profile demo (por profile): ${error.message}`);
    }
    obraProfileDeleted += data?.length ?? 0;
  }
  if (obraIds.length > 0) {
    const { data, error } = await db
      .from("obra_profile")
      .delete()
      .in("obra_id", obraIds)
      .select("obra_id");
    if (error) {
      throw new Error(`Falha ao remover obra_profile demo (por obra): ${error.message}`);
    }
    obraProfileDeleted += data?.length ?? 0;
  }

  for (const profileId of profileIds) {
    // Deletes the auth.users row, which cascades to `profiles` (profiles.id
    // references auth.users(id) on delete cascade) — never delete the
    // `profiles` row directly, or the auth user would be left orphaned.
    const { error } = await db.auth.admin.deleteUser(profileId);
    if (error) {
      throw new Error(`Falha ao remover usuário demo ${profileId}: ${error.message}`);
    }
  }

  let obrasDeleted = 0;
  if (obraIds.length > 0) {
    const { data, error } = await db.from("obras").delete().in("id", obraIds).select("id");
    if (error) {
      throw new Error(`Falha ao remover obras demo: ${error.message}`);
    }
    obrasDeleted = data?.length ?? 0;
  }

  return {
    pedidosDeleted: pedidoIds.length,
    pedidoEventsDeleted,
    obraProfileDeleted,
    profilesDeleted: profileIds.length,
    obrasDeleted,
  };
}

async function selectDemoIds(db: Db, table: "pedidos" | "profiles" | "obras"): Promise<string[]> {
  const { data, error } = await db.from(table).select("id").eq("is_demo", true);
  if (error) {
    throw new Error(`Falha ao buscar linhas demo em ${table}: ${error.message}`);
  }
  return (data ?? []).map((row) => row.id);
}
