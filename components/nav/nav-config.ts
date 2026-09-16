import type { RoleSlug } from "@/lib/types/domain";

export interface NavItem {
  label: string;
  href: string;
}

/** Which nav items each role sees — scoped exactly to what that profile may do. */
const NAV_ITEMS: Record<RoleSlug, NavItem[]> = {
  obra: [
    { label: "Nova Solicitação", href: "/obra/novo" },
    { label: "Meus Pedidos", href: "/obra" },
  ],
  suprimentos: [
    { label: "Kanban", href: "/suprimentos" },
    { label: "Todos os Pedidos", href: "/suprimentos/pedidos" },
  ],
  gestao: [
    { label: "Dashboard", href: "/gestao" },
    { label: "Kanban", href: "/gestao/kanban" },
    { label: "Todos os Pedidos", href: "/gestao/pedidos" },
  ],
};

export function getNavItems(roleSlug: RoleSlug): NavItem[] {
  return NAV_ITEMS[roleSlug];
}
