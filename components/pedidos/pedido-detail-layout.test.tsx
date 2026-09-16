import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { PedidoDetailLayout } from "./pedido-detail-layout";
import type { PedidoComHistorico } from "@/lib/types/domain";

const now = "2026-01-01T10:00:00Z";

function fixturePedido(overrides: Partial<PedidoComHistorico> = {}): PedidoComHistorico {
  return {
    id: "pedido-1",
    code: "PED-000001",
    obra_id: "obra-1",
    requester_id: "requester-1",
    requested_at: now,
    needed_at: "2026-02-01",
    items_description: "10 sacos de cimento\n5 baldes de tinta branca",
    status_id: "status-1",
    priority_id: "priority-1",
    responsible_id: null,
    expected_delivery_at: null,
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
    status: {
      id: "status-1",
      name: "Solicitado",
      slug: "solicitado",
      description: null,
      sort_order: 1,
      is_active: true,
      created_at: now,
      updated_at: now,
    },
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
    events: [],
    ...overrides,
  };
}

describe("PedidoDetailLayout", () => {
  it("always shows the Solicitação fields as plain text", () => {
    render(<PedidoDetailLayout pedido={fixturePedido()} readOnly />);

    expect(screen.getByText("PED-000001")).toBeInTheDocument();
    expect(screen.getByText("Obra Central")).toBeInTheDocument();
    expect(screen.getByText("Maria Obra")).toBeInTheDocument();
    expect(screen.getByText(/10 sacos de cimento/)).toBeInTheDocument();
  });

  it("in readOnly mode never renders the passed edit controls", () => {
    render(
      <PedidoDetailLayout
        pedido={fixturePedido()}
        readOnly
        statusControl={<button>Editar status</button>}
        priorityControl={<button>Editar prioridade</button>}
        responsibleControl={<button>Editar responsável</button>}
        previsaoControl={<button>Editar previsão</button>}
      />,
    );

    expect(screen.queryByRole("button")).not.toBeInTheDocument();
    expect(screen.getByText("Solicitado")).toBeInTheDocument();
    expect(screen.getByText("Alta")).toBeInTheDocument();
  });

  it("when not readOnly, renders the given edit controls instead of the static badges", () => {
    render(
      <PedidoDetailLayout
        pedido={fixturePedido()}
        readOnly={false}
        statusControl={<button>Editar status</button>}
      />,
    );

    expect(screen.getByRole("button", { name: "Editar status" })).toBeInTheDocument();
  });

  it("falls back to static display when not readOnly but no control was passed", () => {
    render(<PedidoDetailLayout pedido={fixturePedido()} readOnly={false} />);
    expect(screen.getByText("Solicitado")).toBeInTheDocument();
  });

  it("shows the atraso condition when the pedido is late", () => {
    render(<PedidoDetailLayout pedido={fixturePedido({ needed_at: "2020-01-01" })} readOnly />);
    expect(screen.getByText("Atrasado")).toBeInTheDocument();
  });

  it("shows 'Dentro do prazo' when the pedido is not late", () => {
    render(<PedidoDetailLayout pedido={fixturePedido({ needed_at: "2099-01-01" })} readOnly />);
    expect(screen.getByText("Dentro do prazo")).toBeInTheDocument();
  });

  it("renders the history timeline in the Histórico section", () => {
    render(
      <PedidoDetailLayout
        pedido={fixturePedido({
          events: [
            {
              id: "event-1",
              pedido_id: "pedido-1",
              event_type_id: "event-type-1",
              previous_value: null,
              new_value: null,
              actor_id: "requester-1",
              created_at: now,
              eventType: {
                id: "event-type-1",
                name: "Criação do pedido",
                slug: "criacao_pedido",
                description: null,
                is_active: true,
                created_at: now,
                updated_at: now,
              },
              actor: {
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
            },
          ],
        })}
        readOnly
      />,
    );

    expect(screen.getByText("Criação do pedido")).toBeInTheDocument();
  });
});
