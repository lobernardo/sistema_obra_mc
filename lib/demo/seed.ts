import type { Db } from "@/lib/supabase/types";
import type {
  Obra,
  Pedido,
  Priority,
  PrioritySlug,
  Profile,
  Status,
  StatusSlug,
} from "@/lib/types/domain";
import {
  cancelPedido,
  createPedido,
  updatePedidoPrevisao,
  updatePedidoPrioridade,
  updatePedidoResponsavel,
  updatePedidoStatus,
} from "../pedidos/service.ts";
import {
  DEMO_OBRA_PROFILE_LINKS,
  DEMO_OBRAS,
  DEMO_PASSWORD,
  DEMO_PEDIDOS,
  type DemoObraProfileLinkSpec,
  type DemoObraSpec,
  type DemoPedidoSpec,
  type DemoUserSpec,
  DEMO_USERS,
} from "./data.ts";

/**
 * `lib/demo/{seed,reset}.ts` are imported two ways: by Vitest (feature
 * tests, via the `@/...` alias) and directly by `scripts/seed-demo.ts` under
 * plain `node` (no bundler, no tsconfig paths). Only `import type` from
 * `@/...` is safe there — it's erased before Node ever resolves the
 * specifier. Every *value* import in this file must stay relative or a bare
 * package name so `node scripts/seed-demo.ts` can run it directly.
 */

export interface SeedResult {
  obras: Obra[];
  profiles: Profile[];
  pedidos: Pedido[];
}

interface SeedContext {
  obras: Record<string, Obra>;
  profiles: Record<string, Profile>;
  statusesBySlug: Record<StatusSlug, Status>;
  prioritiesBySlug: Record<PrioritySlug, Priority>;
}

const DEFAULT_TRIAGE_ACTOR_KEY = "suprimentos1";

/**
 * Populates a realistic, clearly-marked demo dataset: multiple obras, one
 * demo user per role (including an Obra profile linked to more than one
 * obra), and pedidos spanning every status/priority with at least one
 * atrasado and one entregue. Matched on fixed emails/names/tags, so
 * re-running never duplicates rows.
 */
export async function seedDemoData(db: Db): Promise<SeedResult> {
  const obras: Record<string, Obra> = {};
  for (const spec of DEMO_OBRAS) {
    obras[spec.key] = await getOrCreateDemoObra(db, spec);
  }

  const profiles: Record<string, Profile> = {};
  for (const spec of DEMO_USERS) {
    profiles[spec.key] = await getOrCreateDemoUser(db, spec);
  }

  for (const link of DEMO_OBRA_PROFILE_LINKS) {
    await linkObraProfile(db, obras, profiles, link);
  }

  const context: SeedContext = {
    obras,
    profiles,
    statusesBySlug: await loadStatusesBySlug(db),
    prioritiesBySlug: await loadPrioritiesBySlug(db),
  };

  const pedidos: Pedido[] = [];
  for (const spec of DEMO_PEDIDOS) {
    pedidos.push(await getOrCreateDemoPedido(db, spec, context));
  }

  return { obras: Object.values(obras), profiles: Object.values(profiles), pedidos };
}

async function getOrCreateDemoObra(db: Db, spec: DemoObraSpec): Promise<Obra> {
  const { data: existing, error: findError } = await db
    .from("obras")
    .select("*")
    .eq("name", spec.name)
    .eq("is_demo", true)
    .maybeSingle();

  if (findError) {
    throw new Error(`Falha ao buscar obra demo "${spec.name}": ${findError.message}`);
  }
  if (existing) {
    return existing;
  }

  const { data: created, error: createError } = await db
    .from("obras")
    .insert({ name: spec.name, is_demo: true })
    .select()
    .single();

  if (createError || !created) {
    throw new Error(`Falha ao criar obra demo "${spec.name}": ${createError?.message}`);
  }

  return created;
}

async function getOrCreateDemoUser(db: Db, spec: DemoUserSpec): Promise<Profile> {
  const { data: created, error: createError } = await db.auth.admin.createUser({
    email: spec.email,
    password: DEMO_PASSWORD,
    email_confirm: true,
    user_metadata: { role_slug: spec.roleSlug, full_name: spec.fullName },
  });

  let userId = created?.user?.id;

  if (createError || !userId) {
    const existing = await findAuthUserByEmail(db, spec.email);
    if (!existing) {
      throw new Error(`Falha ao provisionar usuário demo "${spec.email}": ${createError?.message}`);
    }
    userId = existing.id;
  }

  const { data: profile, error: profileError } = await db
    .from("profiles")
    .update({ is_demo: true, full_name: spec.fullName })
    .eq("id", userId)
    .select("*, role:roles(*)")
    .single()
    .overrideTypes<Profile, { merge: false }>();

  if (profileError || !profile) {
    throw new Error(`Falha ao marcar perfil demo "${spec.email}": ${profileError?.message}`);
  }

  return profile;
}

