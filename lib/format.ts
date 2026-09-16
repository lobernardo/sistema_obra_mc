/**
 * Date-only columns (`needed_at`, `expected_delivery_at`) are Postgres
 * `date` values like `"2026-09-20"`. Parsing that through `new Date(...)`
 * yields UTC midnight, so formatting must stay pinned to UTC too — otherwise
 * a negative-offset timezone would render the previous day.
 */
const dateFormatter = new Intl.DateTimeFormat("pt-BR", { dateStyle: "short", timeZone: "UTC" });

/**
 * Fixed to the business timezone (not the host's) so a date/time reads the
 * same in a demo regardless of where the server happens to run.
 */
const dateTimeFormatter = new Intl.DateTimeFormat("pt-BR", {
  dateStyle: "short",
  timeStyle: "short",
  timeZone: "America/Sao_Paulo",
});

export function formatDate(value: string | null | undefined): string {
  if (!value) {
    return "—";
  }

  return dateFormatter.format(new Date(value));
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) {
    return "—";
  }

  return dateTimeFormatter.format(new Date(value));
}
