import { describe, expect, it, vi } from "vitest";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { useRouter } from "next/navigation";
import { KanbanBoard } from "./kanban-board";
import { moveStatus } from "@/app/suprimentos/actions";
import type { PedidoComRelacoes, Status } from "@/lib/types/domain";

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
  fixtureStatus("em_compra_preparacao", "Em compra/preparação", 3),
  fixtureStatus("aguardando_entrega", "Aguardando entrega", 4),
  fixtureStatus("entregue", "Entregue", 5),
];

function fixturePedido(overrides: Partial<PedidoComRelacoes> = {}): PedidoComRelacoes {
  return {
    id: "pedido-1",
    code: "PED-000001",
    obra_id: "obra-1",
    requester_id: "requester-1",
    requested_at: now,
    needed_at: "2026-02-01",
    items_description: "10 sacos de cimento",
    status_id: "status-solicitado",
    priority_id: "priority-1",
    responsible_id: null,
    expected_delivery_at: null,
    is_demo: false,
    created_at: now,
    updated_at: now,
    obra: { id: "obra-1", name: "Obra Central", is_active: true, is_demo: false, created_at: now, updated_at: now },
    status: statuses[0],
    priority: {
      id: "priority-1",
      name: "Alta",
      slug: "alta",
      sort_order: 3,
      is_active: true,
      created_at: now,
      updated_at: now,
    },
    requester: {
      id: "requester-1",
      full_name: "Maria Obra",
      role_id: "role-obra",
      is_active: true,
      is_demo: false,
      created_at: now,
      updated_at: now,
      role: { id: "role-obra", name: "Obra", slug: "obra", description: null, is_active: true, created_at: now, updated_at: now },
    },
    responsible: null,
    ...overrides,
  };
}

/** jsdom has no native DataTransfer — provide a minimal stand-in for drag events. */
function createDataTransfer() {
  const store = new Map<string, string>();
  return {
    setData: (type: string, value: string) => store.set(type, value),
    getData: (type: string) => store.get(type) ?? "",
    effectAllowed: "",
  };
}

