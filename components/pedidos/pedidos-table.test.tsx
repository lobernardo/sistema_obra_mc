import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { PedidosTable } from "./pedidos-table";
import type { PedidoComRelacoes } from "@/lib/types/domain";

const now = "2026-01-01T10:00:00Z";

function fixturePedido(overrides: Partial<PedidoComRelacoes> = {}): PedidoComRelacoes {
  return {
    id: "pedido-1",
    code: "PED-000001",
    obra_id: "obra-1",
    requester_id: "requester-1",
    requested_at: now,
    needed_at: "2026-02-01",
    items_description: "10 sacos de cimento",
    status_id: "status-1",
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
    ...overrides,
  };
}

describe("PedidosTable", () => {
  it("renders the identifier, obra, status, priority, responsible and previsão for each pedido", () => {
    render(<PedidosTable pedidos={[fixturePedido()]} linkBasePath="/obra" />);

    expect(screen.getByRole("link", { name: "PED-000001" })).toHaveAttribute(
      "href",
      "/obra/PED-000001",
    );
    expect(screen.getByText("Obra Central")).toBeInTheDocument();
    expect(screen.getByText("Solicitado")).toBeInTheDocument();
    expect(screen.getByText("Alta")).toBeInTheDocument();
    expect(screen.getByText("—")).toBeInTheDocument();
    expect(screen.getByText("01/02/2026")).toBeInTheDocument();
  });

  it("shows the responsible's name when one is assigned", () => {
    render(
      <PedidosTable
        pedidos={[
          fixturePedido({
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
          }),
        ]}
        linkBasePath="/obra"
      />,
    );

    expect(screen.getByText("João Suprimentos")).toBeInTheDocument();
  });

  it("shows the atraso indicator for a late pedido", () => {
    render(
      <PedidosTable pedidos={[fixturePedido({ needed_at: "2020-01-01" })]} linkBasePath="/obra" />,
    );

    expect(screen.getByText("Atrasado")).toBeInTheDocument();
  });

  it("shows 'Dentro do prazo' for a pedido that isn't late", () => {
    render(
      <PedidosTable pedidos={[fixturePedido({ needed_at: "2099-01-01" })]} linkBasePath="/obra" />,
    );

    expect(screen.getByText("Dentro do prazo")).toBeInTheDocument();
  });

  it("renders the empty state when there are no pedidos", () => {
    render(
      <PedidosTable
        pedidos={[]}
        linkBasePath="/obra"
        emptyTitle="Nenhum pedido encontrado"
        emptyDescription="Suas solicitações aparecerão aqui."
      />,
    );

    expect(screen.getByText("Nenhum pedido encontrado")).toBeInTheDocument();
    expect(screen.getByText("Suas solicitações aparecerão aqui.")).toBeInTheDocument();
    expect(screen.queryByRole("table")).not.toBeInTheDocument();
  });

  it("uses the given linkBasePath to build each detail link", () => {
    render(<PedidosTable pedidos={[fixturePedido()]} linkBasePath="/suprimentos/pedidos" />);

    expect(screen.getByRole("link", { name: "PED-000001" })).toHaveAttribute(
      "href",
      "/suprimentos/pedidos/PED-000001",
    );
  });
});
