import { createClient } from "@supabase/supabase-js";
import type { Db } from "@/lib/supabase/types";
import { getSupabaseAnonKey, getSupabaseServiceRoleKey, getSupabaseUrl } from "@/lib/supabase/env";
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
 * Test-only fixture helpers for the domain layer. Builds a service-role
 * client directly (bypassing `lib/supabase/admin.ts`, which is guarded by
 * `server-only` and can't be imported from Vitest) against the local
 * Supabase stack started with `supabase start`.
 */
export function createTestDb(): Db {
  return createClient<Database>(getSupabaseUrl(), getSupabaseServiceRoleKey(), {
    auth: { autoRefreshToken: false, persistSession: false },
  });
}

/**
 * A fresh anon-key client with no session — the same kind of client a real
 * request would get from `lib/supabase/server.ts`/`lib/supabase/client.ts`,
 * subject to Row Level Security once signed in. Never reuse the shared
 * `createTestDb()` admin client for sign-in: `signInWithPassword` swaps its
 * session in place, which would break later `auth.admin.*` calls on it.
 */
export function createAnonDb(): Db {
  return createClient<Database>(getSupabaseUrl(), getSupabaseAnonKey(), {
    auth: { autoRefreshToken: false, persistSession: false },
  });
}

const TEST_PASSWORD = "test-password-123";

let sequence = 0;

/**
 * Test files run as separate module instances (often in parallel worker
 * processes), so a per-module `sequence` counter and `Date.now()` alone can
 * collide across files — two files' first fixture call can produce the same
 * millisecond + sequence=1, yielding a duplicate `auth.users.email` and a
 * "Database error creating new user" from GoTrue. `crypto.randomUUID()` is
 * unique across processes, so mix it in.
 */
function unique(prefix: string): string {
  sequence += 1;
  return `${prefix}-${Date.now()}-${sequence}-${crypto.randomUUID()}`;
}

/**
 * Creates a real `auth.users` row with `role_slug`/`full_name` metadata; the
 * `handle_new_user` trigger (Phase 4.1) provisions the matching `profiles`
 * row automatically. Returns the credentials too, so callers can sign in as
 * this profile to get an RLS-scoped client (see `createAuthenticatedProfile`).
 */
export async function createProfileWithCredentials(
  db: Db,
  roleSlug: RoleSlug,
  overrides: { fullName?: string } = {},
): Promise<{ profile: Profile; email: string; password: string }> {
  const email = `${unique("user")}@test.local`;
  const fullName = overrides.fullName ?? `Test ${roleSlug} ${sequence}`;

  const { data: authUser, error: authError } = await db.auth.admin.createUser({
    email,
    password: TEST_PASSWORD,
    email_confirm: true,
    user_metadata: { role_slug: roleSlug, full_name: fullName },
  });

  if (authError || !authUser.user) {
    throw new Error(`Fixture setup failed: could not create auth user (${authError?.message}).`);
  }

  const { data: profile, error: profileError } = await db
    .from("profiles")
    .select("*, role:roles(*)")
    .eq("id", authUser.user.id)
    .single()
    .overrideTypes<Profile, { merge: false }>();

  if (profileError || !profile) {
    throw new Error(
      `Fixture setup failed: provisioned profile not found (${profileError?.message}).`,
    );
  }

  return { profile, email, password: TEST_PASSWORD };
}

/** Convenience wrapper over `createProfileWithCredentials` for callers that don't need the session. */
export async function createProfile(
  db: Db,
  roleSlug: RoleSlug,
  overrides: { fullName?: string } = {},
): Promise<Profile> {
  const { profile } = await createProfileWithCredentials(db, roleSlug, overrides);
  return profile;
}

/** Signs in as a fixture profile and returns the resulting RLS-scoped client. */
export async function signInAsTestUser(email: string, password: string): Promise<Db> {
  const db = createAnonDb();
  const { error } = await db.auth.signInWithPassword({ email, password });

  if (error) {
    throw new Error(`Fixture setup failed: could not sign in test user (${error.message}).`);
  }

  return db;
}

/** Creates a fixture profile and signs in as it, for exercising Row Level Security. */
export async function createAuthenticatedProfile(
  db: Db,
  roleSlug: RoleSlug,
  overrides: { fullName?: string } = {},
): Promise<{ profile: Profile; db: Db }> {
  const { profile, email, password } = await createProfileWithCredentials(db, roleSlug, overrides);
  const userDb = await signInAsTestUser(email, password);
  return { profile, db: userDb };
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
