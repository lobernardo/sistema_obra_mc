import { describe, expect, it } from "vitest";
import { isPedidoAtrasado } from "./atraso";

const TODAY = new Date("2026-09-16T12:00:00Z");
const PAST = "2026-09-01";
const FUTURE = "2026-09-30";
const SAME_DAY = "2026-09-16";

describe("isPedidoAtrasado", () => {
  it("is true when needed_at has passed and the status is active", () => {
    expect(isPedidoAtrasado({ needed_at: PAST, status: { slug: "em_analise" } }, TODAY)).toBe(true);
  });

  it("is false when needed_at has passed but the status is entregue", () => {
    expect(isPedidoAtrasado({ needed_at: PAST, status: { slug: "entregue" } }, TODAY)).toBe(false);
  });

  it("is false when needed_at has passed but the status is cancelado", () => {
    expect(isPedidoAtrasado({ needed_at: PAST, status: { slug: "cancelado" } }, TODAY)).toBe(false);
  });

  it("is false when needed_at is today, regardless of status", () => {
    expect(isPedidoAtrasado({ needed_at: SAME_DAY, status: { slug: "solicitado" } }, TODAY)).toBe(
      false,
    );
  });

  it("is false when needed_at is in the future, regardless of status", () => {
    expect(isPedidoAtrasado({ needed_at: FUTURE, status: { slug: "solicitado" } }, TODAY)).toBe(
      false,
    );
  });
});
