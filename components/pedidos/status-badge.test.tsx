import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { StatusBadge } from "./status-badge";
import type { StatusSlug } from "@/lib/types/domain";

const STATUSES: { slug: StatusSlug; name: string }[] = [
  { slug: "solicitado", name: "Solicitado" },
  { slug: "em_analise", name: "Em análise" },
  { slug: "em_compra_preparacao", name: "Em compra/preparação" },
  { slug: "aguardando_entrega", name: "Aguardando entrega" },
  { slug: "entregue", name: "Entregue" },
  { slug: "cancelado", name: "Cancelado" },
];

describe("StatusBadge", () => {
  it.each(STATUSES)("renders the label for $slug", ({ slug, name }) => {
    render(<StatusBadge status={{ slug, name }} />);
    expect(screen.getByText(name)).toBeInTheDocument();
  });

  it("gives cancelado a visually distinct (struck-through, neutral) treatment", () => {
    render(<StatusBadge status={{ slug: "cancelado", name: "Cancelado" }} />);
    const badge = screen.getByText("Cancelado");
    expect(badge.className).toContain("line-through");
  });

  it("gives every active status its own, non-overlapping color class", () => {
    const activeSlugs = STATUSES.filter((s) => s.slug !== "cancelado");
    const backgroundClasses = activeSlugs.map(({ slug, name }) => {
      const { getByText, unmount } = render(<StatusBadge status={{ slug, name }} />);
      const bg = getByText(name)
        .className.split(" ")
        .find((c) => c.startsWith("bg-"));
      unmount();
      return bg;
    });

    expect(new Set(backgroundClasses).size).toBe(activeSlugs.length);
  });
});
