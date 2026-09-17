import { describe, expect, it, vi } from "vitest";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { useRouter } from "next/navigation";
import { CancelarPedidoDialog } from "./cancelar-pedido-dialog";
import { cancelarPedido } from "@/app/suprimentos/actions";

vi.mock("@/app/suprimentos/actions", () => ({
  cancelarPedido: vi.fn(),
}));

vi.mock("@/lib/toast", () => ({
  notifySuccess: vi.fn(),
  notifyError: vi.fn(),
}));

vi.mock("next/navigation", () => ({
  useRouter: vi.fn(),
}));

describe("CancelarPedidoDialog", () => {
  it("does not cancel until the confirmation dialog is confirmed", () => {
    vi.mocked(useRouter).mockReturnValue({ refresh: vi.fn() } as unknown as ReturnType<
      typeof useRouter
    >);
    render(<CancelarPedidoDialog pedidoId="pedido-1" pedidoCode="PED-000001" />);

    fireEvent.click(screen.getByRole("button", { name: "Cancelar Pedido" }));

    expect(screen.getByText("Cancelar pedido PED-000001?")).toBeInTheDocument();
    expect(cancelarPedido).not.toHaveBeenCalled();
  });

  it("calls cancelarPedido and refreshes when confirmed", async () => {
    const refresh = vi.fn();
    vi.mocked(useRouter).mockReturnValue({ refresh } as unknown as ReturnType<typeof useRouter>);
    vi.mocked(cancelarPedido).mockResolvedValue({ pedido: { id: "pedido-1" } as never });

    render(<CancelarPedidoDialog pedidoId="pedido-1" pedidoCode="PED-000001" />);

    fireEvent.click(screen.getByRole("button", { name: "Cancelar Pedido" }));
    fireEvent.click(screen.getByRole("button", { name: "Confirmar cancelamento" }));

    await waitFor(() => expect(cancelarPedido).toHaveBeenCalledWith("pedido-1"));
    await waitFor(() => expect(refresh).toHaveBeenCalled());
  });

  it("closing via Voltar does not call cancelarPedido", () => {
    vi.mocked(useRouter).mockReturnValue({ refresh: vi.fn() } as unknown as ReturnType<
      typeof useRouter
    >);
    render(<CancelarPedidoDialog pedidoId="pedido-1" pedidoCode="PED-000001" />);

    fireEvent.click(screen.getByRole("button", { name: "Cancelar Pedido" }));
    fireEvent.click(screen.getByRole("button", { name: "Voltar" }));

    expect(cancelarPedido).not.toHaveBeenCalled();
  });

  it("shows the error and keeps the dialog open when the action fails", async () => {
    vi.mocked(useRouter).mockReturnValue({ refresh: vi.fn() } as unknown as ReturnType<
      typeof useRouter
    >);
    vi.mocked(cancelarPedido).mockResolvedValue({
      error: "Pedido em status terminal não pode ser cancelado.",
    });

    render(<CancelarPedidoDialog pedidoId="pedido-1" pedidoCode="PED-000001" />);

    fireEvent.click(screen.getByRole("button", { name: "Cancelar Pedido" }));
    fireEvent.click(screen.getByRole("button", { name: "Confirmar cancelamento" }));

    await waitFor(() => expect(cancelarPedido).toHaveBeenCalled());
    expect(screen.getByText("Cancelar pedido PED-000001?")).toBeInTheDocument();
  });
});
