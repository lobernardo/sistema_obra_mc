import { describe, expect, it, vi } from "vitest";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { PedidosFilterBar } from "./pedidos-filter-bar";
import type { Obra, Priority, Profile, Status } from "@/lib/types/domain";

vi.mock("next/navigation", () => ({
  useRouter: vi.fn(),
  usePathname: vi.fn(),
  useSearchParams: vi.fn(),
}));

const now = "2026-01-01T10:00:00Z";

const obras: Obra[] = [
  { id: "obra-1", name: "Obra Central", is_active: true, is_demo: false, created_at: now, updated_at: now },
  { id: "obra-2", name: "Obra Norte", is_active: true, is_demo: false, created_at: now, updated_at: now },
];

const statuses: Status[] = [
  { id: "status-1", name: "Solicitado", slug: "solicitado", description: null, sort_order: 1, is_active: true, created_at: now, updated_at: now },
  { id: "status-2", name: "Em análise", slug: "em_analise", description: null, sort_order: 2, is_active: true, created_at: now, updated_at: now },
];

const priorities: Priority[] = [
  { id: "prio-1", name: "Baixa", slug: "baixa", sort_order: 1, is_active: true, created_at: now, updated_at: now },
];

const suprimentosProfiles: Profile[] = [
  {
    id: "prof-1",
    full_name: "João Suprimentos",
    role_id: "role-suprimentos",
    is_active: true,
    is_demo: false,
    created_at: now,
    updated_at: now,
    role: { id: "role-suprimentos", name: "Suprimentos", slug: "suprimentos", description: null, is_active: true, created_at: now, updated_at: now },
  },
];

function selectOption(name: string): void {
  const option = screen.getByRole("option", { name });
  fireEvent.pointerDown(option, { pointerType: "mouse" });
  fireEvent.click(option);
}

function setup(initialQuery = "") {
  const push = vi.fn();
  vi.mocked(useRouter).mockReturnValue({ push } as unknown as ReturnType<typeof useRouter>);
  vi.mocked(usePathname).mockReturnValue("/suprimentos/pedidos");
  vi.mocked(useSearchParams).mockReturnValue(
    new URLSearchParams(initialQuery) as unknown as ReturnType<typeof useSearchParams>,
  );

  render(
    <PedidosFilterBar
      obras={obras}
      suprimentosProfiles={suprimentosProfiles}
      priorities={priorities}
      statuses={statuses}
    />,
  );

  return { push };
}

describe("PedidosFilterBar", () => {
  it("selecting an obra pushes obraId onto the URL", () => {
    const { push } = setup();

    fireEvent.click(screen.getByLabelText("Obra"));
    selectOption("Obra Central");

    expect(push).toHaveBeenCalledWith("/suprimentos/pedidos?obraId=obra-1");
  });

  it("combines two filters (obra + status) into the same query string", () => {
    const { push } = setup("obraId=obra-1");

    fireEvent.click(screen.getByLabelText("Status"));
    selectOption("Em análise");

    expect(push).toHaveBeenCalledWith("/suprimentos/pedidos?obraId=obra-1&statusId=status-2");
  });

  it("selecting 'Somente atrasados' sets atrasado=true", () => {
    const { push } = setup();

    fireEvent.click(screen.getByLabelText("Atraso"));
    selectOption("Somente atrasados");

    expect(push).toHaveBeenCalledWith("/suprimentos/pedidos?atrasado=true");
  });

  it("selecting 'Excluir atrasados' sets atrasado=false", () => {
    const { push } = setup();

    fireEvent.click(screen.getByLabelText("Atraso"));
    selectOption("Excluir atrasados");

    expect(push).toHaveBeenCalledWith("/suprimentos/pedidos?atrasado=false");
  });

  it("selecting the 'all' option removes the filter from the URL", () => {
    const { push } = setup("obraId=obra-1");

    fireEvent.click(screen.getByLabelText("Obra"));
    selectOption("Todas as obras");

    expect(push).toHaveBeenCalledWith("/suprimentos/pedidos");
  });

  it("debounces free-text search into the search query param", async () => {
    const { push } = setup();

    fireEvent.change(screen.getByLabelText("Buscar"), { target: { value: "cimento" } });

    await waitFor(() =>
      expect(push).toHaveBeenCalledWith("/suprimentos/pedidos?search=cimento"),
    );
  });

  it("shows a clear-filters action only when a filter is applied", () => {
    setup("obraId=obra-1");
    expect(screen.getByRole("button", { name: "Limpar filtros" })).toBeInTheDocument();
  });

  it("hides the clear-filters action when no filter is applied", () => {
    setup();
    expect(screen.queryByRole("button", { name: "Limpar filtros" })).not.toBeInTheDocument();
  });
});
