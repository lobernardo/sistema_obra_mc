import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { StatusDistributionCard } from "./status-distribution-card";
import type { StatusCount } from "@/lib/pedidos/dashboard";

const now = "2026-01-01T10:00:00Z";

function fixtureStatusCount(slug: string, name: string, sortOrder: number, count: number): StatusCount {
  return {
    status: {
      id: `status-${slug}`,
      name,
      slug,
      description: null,
      sort_order: sortOrder,
      is_active: true,
      created_at: now,
      updated_at: now,
    },
    count,
  };
}

describe("StatusDistributionCard", () => {
  it("shows every status with its count, in the given order", () => {
    const porStatus = [
      fixtureStatusCount("solicitado", "Solicitado", 1, 3),
      fixtureStatusCount("entregue", "Entregue", 5, 2),
      fixtureStatusCount("cancelado", "Cancelado", 6, 1),
    ];

    render(<StatusDistributionCard porStatus={porStatus} />);

    expect(screen.getByText("Solicitado")).toBeInTheDocument();
    expect(screen.getByText("3")).toBeInTheDocument();
    expect(screen.getByText("Cancelado")).toBeInTheDocument();
    expect(screen.getByText("1")).toBeInTheDocument();
  });
});
