import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { AtrasoIndicator } from "./atraso-indicator";

describe("AtrasoIndicator", () => {
  it("renders the marker when given atrasado=true directly", () => {
    render(<AtrasoIndicator atrasado />);
    expect(screen.getByText("Atrasado")).toBeInTheDocument();
  });

  it("renders nothing when given atrasado=false directly", () => {
    const { container } = render(<AtrasoIndicator atrasado={false} />);
    expect(container).toBeEmptyDOMElement();
  });

  it("computes atraso from a pedido whose needed_at has passed and isn't entregue/cancelado", () => {
    render(
      <AtrasoIndicator
        pedido={{ needed_at: "2020-01-01", status: { slug: "em_analise" } }}
        today={new Date("2026-01-01T00:00:00Z")}
      />,
    );
    expect(screen.getByText("Atrasado")).toBeInTheDocument();
  });

  it("never marks entregue or cancelado pedidos as atrasado, even with a past needed_at", () => {
    const { container: entregue } = render(
      <AtrasoIndicator
        pedido={{ needed_at: "2020-01-01", status: { slug: "entregue" } }}
        today={new Date("2026-01-01T00:00:00Z")}
      />,
    );
    expect(entregue).toBeEmptyDOMElement();

    const { container: cancelado } = render(
      <AtrasoIndicator
        pedido={{ needed_at: "2020-01-01", status: { slug: "cancelado" } }}
        today={new Date("2026-01-01T00:00:00Z")}
      />,
    );
    expect(cancelado).toBeEmptyDOMElement();
  });
});
