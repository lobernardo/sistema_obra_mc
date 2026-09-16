import { describe, expect, it } from "vitest";
import { getNavItems } from "./nav-config";

describe("getNavItems", () => {
  it("scopes Obra to Nova Solicitação and Meus Pedidos", () => {
    expect(getNavItems("obra").map((item) => item.label)).toEqual([
      "Nova Solicitação",
      "Meus Pedidos",
    ]);
  });

  it("scopes Suprimentos to Kanban and Todos os Pedidos", () => {
    expect(getNavItems("suprimentos").map((item) => item.label)).toEqual([
      "Kanban",
      "Todos os Pedidos",
    ]);
  });

  it("scopes Gestão to Dashboard, Kanban and Todos os Pedidos", () => {
    expect(getNavItems("gestao").map((item) => item.label)).toEqual([
      "Dashboard",
      "Kanban",
      "Todos os Pedidos",
    ]);
  });

  it("never leaks another role's items", () => {
    const obraLabels = getNavItems("obra").map((item) => item.label);
    const suprimentosLabels = getNavItems("suprimentos").map((item) => item.label);
    const gestaoLabels = getNavItems("gestao").map((item) => item.label);

    expect(obraLabels).not.toContain("Kanban");
    expect(obraLabels).not.toContain("Dashboard");
    expect(suprimentosLabels).not.toContain("Nova Solicitação");
    expect(suprimentosLabels).not.toContain("Dashboard");
    expect(gestaoLabels).not.toContain("Nova Solicitação");
  });
});
