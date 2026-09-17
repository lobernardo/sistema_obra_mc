import { describe, expect, it } from "vitest";
import { isPedidoPendente } from "./pendente";

describe("isPedidoPendente", () => {
  it("is true for every active status", () => {
    for (const slug of ["solicitado", "em_analise", "em_compra_preparacao", "aguardando_entrega"]) {
      expect(isPedidoPendente({ status: { slug } })).toBe(true);
    }
  });

  it("is false when the status is entregue", () => {
    expect(isPedidoPendente({ status: { slug: "entregue" } })).toBe(false);
  });

  it("is false when the status is cancelado", () => {
    expect(isPedidoPendente({ status: { slug: "cancelado" } })).toBe(false);
  });
});
