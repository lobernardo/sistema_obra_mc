import { loadEnvConfig } from "@next/env";
import { afterEach } from "vitest";
import { cleanup } from "@testing-library/react";
import "@testing-library/jest-dom/vitest";

loadEnvConfig(process.cwd());

// vitest.config.mts doesn't enable `test.globals`, so React Testing
// Library's auto-cleanup (which relies on a global `afterEach`) never
// registers itself — wire it up explicitly so component trees don't leak
// between test cases.
afterEach(() => {
  cleanup();
});
