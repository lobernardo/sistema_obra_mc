import { describe, expect, it, vi } from "vitest";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { useRouter } from "next/navigation";
import { PedidoStatusField } from "./pedido-status-field";
import { moveStatus } from "@/app/suprimentos/actions";
import type { Status } from "@/lib/types/domain";

vi.mock("@/app/suprimentos/actions", () => ({
  moveStatus: vi.fn(),
}));

vi.mock("@/lib/toast", () => ({
  notifySuccess: vi.fn(),
  notifyError: vi.fn(),
}));

vi.mock("next/navigation", () => ({
  useRouter: vi.fn(),
}));

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
  fixtureStatus("entregue", "Entregue", 5),
];

function selectOption(name: string): void {
  const option = screen.getByRole("option", { name });
  fireEvent.pointerDown(option, { pointerType: "mouse" });
  fireEvent.click(option);
}

describe("PedidoStatusField", () => {
  it("calls moveStatus with the pedido and chosen status id, then refreshes on success", async () => {
    const refresh = vi.fn();
    vi.mocked(useRouter).mockReturnValue({ refresh } as unknown as ReturnType<typeof useRouter>);
    vi.mocked(moveStatus).mockResolvedValue({ pedido: { id: "pedido-1" } as never });

    render(
      <PedidoStatusField pedidoId="pedido-1" statusId="status-solicitado" statuses={statuses} />,
    );

    fireEvent.click(screen.getByRole("combobox"));
    selectOption("Em análise");

    await waitFor(() =>
      expect(moveStatus).toHaveBeenCalledWith("pedido-1", "status-em_analise"),
    );
    await waitFor(() => expect(refresh).toHaveBeenCalled());
  });

  it("reverts the status and does not refresh when the action returns an error", async () => {
    const refresh = vi.fn();
    vi.mocked(useRouter).mockReturnValue({ refresh } as unknown as ReturnType<typeof useRouter>);
    vi.mocked(moveStatus).mockResolvedValue({
      error: "Pedido em status terminal não pode ser alterado.",
    });

    render(
      <PedidoStatusField pedidoId="pedido-1" statusId="status-solicitado" statuses={statuses} />,
    );

    fireEvent.click(screen.getByRole("combobox"));
    selectOption("Em análise");

    await waitFor(() => expect(moveStatus).toHaveBeenCalled());
    expect(refresh).not.toHaveBeenCalled();
    expect(screen.getByRole("combobox")).toHaveTextContent("Solicitado");
  });
});