async function findAuthUserByEmail(db: Db, email: string): Promise<{ id: string } | null> {
  const perPage = 200;
  for (let page = 1; page <= 10; page += 1) {
    const { data, error } = await db.auth.admin.listUsers({ page, perPage });
    if (error) {
      throw new Error(`Falha ao listar usuários: ${error.message}`);
    }

    const found = data.users.find((user) => user.email === email);
    if (found) {
      return found;
    }
    if (data.users.length < perPage) {
      return null;
    }
  }

  return null;
}

async function linkObraProfile(
  db: Db,
  obras: Record<string, Obra>,
  profiles: Record<string, Profile>,
  link: DemoObraProfileLinkSpec,
): Promise<void> {
  const { error } = await db
    .from("obra_profile")
    .upsert(
      { obra_id: obras[link.obraKey].id, profile_id: profiles[link.profileKey].id },
      { onConflict: "obra_id,profile_id", ignoreDuplicates: true },
    );

  if (error) {
    throw new Error(
      `Falha ao vincular obra_profile demo (${link.obraKey}/${link.profileKey}): ${error.message}`,
    );
  }
}

async function loadStatusesBySlug(db: Db): Promise<Record<StatusSlug, Status>> {
  const { data, error } = await db.from("statuses").select("*");
  if (error) {
    throw new Error(`Falha ao carregar statuses: ${error.message}`);
  }

  const map = {} as Record<StatusSlug, Status>;
  for (const row of data ?? []) {
    map[row.slug as StatusSlug] = row;
  }
  return map;
}

async function loadPrioritiesBySlug(db: Db): Promise<Record<PrioritySlug, Priority>> {
  const { data, error } = await db.from("priorities").select("*");
  if (error) {
    throw new Error(`Falha ao carregar priorities: ${error.message}`);
  }

  const map = {} as Record<PrioritySlug, Priority>;
  for (const row of data ?? []) {
    map[row.slug as PrioritySlug] = row;
  }
  return map;
}

async function getOrCreateDemoPedido(
  db: Db,
  spec: DemoPedidoSpec,
  context: SeedContext,
): Promise<Pedido> {
  const { data: existing, error: findError } = await db
    .from("pedidos")
    .select("*")
    .eq("is_demo", true)
    .ilike("items_description", `${spec.tag}%`)
    .maybeSingle();

  if (findError) {
    throw new Error(`Falha ao buscar pedido demo "${spec.tag}": ${findError.message}`);
  }
  if (existing) {
    return existing;
  }

  const obra = context.obras[spec.obraKey];
  const requester = context.profiles[spec.requesterKey];

  const created = await createPedido(
    db,
    {
      obra_id: obra.id,
      needed_at: isoDateOffset(spec.neededAtOffsetDays),
      items_description: `${spec.tag} ${spec.itemsDescription}`,
    },
    requester,
  );

  const { error: flagError } = await db
    .from("pedidos")
    .update({ is_demo: true })
    .eq("id", created.id);
  if (flagError) {
    throw new Error(`Falha ao marcar pedido demo "${spec.tag}" como is_demo: ${flagError.message}`);
  }

  await applyDemoPedidoState(db, created.id, spec, context);

  const { data: finalPedido, error: finalError } = await db
    .from("pedidos")
    .select("*")
    .eq("id", created.id)
    .single();

  if (finalError || !finalPedido) {
    throw new Error(`Falha ao recarregar pedido demo "${spec.tag}": ${finalError?.message}`);
  }

  return finalPedido;
}

async function applyDemoPedidoState(
  db: Db,
  pedidoId: string,
  spec: DemoPedidoSpec,
  context: SeedContext,
): Promise<void> {
  const isNoOp =
    spec.targetStatusSlug === "solicitado" &&
    !spec.prioritySlug &&
    !spec.responsibleKey &&
    spec.expectedDeliveryOffsetDays == null;

  if (isNoOp) {
    return;
  }

  const actor = context.profiles[spec.responsibleKey ?? DEFAULT_TRIAGE_ACTOR_KEY];

  if (spec.prioritySlug) {
    await updatePedidoPrioridade(
      db,
      pedidoId,
      context.prioritiesBySlug[spec.prioritySlug].id,
      actor,
    );
  }
  if (spec.responsibleKey) {
    await updatePedidoResponsavel(db, pedidoId, context.profiles[spec.responsibleKey].id, actor);
  }
  if (spec.expectedDeliveryOffsetDays != null) {
    await updatePedidoPrevisao(db, pedidoId, isoDateOffset(spec.expectedDeliveryOffsetDays), actor);
  }

  if (spec.targetStatusSlug === "cancelado") {
    await cancelPedido(db, pedidoId, actor);
  } else if (spec.targetStatusSlug !== "solicitado") {
    await updatePedidoStatus(db, pedidoId, context.statusesBySlug[spec.targetStatusSlug].id, actor);
  }
}

function isoDateOffset(days: number): string {
  const date = new Date();
  date.setUTCDate(date.getUTCDate() + days);
  return date.toISOString().slice(0, 10);
}
