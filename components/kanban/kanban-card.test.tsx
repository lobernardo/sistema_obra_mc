import { describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import { KanbanCard } from "./kanban-card";
import type { PedidoComRelacoes, Status } from "@/lib/types/domain";

const now = "2026-01-01T10:00:00Z";

const statuses: Status[] = [
  {
    id: "status-solicitado",
    name: "Solicitado",
    slug: "solicitado",
    description: null,
    sort_order: 1,
    is_active: true,
    created_at: now,
    updated_at: now,
  },
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
    expected_delivery_at: "2026-02-10",
    is_demo: false,
    created_at: now,
    updated_at: now,
    obra: {
      id: "obra-1",
      name: "Obra Central",
      is_active: true,
      is_demo: false,
      created_at: now,
      updated_at: now,
    },
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
      role: {
        id: "role-obra",
        name: "Obra",
        slug: "obra",
        description: null,
        is_active: true,
        created_at: now,
        updated_at: now,
      },
    },
    responsible: null,
    ...overrides,
  };
}

describe("KanbanCard", () => {
  it("shows identifier, obra, resumo, data necessária, prioridade, responsável, previsão and atraso", () => {
    const { container } = render(
      <KanbanCard
        pedido={fixturePedido({ needed_at: "2020-01-01" })}
        statuses={statuses}
        isMoving={false}
        onMove={vi.fn()}
      />,
    );

    expect(screen.getByRole("link", { name: "PED-000001" })).toHaveAttribute(
      "href",
      "/suprimentos/pedidos/PED-000001",
    );
    expect(screen.getByText("Obra Central")).toBeInTheDocument();
    expect(screen.getByText("10 sacos de cimento")).toBeInTheDocument();
    expect(container.textContent).toContain("01/01/2020");
    expect(screen.getByText("Alta")).toBeInTheDocument();
    expect(screen.getByText("Sem responsável")).toBeInTheDocument();
    expect(container.textContent).toContain("10/02/2026");
    expect(screen.getByText("Atrasado")).toBeInTheDocument();
  });

  it("shows the responsible's name when assigned", () => {
    render(
      <KanbanCard
        pedido={fixturePedido({
          responsible: {
            id: "resp-1",
            full_name: "João Suprimentos",
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
          },
        })}
        statuses={statuses}
        isMoving={false}
        onMove={vi.fn()}
      />,
    );

    expect(screen.getByText("João Suprimentos")).toBeInTheDocument();
  });

  it("is draggable", () => {
    render(
      <KanbanCard pedido={fixturePedido()} statuses={statuses} isMoving={false} onMove={vi.fn()} />,
    );

    expect(screen.getByText("PED-000001").closest('[draggable="true"]')).not.toBeNull();
  });

  it("uses the given linkBasePath for the detail link", () => {
    render(
      <KanbanCard
        pedido={fixturePedido()}
        statuses={statuses}
        isMoving={false}
        onMove={vi.fn()}
        linkBasePath="/gestao/pedidos"
      />,
    );

    expect(screen.getByRole("link", { name: "PED-000001" })).toHaveAttribute(
      "href",
      "/gestao/pedidos/PED-000001",
    );
  });

  describe("readOnly", () => {
    it("renders no accessible status select", () => {
      render(
        <KanbanCard
          pedido={fixturePedido()}
          statuses={statuses}
          isMoving={false}
          onMove={vi.fn()}
          readOnly
        />,
      );

      expect(screen.queryByRole("combobox")).not.toBeInTheDocument();
    });

    it("is not draggable", () => {
      render(
        <KanbanCard
          pedido={fixturePedido()}
          statuses={statuses}
          isMoving={false}
          onMove={vi.fn()}
          readOnly
        />,
      );

      expect(screen.getByText("PED-000001").closest('[draggable="true"]')).toBeNull();
    });

    it("still shows identifier, obra and atraso condition", () => {
      render(
        <KanbanCard
          pedido={fixturePedido({ needed_at: "2020-01-01" })}
          statuses={statuses}
          isMoving={false}
          onMove={vi.fn()}
          readOnly
        />,
      );

      expect(screen.getByText("PED-000001")).toBeInTheDocument();
      expect(screen.getByText("Obra Central")).toBeInTheDocument();
      expect(screen.getByText("Atrasado")).toBeInTheDocument();
    });
  });
});
