import { describe, expect, it, vi } from "vitest";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { useRouter } from "next/navigation";
import { ResponsavelControl } from "./responsavel-control";
import { setResponsavel } from "@/app/suprimentos/actions";
import type { Profile } from "@/lib/types/domain";

vi.mock("@/app/suprimentos/actions", () => ({
  setResponsavel: vi.fn(),
}));

vi.mock("@/lib/toast", () => ({
  notifySuccess: vi.fn(),
  notifyError: vi.fn(),
}));

vi.mock("next/navigation", () => ({
  useRouter: vi.fn(),
}));

const now = "2026-01-01T10:00:00Z";

function fixtureProfile(id: string, fullName: string): Profile {
  return {
    id,
    full_name: fullName,
    role_id: "role-suprimentos",
    is_active: true,
    is_demo: false,
    created_at: now,
    updated_at: now,
    role: {
      id: "role-suprimentos",
      name: "Suprimentos",
      slug: "suprimentos",
      description: null,
      is_active: true,
      created_at: now,
      updated_at: now,
    },
  };
}

const profiles = [fixtureProfile("prof-1", "João Suprimentos"), fixtureProfile("prof-2", "Ana Suprimentos")];

/**
 * `@base-ui/react`'s Select item only commits a selection on `click` when a
 * preceding `pointerdown` marked it as a real (non-synthetic) mouse
 * interaction — a bare `fireEvent.click` looks like an untrusted virtual
 * click and is ignored. No `@testing-library/user-event` in this project's
 * devDependencies, so reproduce the same two-event sequence by hand.
 */
function selectOption(name: string): void {
  const option = screen.getByRole("option", { name });
  fireEvent.pointerDown(option, { pointerType: "mouse" });
  fireEvent.click(option);
}

describe("ResponsavelControl", () => {
  it("lists every suprimentos profile plus a 'Sem responsável' option", () => {
    vi.mocked(useRouter).mockReturnValue({ refresh: vi.fn() } as unknown as ReturnType<
      typeof useRouter
    >);
    render(
      <ResponsavelControl pedidoId="pedido-1" responsibleId={null} suprimentosProfiles={profiles} />,
    );

    fireEvent.click(screen.getByRole("combobox"));

    expect(screen.getByRole("option", { name: "Sem responsável" })).toBeInTheDocument();
    expect(screen.getByRole("option", { name: "João Suprimentos" })).toBeInTheDocument();
    expect(screen.getByRole("option", { name: "Ana Suprimentos" })).toBeInTheDocument();
  });

  it("calls setResponsavel with the pedido and chosen profile id, then refreshes on success", async () => {
    const refresh = vi.fn();
    vi.mocked(useRouter).mockReturnValue({ refresh } as unknown as ReturnType<typeof useRouter>);
    vi.mocked(setResponsavel).mockResolvedValue({
      pedido: { id: "pedido-1" } as never,
    });

    render(
      <ResponsavelControl pedidoId="pedido-1" responsibleId={null} suprimentosProfiles={profiles} />,
    );

    fireEvent.click(screen.getByRole("combobox"));
    selectOption("João Suprimentos");

    await waitFor(() => expect(setResponsavel).toHaveBeenCalledWith("pedido-1", "prof-1"));
    await waitFor(() => expect(refresh).toHaveBeenCalled());
  });

  it("reverts the selection and does not refresh when the action returns an error", async () => {
    const refresh = vi.fn();
    vi.mocked(useRouter).mockReturnValue({ refresh } as unknown as ReturnType<typeof useRouter>);
    vi.mocked(setResponsavel).mockResolvedValue({ error: "Apenas suprimentos pode fazer isso." });

    render(
      <ResponsavelControl pedidoId="pedido-1" responsibleId={null} suprimentosProfiles={profiles} />,
    );

    fireEvent.click(screen.getByRole("combobox"));
    selectOption("João Suprimentos");

    await waitFor(() => expect(setResponsavel).toHaveBeenCalled());
    expect(refresh).not.toHaveBeenCalled();
    expect(screen.getByRole("combobox")).toHaveTextContent("Sem responsável");
  });
});
