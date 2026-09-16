import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { PriorityBadge } from "./priority-badge";
import type { PrioritySlug } from "@/lib/types/domain";

const PRIORITIES: { slug: PrioritySlug; name: string }[] = [
  { slug: "baixa", name: "Baixa" },
  { slug: "normal", name: "Normal" },
  { slug: "alta", name: "Alta" },
  { slug: "urgente", name: "Urgente" },
];

describe("PriorityBadge", () => {
  it.each(PRIORITIES)("renders the label for $slug", ({ slug, name }) => {
    render(<PriorityBadge priority={{ slug, name }} />);
    expect(screen.getByText(name)).toBeInTheDocument();
  });

  it("gives every level its own, non-overlapping color class", () => {
    const backgroundClasses = PRIORITIES.map(({ slug, name }) => {
      const { getByText, unmount } = render(<PriorityBadge priority={{ slug, name }} />);
      const bg = getByText(name)
        .className.split(" ")
        .find((c) => c.startsWith("bg-"));
      unmount();
      return bg;
    });

    expect(new Set(backgroundClasses).size).toBe(PRIORITIES.length);
  });

  it("renders a neutral placeholder when the pedido has no priority set", () => {
    render(<PriorityBadge priority={null} />);
    expect(screen.getByText("Sem prioridade")).toBeInTheDocument();
  });
});
