import { describe, expect, it, vi } from "vitest";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { DashboardFilterBar } from "./dashboard-filter-bar";
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
  vi.mocked(usePathname).mockReturnValue("/gestao");
  vi.mocked(useSearchParams).mockReturnValue(
    new URLSearchParams(initialQuery) as unknown as ReturnType<typeof useSearchParams>,
  );

  render(
    <DashboardFilterBar
      obras={obras}
      suprimentosProfiles={suprimentosProfiles}
      priorities={priorities}
      statuses={statuses}
    />,
  );

  return { push };
}

describe("DashboardFilterBar", () => {
  it("selecting an obra pushes obraId onto the URL", () => {
    const { push } = setup();

    fireEvent.click(screen.getByLabelText("Obra"));
    selectOption("Obra Central");

    expect(push).toHaveBeenCalledWith("/gestao?obraId=obra-1");
  });

  it("combines two filters (obra + status) into the same query string", () => {
    const { push } = setup("obraId=obra-1");

    fireEvent.click(screen.getByLabelText("Status"));
    selectOption("Em análise");

    expect(push).toHaveBeenCalledWith("/gestao?obraId=obra-1&statusId=status-2");
  });

  it("selecting a priority pushes priorityId onto the URL", () => {
    const { push } = setup();

    fireEvent.click(screen.getByLabelText("Prioridade"));
    selectOption("Baixa");

    expect(push).toHaveBeenCalledWith("/gestao?priorityId=prio-1");
  });

  it("selecting a responsible pushes responsibleId onto the URL", () => {
    const { push } = setup();

    fireEvent.click(screen.getByLabelText("Responsável"));
    selectOption("João Suprimentos");

    expect(push).toHaveBeenCalledWith("/gestao?responsibleId=prof-1");
  });

  it("changing the período (de) filter pushes requestedFrom onto the URL", async () => {
    const { push } = setup();

    fireEvent.change(screen.getByLabelText("Período (de)"), { target: { value: "2026-01-01" } });

    await waitFor(() => expect(push).toHaveBeenCalledWith("/gestao?requestedFrom=2026-01-01"));
  });

  it("selecting the 'all' option removes the filter from the URL", () => {
    const { push } = setup("obraId=obra-1");

    fireEvent.click(screen.getByLabelText("Obra"));
    selectOption("Todas as obras");

    expect(push).toHaveBeenCalledWith("/gestao");
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
