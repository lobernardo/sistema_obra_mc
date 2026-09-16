import { redirect } from "next/navigation";
import { createClient } from "@/lib/supabase/server";
import { getCurrentProfile } from "@/lib/auth/session";
import { resolveRouteAccess } from "@/lib/auth/guard";

export default async function GestaoLayout({ children }: LayoutProps<"/gestao">) {
  const db = await createClient();
  const profile = await getCurrentProfile(db);
  const access = resolveRouteAccess(profile, "gestao");

  if (access.outcome === "redirect") {
    redirect(access.to);
  }

  return <>{children}</>;
}
