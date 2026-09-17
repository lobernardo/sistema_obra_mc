import { describe, expect, it, vi } from "vitest";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { format } from "date-fns";
import { useRouter } from "next/navigation";
import { PrevisaoControl } from "./previsao-control";
import { setPrevisao } from "@/app/suprimentos/actions";

vi.mock("@/app/suprimentos/actions", () => ({
  setPrevisao: vi.fn(),
}));

vi.mock("@/lib/toast", () => ({
  notifySuccess: vi.fn(),
  notifyError: vi.fn(),
}));

vi.mock("next/navigation", () => ({
  useRouter: vi.fn(),
}));

/** The Calendar always opens on the current month, so "today"'s cell is the one day guaranteed not to collide with an overflow day from an adjacent month. */
const todayIso = format(new Date(), "yyyy-MM-dd");

function pickToday(): void {
  fireEvent.click(screen.getByRole("button", { name: /^Today,/ }));
}

describe("PrevisaoControl", () => {
  it("shows a placeholder when there is no previsão yet", () => {
    vi.mocked(useRouter).mockReturnValue({ refresh: vi.fn() } as unknown as ReturnType<
      typeof useRouter
    >);
    render(<PrevisaoControl pedidoId="pedido-1" expectedDeliveryAt={null} />);

    expect(screen.getByText("Selecione a data")).toBeInTheDocument();
  });

  it("shows the formatted previsão when one is set", () => {
    vi.mocked(useRouter).mockReturnValue({ refresh: vi.fn() } as unknown as ReturnType<
      typeof useRouter
    >);
    render(<PrevisaoControl pedidoId="pedido-1" expectedDeliveryAt="2026-03-15" />);

    expect(screen.getByText("15/03/2026")).toBeInTheDocument();
  });

  it("calls setPrevisao with the pedido and chosen date, then refreshes on success", async () => {
    const refresh = vi.fn();
    vi.mocked(useRouter).mockReturnValue({ refresh } as unknown as ReturnType<typeof useRouter>);
    vi.mocked(setPrevisao).mockResolvedValue({ pedido: { id: "pedido-1" } as never });

    render(<PrevisaoControl pedidoId="pedido-1" expectedDeliveryAt={null} />);

    fireEvent.click(screen.getByRole("button", { name: "Selecione a data" }));
    pickToday();

    await waitFor(() => expect(setPrevisao).toHaveBeenCalledWith("pedido-1", todayIso));
    await waitFor(() => expect(refresh).toHaveBeenCalled());
  });

  it("reverts the previsão and does not refresh when the action returns an error", async () => {
    const refresh = vi.fn();
    vi.mocked(useRouter).mockReturnValue({ refresh } as unknown as ReturnType<typeof useRouter>);
    vi.mocked(setPrevisao).mockResolvedValue({ error: "Não foi possível concluir a ação." });

    render(<PrevisaoControl pedidoId="pedido-1" expectedDeliveryAt="2026-03-15" />);

    fireEvent.click(screen.getByRole("button", { name: /15\/03\/2026/ }));
    pickToday();

    await waitFor(() => expect(setPrevisao).toHaveBeenCalled());
    expect(refresh).not.toHaveBeenCalled();
    expect(screen.getByText("15/03/2026")).toBeInTheDocument();
  });
});
