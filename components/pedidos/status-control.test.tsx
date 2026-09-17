import { describe, expect, it, vi } from "vitest";
import { fireEvent, render, screen } from "@testing-library/react";
import { StatusControl } from "./status-control";
import type { Status } from "@/lib/types/domain";

const now = "2026-01-01T10:00:00Z";

function fixtureStatus(slug: string, name: string, sortOrder: number): Status {
  return {
    id: `status-${slug}`,
    name,
    slug,
    description: null,
    sort_order: sortOrder,
    is_active: true,
    created_at: now,
    updated_at: now,
  };
}

const statuses: Status[] = [
  fixtureStatus("solicitado", "Solicitado", 1),
  fixtureStatus("em_analise", "Em análise", 2),
  fixtureStatus("em_compra_preparacao", "Em compra/preparação", 3),
  fixtureStatus("aguardando_entrega", "Aguardando entrega", 4),
  fixtureStatus("entregue", "Entregue", 5),
];

/** See responsavel-control.test.tsx for why a bare click doesn't select an item. */
function selectOption(name: string): void {
  const option = screen.getByRole("option", { name });
  fireEvent.pointerDown(option, { pointerType: "mouse" });
  fireEvent.click(option);
}

describe("StatusControl", () => {
  it("lists the active workflow statuses, excluding cancelado", () => {
    render(
      <StatusControl statusId="status-solicitado" statuses={statuses} onChange={vi.fn()} />,
    );

    fireEvent.click(screen.getByRole("combobox"));

    expect(screen.getAllByRole("option")).toHaveLength(5);
    expect(screen.queryByRole("option", { name: "Cancelado" })).not.toBeInTheDocument();
  });

  it("calls onChange with the chosen status object", () => {
    const onChange = vi.fn();
    render(<StatusControl statusId="status-solicitado" statuses={statuses} onChange={onChange} />);

    fireEvent.click(screen.getByRole("combobox"));
    selectOption("Entregue");

    expect(onChange).toHaveBeenCalledWith(statuses.find((s) => s.slug === "entregue"));
  });

  it("stays controlled by statusId — an external revert is reflected without local state", () => {
    const { rerender } = render(
      <StatusControl statusId="status-solicitado" statuses={statuses} onChange={vi.fn()} />,
    );
    // Base UI's Select only registers an item's rendered label the first
    // time its list mounts — open it once so the trigger can resolve
    // "status-em_analise" to "Em análise" instead of the raw id.
    fireEvent.click(screen.getByRole("combobox"));
    fireEvent.click(screen.getByRole("combobox"));

    rerender(<StatusControl statusId="status-em_analise" statuses={statuses} onChange={vi.fn()} />);

    expect(screen.getByText("Em análise")).toBeInTheDocument();
  });

  it("is disabled when disabled is true", () => {
    render(
      <StatusControl statusId="status-solicitado" statuses={statuses} disabled onChange={vi.fn()} />,
    );

    expect(screen.getByRole("combobox")).toBeDisabled();
  });
});
