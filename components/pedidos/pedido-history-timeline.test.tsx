import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { PedidoHistoryTimeline } from "./pedido-history-timeline";
import type { PedidoEventComRelacoes } from "@/lib/types/domain";

function fixtureEvent(overrides: Partial<PedidoEventComRelacoes> = {}): PedidoEventComRelacoes {
  return {
    id: "event-1",
    pedido_id: "pedido-1",
    event_type_id: "event-type-1",
    previous_value: null,
    new_value: null,
    actor_id: "actor-1",
    created_at: "2026-01-01T10:00:00Z",
    eventType: {
      id: "event-type-1",
      name: "Criação do pedido",
      slug: "criacao_pedido",
      description: null,
      is_active: true,
      created_at: "2026-01-01T10:00:00Z",
      updated_at: "2026-01-01T10:00:00Z",
    },
    actor: {
      id: "actor-1",
      full_name: "Maria Obra",
      role_id: "role-1",
      is_active: true,
      is_demo: false,
      created_at: "2026-01-01T10:00:00Z",
      updated_at: "2026-01-01T10:00:00Z",
      role: {
        id: "role-1",
        name: "Obra",
        slug: "obra",
        description: null,
        is_active: true,
        created_at: "2026-01-01T10:00:00Z",
        updated_at: "2026-01-01T10:00:00Z",
      },
    },
    ...overrides,
  };
}

describe("PedidoHistoryTimeline", () => {
  it("renders each event's type, values and author", () => {
    render(
      <PedidoHistoryTimeline
        events={[
          fixtureEvent({
            id: "event-2",
            previous_value: "solicitado",
            new_value: "em_analise",
            eventType: {
              id: "event-type-2",
              name: "Mudança de status",
              slug: "mudanca_status",
              description: null,
              is_active: true,
              created_at: "2026-01-02T10:00:00Z",
              updated_at: "2026-01-02T10:00:00Z",
            },
            created_at: "2026-01-02T10:00:00Z",
          }),
        ]}
      />,
    );

    expect(screen.getByText("Mudança de status")).toBeInTheDocument();
    expect(screen.getByText("solicitado → em_analise")).toBeInTheDocument();
    expect(screen.getByText("Maria Obra")).toBeInTheDocument();
  });

  it("renders events in chronological order regardless of input order", () => {
    const first = fixtureEvent({
      id: "event-1",
      created_at: "2026-01-01T10:00:00Z",
      eventType: {
        id: "event-type-1",
        name: "Criação do pedido",
        slug: "criacao_pedido",
        description: null,
        is_active: true,
        created_at: "2026-01-01T10:00:00Z",
        updated_at: "2026-01-01T10:00:00Z",
      },
    });
    const second = fixtureEvent({
      id: "event-2",
      created_at: "2026-01-02T10:00:00Z",
      eventType: {
        id: "event-type-2",
        name: "Mudança de status",
        slug: "mudanca_status",
        description: null,
        is_active: true,
        created_at: "2026-01-02T10:00:00Z",
        updated_at: "2026-01-02T10:00:00Z",
      },
    });

    render(<PedidoHistoryTimeline events={[second, first]} />);

    const items = screen.getAllByRole("listitem");
    expect(items[0]).toHaveTextContent("Criação do pedido");
    expect(items[1]).toHaveTextContent("Mudança de status");
  });

  it("shows an em dash for values that are absent (e.g. criação do pedido)", () => {
    render(<PedidoHistoryTimeline events={[fixtureEvent()]} />);
    expect(screen.getByText("— → —")).toBeInTheDocument();
  });

  it("shows an empty state when there is no history", () => {
    render(<PedidoHistoryTimeline events={[]} />);
    expect(screen.getByText("Nenhum evento registrado")).toBeInTheDocument();
  });
});
