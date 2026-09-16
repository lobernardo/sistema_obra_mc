import { redirect } from "next/navigation";
import { createClient } from "@/lib/supabase/server";
import { getCurrentProfile } from "@/lib/auth/session";
import { getRoleHomePath } from "@/lib/auth/roles";
import type { RoleSlug } from "@/lib/types/domain";

export default async function Home() {
  const db = await createClient();
  const profile = await getCurrentProfile(db);

  if (!profile) {
    redirect("/login");
  }

  redirect(getRoleHomePath(profile.role.slug as RoleSlug));
}
