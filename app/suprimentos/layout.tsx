import { redirect } from "next/navigation";
import { createClient } from "@/lib/supabase/server";
import { getCurrentProfile } from "@/lib/auth/session";
import { resolveRouteAccess } from "@/lib/auth/guard";

export default async function SuprimentosLayout({ children }: LayoutProps<"/suprimentos">) {
  const db = await createClient();
  const profile = await getCurrentProfile(db);
  const access = resolveRouteAccess(profile, "suprimentos");

  if (access.outcome === "redirect") {
    redirect(access.to);
  }

  return <>{children}</>;
}
