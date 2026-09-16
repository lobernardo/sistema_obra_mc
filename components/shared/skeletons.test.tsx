import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { DashboardIndicatorSkeleton, KanbanCardSkeleton, ListSkeleton } from "./skeletons";

describe("ListSkeleton", () => {
  it("renders the requested number of placeholder rows", () => {
    render(<ListSkeleton rows={3} />);
    expect(screen.getByRole("status", { name: "Carregando lista" })).toBeInTheDocument();
  });

  it("defaults to 5 rows when none is given", () => {
    const { container } = render(<ListSkeleton />);
    expect(container.querySelectorAll('[data-slot="skeleton"]').length).toBe(5);
  });
});

describe("KanbanCardSkeleton", () => {
  it("renders a card-shaped placeholder", () => {
    render(<KanbanCardSkeleton />);
    expect(screen.getByRole("status", { name: "Carregando pedido" })).toBeInTheDocument();
  });
});

describe("DashboardIndicatorSkeleton", () => {
  it("renders an indicator-shaped placeholder", () => {
    render(<DashboardIndicatorSkeleton />);
    expect(screen.getByRole("status", { name: "Carregando indicador" })).toBeInTheDocument();
  });
});
