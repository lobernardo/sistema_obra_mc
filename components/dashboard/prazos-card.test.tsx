import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { PrazosCard } from "./prazos-card";
import type { PrazoCount } from "@/lib/pedidos/dashboard";

describe("PrazosCard", () => {
  it("shows the count for each prazo situation", () => {
    const prazos: PrazoCount[] = [
      { situacao: "dentro_do_prazo", count: 4 },
      { situacao: "vencendo_em_breve", count: 2 },
      { situacao: "atrasado", count: 1 },
    ];

    render(<PrazosCard prazos={prazos} />);

    expect(screen.getByText("Dentro do prazo")).toBeInTheDocument();
    expect(screen.getByText("4")).toBeInTheDocument();
    expect(screen.getByText("Vencendo em breve")).toBeInTheDocument();
    expect(screen.getByText("2")).toBeInTheDocument();
    expect(screen.getByText("Atrasado")).toBeInTheDocument();
    expect(screen.getByText("1")).toBeInTheDocument();
  });
});
