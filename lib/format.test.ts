import { describe, expect, it } from "vitest";
import { formatDate, formatDateTime } from "./format";

describe("formatDate", () => {
  it("formats a date-only string as pt-BR short date", () => {
    expect(formatDate("2026-09-20")).toBe("20/09/2026");
  });

  it("never shifts a day across timezones (pinned to UTC)", () => {
    expect(formatDate("2026-01-01")).toBe("01/01/2026");
  });

  it.each([null, undefined, ""])("renders %s as an em dash", (value) => {
    expect(formatDate(value)).toBe("—");
  });
});

describe("formatDateTime", () => {
  it("formats a timestamptz string as pt-BR short date and time", () => {
    expect(formatDateTime("2026-09-20T14:30:00Z")).toBe("20/09/2026, 11:30");
  });

  it.each([null, undefined, ""])("renders %s as an em dash", (value) => {
    expect(formatDateTime(value)).toBe("—");
  });
});
