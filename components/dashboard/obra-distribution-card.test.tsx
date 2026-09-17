import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { ObraDistributionCard } from "./obra-distribution-card";
import type { ObraCount } from "@/lib/pedidos/dashboard";

const now = "2026-01-01T10:00:00Z";

function fixtureObraCount(id: string, name: string, count: number): ObraCount {
  return {
    obra: { id, name, is_active: true, is_demo: false, created_at: now, updated_at: now },
    count,
  };
}

describe("ObraDistributionCard", () => {
  it("shows every obra that has at least one pedido, with its count", () => {
    const porObra = [fixtureObraCount("obra-1", "Obra Central", 5), fixtureObraCount("obra-2", "Obra Norte", 2)];

    render(<ObraDistributionCard porObra={porObra} />);

    expect(screen.getByText("Obra Central")).toBeInTheDocument();
    expect(screen.getByText("5")).toBeInTheDocument();
    expect(screen.getByText("Obra Norte")).toBeInTheDocument();
    expect(screen.getByText("2")).toBeInTheDocument();
  });

  it("omits obras with zero pedidos in scope, so a narrow filter doesn't bury the one row that matters", () => {
    const porObra = [
      fixtureObraCount("obra-1", "Obra Central", 3),
      fixtureObraCount("obra-2", "Obra Norte", 0),
      fixtureObraCount("obra-3", "Obra Sul", 0),
    ];

    render(<ObraDistributionCard porObra={porObra} />);

    expect(screen.getByText("Obra Central")).toBeInTheDocument();
    expect(screen.queryByText("Obra Norte")).not.toBeInTheDocument();
    expect(screen.queryByText("Obra Sul")).not.toBeInTheDocument();
  });

  it("shows an empty state when no obra has a pedido in scope", () => {
    render(<ObraDistributionCard porObra={[]} />);

    expect(screen.getByText("Nenhum pedido no escopo")).toBeInTheDocument();
  });
});