describe("KanbanBoard", () => {
  it("renders one column per active status, ordered by sort_order, excluding cancelado", () => {
    vi.mocked(useRouter).mockReturnValue({ refresh: vi.fn() } as unknown as ReturnType<
      typeof useRouter
    >);
    render(<KanbanBoard initialPedidos={[]} statuses={statuses} />);

    const headings = screen.getAllByRole("heading", { level: 2 }).map((h) => h.textContent);
    expect(headings).toEqual([
      "Solicitado",
      "Em análise",
      "Em compra/preparação",
      "Aguardando entrega",
      "Entregue",
    ]);
    expect(screen.queryByText("Cancelado")).not.toBeInTheDocument();
  });

  it("places each pedido in the column matching its status", () => {
    vi.mocked(useRouter).mockReturnValue({ refresh: vi.fn() } as unknown as ReturnType<
      typeof useRouter
    >);
    const pedido = fixturePedido({ status_id: "status-em_analise", status: statuses[1] });
    render(<KanbanBoard initialPedidos={[pedido]} statuses={statuses} />);

    expect(screen.getByText("PED-000001")).toBeInTheDocument();
  });

  it("moving a card via drag-and-drop calls moveStatus and updates the column immediately", async () => {
    const refresh = vi.fn();
    vi.mocked(useRouter).mockReturnValue({ refresh } as unknown as ReturnType<typeof useRouter>);
    vi.mocked(moveStatus).mockResolvedValue({ pedido: { id: "pedido-1" } as never });

    const pedido = fixturePedido();
    render(<KanbanBoard initialPedidos={[pedido]} statuses={statuses} />);

    const card = screen.getByText("PED-000001").closest('[draggable="true"]')!;
    const dataTransfer = createDataTransfer();
    fireEvent.dragStart(card, { dataTransfer });

    const targetColumn = screen.getByText("Em análise").closest("div")!.nextElementSibling!;
    fireEvent.dragOver(targetColumn, { dataTransfer });
    fireEvent.drop(targetColumn, { dataTransfer });

    await waitFor(() =>
      expect(moveStatus).toHaveBeenCalledWith("pedido-1", "status-em_analise"),
    );
    await waitFor(() => expect(refresh).toHaveBeenCalled());
  });

  it("reverts the move when the drag-and-drop persistence fails", async () => {
    const refresh = vi.fn();
    vi.mocked(useRouter).mockReturnValue({ refresh } as unknown as ReturnType<typeof useRouter>);
    vi.mocked(moveStatus).mockResolvedValue({ error: "Transição de status inválida." });

    const pedido = fixturePedido();
    render(<KanbanBoard initialPedidos={[pedido]} statuses={statuses} />);

    const card = screen.getByText("PED-000001").closest('[draggable="true"]')!;
    const dataTransfer = createDataTransfer();
    fireEvent.dragStart(card, { dataTransfer });

    const targetColumn = screen.getByText("Em análise").closest("div")!.nextElementSibling!;
    fireEvent.drop(targetColumn, { dataTransfer });

    await waitFor(() => expect(moveStatus).toHaveBeenCalled());
    expect(refresh).not.toHaveBeenCalled();
    // The card's accessible status select must reflect the reverted status.
    const select = screen.getByRole("combobox");
    expect(select).toHaveTextContent("Solicitado");
  });

  it("the card's accessible status select produces the same call as drag-and-drop", async () => {
    const refresh = vi.fn();
    vi.mocked(useRouter).mockReturnValue({ refresh } as unknown as ReturnType<typeof useRouter>);
    vi.mocked(moveStatus).mockResolvedValue({ pedido: { id: "pedido-1" } as never });

    const pedido = fixturePedido();
    render(<KanbanBoard initialPedidos={[pedido]} statuses={statuses} />);

    fireEvent.click(screen.getByRole("combobox"));
    const option = screen.getByRole("option", { name: "Em análise" });
    fireEvent.pointerDown(option, { pointerType: "mouse" });
    fireEvent.click(option);

    await waitFor(() =>
      expect(moveStatus).toHaveBeenCalledWith("pedido-1", "status-em_analise"),
    );
  });

  describe("readOnly", () => {
    it("renders no draggable cards or accessible status selects", () => {
      vi.mocked(useRouter).mockReturnValue({ refresh: vi.fn() } as unknown as ReturnType<
        typeof useRouter
      >);
      const pedido = fixturePedido();
      render(<KanbanBoard initialPedidos={[pedido]} statuses={statuses} readOnly />);

      expect(screen.getByText("PED-000001").closest('[draggable="true"]')).toBeNull();
      expect(screen.queryByRole("combobox")).not.toBeInTheDocument();
    });

    it("dropping a card never calls moveStatus", async () => {
      vi.mocked(useRouter).mockReturnValue({ refresh: vi.fn() } as unknown as ReturnType<
        typeof useRouter
      >);
      const pedido = fixturePedido();
      render(<KanbanBoard initialPedidos={[pedido]} statuses={statuses} readOnly />);

      const targetColumn = screen.getByText("Em análise").closest("div")!.nextElementSibling!;
      const dataTransfer = createDataTransfer();
      dataTransfer.setData("text/plain", "pedido-1");
      fireEvent.drop(targetColumn, { dataTransfer });

      expect(moveStatus).not.toHaveBeenCalled();
    });

    it("uses the given linkBasePath for each card's detail link", () => {
      vi.mocked(useRouter).mockReturnValue({ refresh: vi.fn() } as unknown as ReturnType<
        typeof useRouter
      >);
      const pedido = fixturePedido();
      render(
        <KanbanBoard
          initialPedidos={[pedido]}
          statuses={statuses}
          readOnly
          linkBasePath="/gestao/pedidos"
        />,
      );

      expect(screen.getByRole("link", { name: "PED-000001" })).toHaveAttribute(
        "href",
        "/gestao/pedidos/PED-000001",
      );
    });
  });
});
