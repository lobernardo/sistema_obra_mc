import type { ReactNode } from "react";
import type { Profile } from "@/lib/types/domain";
import { AppHeader } from "@/components/nav/app-header";

/** Shared shell (header, nav, logout) for every authenticated route. */
export function AppShell({ profile, children }: { profile: Profile; children: ReactNode }) {
  return (
    <div className="flex min-h-full flex-1 flex-col">
      <AppHeader profile={profile} />
      <main className="flex flex-1 flex-col">{children}</main>
    </div>
  );
}
