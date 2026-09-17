import { describe, expect, it } from "vitest";
import { parsePedidoFilters } from "./filters";

describe("parsePedidoFilters", () => {
  it("maps a single filter straight through", () => {
    expect(parsePedidoFilters({ obraId: "obra-1" })).toMatchObject({ obraId: "obra-1" });
  });

  it("combines two or more filters simultaneously", () => {
    const filters = parsePedidoFilters({ obraId: "obra-1", statusId: "status-1" });

    expect(filters).toMatchObject({ obraId: "obra-1", statusId: "status-1" });
  });

  it("treats an empty string as no filter", () => {
    const filters = parsePedidoFilters({ obraId: "" });

    expect(filters.obraId).toBeUndefined();
  });

  it("takes the first value when a key repeats as an array", () => {
    const filters = parsePedidoFilters({ obraId: ["obra-1", "obra-2"] });

    expect(filters.obraId).toBe("obra-1");
  });

  it("parses atrasado=true and atrasado=false into booleans", () => {
    expect(parsePedidoFilters({ atrasado: "true" }).atrasado).toBe(true);
    expect(parsePedidoFilters({ atrasado: "false" }).atrasado).toBe(false);
  });

  it("leaves atrasado undefined when absent or unrecognized", () => {
    expect(parsePedidoFilters({}).atrasado).toBeUndefined();
    expect(parsePedidoFilters({ atrasado: "all" }).atrasado).toBeUndefined();
  });

  it("returns no filters at all when given an empty object", () => {
    const filters = parsePedidoFilters({});

    expect(filters).toEqual({
      obraId: undefined,
      responsibleId: undefined,
      priorityId: undefined,
      statusId: undefined,
      requestedFrom: undefined,
      requestedTo: undefined,
      neededAtFrom: undefined,
      neededAtTo: undefined,
      search: undefined,
      atrasado: undefined,
    });
  });
});
