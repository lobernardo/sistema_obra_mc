import { describe, expect, it, vi } from "vitest";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { useRouter } from "next/navigation";
import { MarcarEntregueButton } from "./marcar-entregue-button";
import { moveStatus } from "@/app/suprimentos/actions";

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

describe("MarcarEntregueButton", () => {
  it("calls moveStatus with the pedido and the entregue status id, then refreshes on success", async () => {
    const refresh = vi.fn();
    vi.mocked(useRouter).mockReturnValue({ refresh } as unknown as ReturnType<typeof useRouter>);
    vi.mocked(moveStatus).mockResolvedValue({ pedido: { id: "pedido-1" } as never });

    render(<MarcarEntregueButton pedidoId="pedido-1" entregueStatusId="status-entregue" />);

    fireEvent.click(screen.getByRole("button", { name: "Marcar como Entregue" }));

    await waitFor(() =>
      expect(moveStatus).toHaveBeenCalledWith("pedido-1", "status-entregue"),
    );
    await waitFor(() => expect(refresh).toHaveBeenCalled());
  });

  it("shows the error and does not refresh when the action fails", async () => {
    const refresh = vi.fn();
    vi.mocked(useRouter).mockReturnValue({ refresh } as unknown as ReturnType<typeof useRouter>);
    vi.mocked(moveStatus).mockResolvedValue({ error: "Não foi possível concluir a ação." });

    render(<MarcarEntregueButton pedidoId="pedido-1" entregueStatusId="status-entregue" />);

    fireEvent.click(screen.getByRole("button", { name: "Marcar como Entregue" }));

    await waitFor(() => expect(moveStatus).toHaveBeenCalled());
    expect(refresh).not.toHaveBeenCalled();
  });
});
