import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { EmptyState } from "./empty-state";

describe("EmptyState", () => {
  it("renders the configured title", () => {
    render(<EmptyState title="Nenhum pedido encontrado" />);
    expect(screen.getByText("Nenhum pedido encontrado")).toBeInTheDocument();
  });

  it("renders an optional contextual description", () => {
    render(
      <EmptyState
        title="Nenhum pedido encontrado"
        description="Ajuste os filtros ou aguarde novas solicitações."
      />,
    );
    expect(
      screen.getByText("Ajuste os filtros ou aguarde novas solicitações."),
    ).toBeInTheDocument();
  });

  it("omits the description when none is given", () => {
    render(<EmptyState title="Nenhum pedido encontrado" />);
    expect(screen.queryByText(/ajuste/i)).not.toBeInTheDocument();
  });
});
