import { describe, expect, it, vi } from "vitest";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { NovaSolicitacaoForm } from "./nova-solicitacao-form";
import { createSolicitacao } from "@/app/obra/novo/actions";
import type { Obra } from "@/lib/types/domain";

vi.mock("@/app/obra/novo/actions", () => ({
  createSolicitacao: vi.fn(),
}));

vi.mock("@/lib/toast", () => ({
  notifySuccess: vi.fn(),
  notifyError: vi.fn(),
}));

const now = "2026-01-01T10:00:00Z";

const obras: Obra[] = [
  {
    id: "obra-1",
    name: "Obra Central",
    is_active: true,
    is_demo: false,
    created_at: now,
    updated_at: now,
  },
  {
    id: "obra-2",
    name: "Obra Norte",
    is_active: true,
    is_demo: false,
    created_at: now,
    updated_at: now,
  },
];

describe("NovaSolicitacaoForm", () => {
  it("lists only the given obras in the obra select", () => {
    render(<NovaSolicitacaoForm obras={obras} />);

    fireEvent.click(screen.getByRole("combobox"));

    expect(screen.getByRole("option", { name: "Obra Central" })).toBeInTheDocument();
    expect(screen.getByRole("option", { name: "Obra Norte" })).toBeInTheDocument();
    expect(screen.getAllByRole("option")).toHaveLength(2);
  });

  it("keeps the submit button disabled until obra, data necessária and itens are filled", () => {
    render(<NovaSolicitacaoForm obras={obras} />);

    const submit = screen.getByRole("button", { name: "Enviar solicitação" });
    expect(submit).toBeDisabled();

    fireEvent.change(screen.getByLabelText("Itens/quantidades"), {
      target: { value: "10 sacos de cimento" },
    });
    expect(submit).toBeDisabled();
  });

  it("does not show any editable field for the automatic solicitation date", () => {
    render(<NovaSolicitacaoForm obras={obras} />);

    expect(screen.queryByLabelText(/data da solicitação/i)).not.toBeInTheDocument();
  });

  it("shows the error message from a failed submission and keeps the typed items", async () => {
    vi.mocked(createSolicitacao).mockResolvedValue({
      error: "A obra informada não está associada ao solicitante.",
    });
    render(<NovaSolicitacaoForm obras={obras} />);

    fireEvent.change(screen.getByLabelText("Itens/quantidades"), {
      target: { value: "10 sacos de cimento" },
    });

    expect(screen.getByLabelText("Itens/quantidades")).toHaveValue("10 sacos de cimento");
  });

  it("renders the confirmation with the pedido code and links to detail/listing after success", async () => {
    vi.mocked(createSolicitacao).mockResolvedValue({ pedido: { code: "PED-000042" } });
    const { container } = render(<NovaSolicitacaoForm obras={obras} />);

    fireEvent.change(screen.getByLabelText("Itens/quantidades"), {
      target: { value: "10 sacos de cimento" },
    });
    fireEvent.submit(container.querySelector("form")!);

    await waitFor(() => expect(screen.getByText("PED-000042")).toBeInTheDocument());
    expect(screen.getByRole("link", { name: "Ver detalhe" })).toHaveAttribute(
      "href",
      "/obra/PED-000042",
    );
    expect(screen.getByRole("link", { name: "Ver Meus Pedidos" })).toHaveAttribute(
      "href",
      "/obra",
    );
  });
});
