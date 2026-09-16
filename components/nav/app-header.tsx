import type { Profile, RoleSlug } from "@/lib/types/domain";
import { getNavItems } from "./nav-config";
import { NavLinks } from "./nav-links";
import { LogoutButton } from "./logout-button";

export function AppHeader({ profile }: { profile: Profile }) {
  const items = getNavItems(profile.role.slug as RoleSlug);

  return (
    <header className="border-border bg-background sticky top-0 z-10 border-b">
      <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
        <div className="flex flex-wrap items-center gap-4">
          <span className="text-sm font-semibold tracking-tight">Sistema de Solicitações</span>
          <NavLinks items={items} />
        </div>

        <div className="flex items-center gap-3">
          <div className="flex flex-col leading-tight text-right">
            <span className="text-sm font-medium">{profile.full_name}</span>
            <span className="text-muted-foreground text-xs">{profile.role.name}</span>
          </div>
          <LogoutButton />
        </div>
      </div>
    </header>
  );
}
