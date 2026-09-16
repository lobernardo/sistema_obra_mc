import { describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import { usePathname } from "next/navigation";
import { NavLinks } from "./nav-links";
import type { NavItem } from "./nav-config";

vi.mock("next/navigation", () => ({
  usePathname: vi.fn(),
}));

const items: NavItem[] = [
  { label: "Kanban", href: "/suprimentos" },
  { label: "Todos os Pedidos", href: "/suprimentos/pedidos" },
];

describe("NavLinks", () => {
  it("renders every item as a link to its href", () => {
    vi.mocked(usePathname).mockReturnValue("/suprimentos");
    render(<NavLinks items={items} />);

    expect(screen.getByRole("link", { name: "Kanban" })).toHaveAttribute("href", "/suprimentos");
    expect(screen.getByRole("link", { name: "Todos os Pedidos" })).toHaveAttribute(
      "href",
      "/suprimentos/pedidos",
    );
  });

  it("marks the link matching the current pathname as the active page", () => {
    vi.mocked(usePathname).mockReturnValue("/suprimentos/pedidos");
    render(<NavLinks items={items} />);

    expect(screen.getByRole("link", { name: "Todos os Pedidos" })).toHaveAttribute(
      "aria-current",
      "page",
    );
    expect(screen.getByRole("link", { name: "Kanban" })).not.toHaveAttribute("aria-current");
  });
});
