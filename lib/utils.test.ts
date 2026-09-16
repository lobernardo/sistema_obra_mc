import { describe, expect, it } from "vitest";
import { cn } from "./utils";

describe("cn", () => {
  it("merges class names, dropping falsy values", () => {
    expect(cn("a", false && "b", "c")).toBe("a c");
  });

  it("merges conflicting tailwind classes, keeping the last one", () => {
    expect(cn("p-2", "p-4")).toBe("p-4");
  });
});
