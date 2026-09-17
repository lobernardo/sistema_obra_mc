import { describe, expect, it, vi } from "vitest";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { useRouter } from "next/navigation";
import { PrioridadeControl } from "./prioridade-control";
import { setPrioridade } from "@/app/suprimentos/actions";
import type { Priority } from "@/lib/types/domain";

vi.mock("@/app/suprimentos/actions", () => ({
  setPrioridade: vi.fn(),
}));

vi.mock("@/lib/toast", () => ({
  notifySuccess: vi.fn(),
  notifyError: vi.fn(),
}));

vi.mock("next/navigation", () => ({
  useRouter: vi.fn(),
}));

const now = "2026-01-01T10:00:00Z";

const priorities: Priority[] = [
  { id: "prio-baixa", name: "Baixa", slug: "baixa", sort_order: 1, is_active: true, created_at: now, updated_at: now },
  { id: "prio-normal", name: "Normal", slug: "normal", sort_order: 2, is_active: true, created_at: now, updated_at: now },
  { id: "prio-alta", name: "Alta", slug: "alta", sort_order: 3, is_active: true, created_at: now, updated_at: now },
  { id: "prio-urgente", name: "Urgente", slug: "urgente", sort_order: 4, is_active: true, created_at: now, updated_at: now },
];

/** See responsavel-control.test.tsx for why a bare click doesn't select an item. */
function selectOption(name: string): void {
  const option = screen.getByRole("option", { name });
  fireEvent.pointerDown(option, { pointerType: "mouse" });
  fireEvent.click(option);
}

describe("PrioridadeControl", () => {
  it("lists all 4 V0 priority levels", () => {
    vi.mocked(useRouter).mockReturnValue({ refresh: vi.fn() } as unknown as ReturnType<
      typeof useRouter
    >);
    render(<PrioridadeControl pedidoId="pedido-1" priorityId={null} priorities={priorities} />);

    fireEvent.click(screen.getByRole("combobox"));

    expect(screen.getAllByRole("option")).toHaveLength(4);
    expect(screen.getByRole("option", { name: "Urgente" })).toBeInTheDocument();
  });

  it("calls setPrioridade with the pedido and chosen priority id, then refreshes on success", async () => {
    const refresh = vi.fn();
    vi.mocked(useRouter).mockReturnValue({ refresh } as unknown as ReturnType<typeof useRouter>);
    vi.mocked(setPrioridade).mockResolvedValue({ pedido: { id: "pedido-1" } as never });

    render(<PrioridadeControl pedidoId="pedido-1" priorityId="prio-baixa" priorities={priorities} />);

    fireEvent.click(screen.getByRole("combobox"));
    selectOption("Urgente");

    await waitFor(() =>
      expect(setPrioridade).toHaveBeenCalledWith("pedido-1", "prio-urgente"),
    );
    await waitFor(() => expect(refresh).toHaveBeenCalled());
  });

  it("reverts the selection and does not refresh when the action returns an error", async () => {
    const refresh = vi.fn();
    vi.mocked(useRouter).mockReturnValue({ refresh } as unknown as ReturnType<typeof useRouter>);
    vi.mocked(setPrioridade).mockResolvedValue({ error: "Prioridade inválida." });

    render(<PrioridadeControl pedidoId="pedido-1" priorityId="prio-baixa" priorities={priorities} />);

    fireEvent.click(screen.getByRole("combobox"));
    selectOption("Urgente");

    await waitFor(() => expect(setPrioridade).toHaveBeenCalled());
    expect(refresh).not.toHaveBeenCalled();
    expect(screen.getByRole("combobox")).toHaveTextContent("Baixa");
  });
});
