import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { IndicatorCard } from "./indicator-card";

describe("IndicatorCard", () => {
  it("shows the title and value", () => {
    render(<IndicatorCard title="Volume total" value={42} />);

    expect(screen.getByText("Volume total")).toBeInTheDocument();
    expect(screen.getByText("42")).toBeInTheDocument();
  });

  it("renders as plain content with no link when href is omitted", () => {
    render(<IndicatorCard title="Volume total" value={42} />);

    expect(screen.queryByRole("link")).not.toBeInTheDocument();
  });

  it("renders as a drill-down link when href is given", () => {
    render(<IndicatorCard title="Atrasados" value={7} href="/gestao/pedidos?atrasado=true" />);

    expect(screen.getByRole("link")).toHaveAttribute("href", "/gestao/pedidos?atrasado=true");
  });

  it("shows the optional description", () => {
    render(<IndicatorCard title="Pendentes" value={3} description="Não entregues nem cancelados" />);

    expect(screen.getByText("Não entregues nem cancelados")).toBeInTheDocument();
  });
});
