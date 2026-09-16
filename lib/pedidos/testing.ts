import { createClient } from "@supabase/supabase-js";
import type { Db } from "@/lib/supabase/types";
import { getSupabaseServiceRoleKey, getSupabaseUrl } from "@/lib/supabase/env";
import type { Database } from "@/lib/types/database";
import type {
  Obra,
  Priority,
  PrioritySlug,
  Profile,
  RoleSlug,
  Status,
  StatusSlug,
} from "@/lib/types/domain";

/**
 * Test-only fixture helpers for the pedidos domain layer. Builds a
 * service-role client directly (bypassing `lib/supabase/admin.ts`, which is
 * guarded by `server-only` and can't be imported from Vitest) against the
 * local Supabase stack started with `supabase start`.
 */
export function createTestDb(): Db {
  return createClient<Database>(getSupabaseUrl(), getSupabaseServiceRoleKey(), {
    auth: { autoRefreshToken: false, persistSession: false },
  });
}

let sequence = 0;

function unique(prefix: string): string {
  sequence += 1;
  return `${prefix}-${Date.now()}-${sequence}`;
}

/** Creates a real `auth.users` row plus its matching `profiles` row. */
export async function createProfile(
  db: Db,
  roleSlug: RoleSlug,
  overrides: { fullName?: string } = {},
): Promise<Profile> {
  const { data: role, error: roleError } = await db
    .from("roles")
    .select("*")
    .eq("slug", roleSlug)
    .single();

  if (roleError || !role) {
    throw new Error(`Fixture setup failed: role "${roleSlug}" not found (${roleError?.message}).`);
  }

  const email = `${unique("user")}@test.local`;
  const { data: authUser, error: authError } = await db.auth.admin.createUser({
    email,
    password: "test-password-123",
    email_confirm: true,
  });

  if (authError || !authUser.user) {
    throw new Error(`Fixture setup failed: could not create auth user (${authError?.message}).`);
  }

  const { data: profile, error: profileError } = await db
    .from("profiles")
    .insert({
      id: authUser.user.id,
      full_name: overrides.fullName ?? `Test ${roleSlug} ${sequence}`,
      role_id: role.id,
    })
    .select()
    .single();

  if (profileError || !profile) {
    throw new Error(`Fixture setup failed: could not create profile (${profileError?.message}).`);
  }

  return { ...profile, role };
}

export async function createObra(db: Db, name: string = unique("Obra")): Promise<Obra> {
  const { data, error } = await db.from("obras").insert({ name }).select().single();

  if (error || !data) {
    throw new Error(`Fixture setup failed: could not create obra (${error?.message}).`);
  }

  return data;
}

export async function linkObraProfile(db: Db, obraId: string, profileId: string): Promise<void> {
  const { error } = await db
    .from("obra_profile")
    .insert({ obra_id: obraId, profile_id: profileId });

  if (error) {
    throw new Error(`Fixture setup failed: could not link obra_profile (${error.message}).`);
  }
}

export async function getStatusBySlug(db: Db, slug: StatusSlug): Promise<Status> {
  const { data, error } = await db.from("statuses").select("*").eq("slug", slug).single();

  if (error || !data) {
    throw new Error(`Fixture setup failed: status "${slug}" not found (${error?.message}).`);
  }

  return data;
}

export async function getPriorityBySlug(db: Db, slug: PrioritySlug): Promise<Priority> {
  const { data, error } = await db.from("priorities").select("*").eq("slug", slug).single();

  if (error || !data) {
    throw new Error(`Fixture setup failed: priority "${slug}" not found (${error?.message}).`);
  }

  return data;
}

export async function getEventsForPedido(
  db: Db,
  pedidoId: string,
): Promise<{ slug: string; previous_value: string | null; new_value: string | null }[]> {
  const { data, error } = await db
    .from("pedido_events")
    .select("previous_value, new_value, eventType:event_types(slug)")
    .eq("pedido_id", pedidoId)
    .order("created_at", { ascending: true })
    .overrideTypes<
      { previous_value: string | null; new_value: string | null; eventType: { slug: string } }[],
      { merge: false }
    >();

  if (error) {
    throw new Error(`Fixture check failed: could not fetch pedido events (${error.message}).`);
  }

  return (data ?? []).map((event) => ({
    slug: event.eventType.slug,
    previous_value: event.previous_value,
    new_value: event.new_value,
  }));
}

/** Convenience: an Obra profile already linked to a fresh obra it can use. */
export async function createObraProfileWithObra(db: Db): Promise<{ profile: Profile; obra: Obra }> {
  const [profile, obra] = await Promise.all([createProfile(db, "obra"), createObra(db)]);
  await linkObraProfile(db, obra.id, profile.id);
  return { profile, obra };
}
