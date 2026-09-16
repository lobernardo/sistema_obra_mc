import { redirect } from "next/navigation";
import { createClient } from "@/lib/supabase/server";
import { getCurrentProfile } from "@/lib/auth/session";
import { resolveRouteAccess } from "@/lib/auth/guard";

export default async function ObraLayout({ children }: LayoutProps<"/obra">) {
  const db = await createClient();
  const profile = await getCurrentProfile(db);
  const access = resolveRouteAccess(profile, "obra");

  if (access.outcome === "redirect") {
    redirect(access.to);
  }

  return <>{children}</>;
}
