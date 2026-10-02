# Confirmed input — sidebar-titulos-secao (tier: light)

Summary: visually differentiate sidebar section titles (OPERAÇÃO, NOTIFICAÇÕES, CADASTROS, ADMINISTRAÇÃO) from clickable links. Visual-only change.

Evidence: titles render as `<p class="sidebar-group-label">` in resources/views/layouts/app.blade.php:113-115; rule in resources/css/app.css:182-184 (`px-3 pt-4 pb-1 text-xs font-semibold tracking-wide text-text-muted uppercase`), same gray as `.sidebar-link` (app.css:174-176). tests/Feature/Design/ThemeTokensTest.php:212-213 currently requires `text-text-muted`.

Confirmed ACs:
1. `.sidebar-group-label` uses `text-secondary` (#520C1F, wine), smaller bold text (e.g. text-[11px] font-bold), wider letter spacing (tracking-wider), a discreet top separator (border-t border-border) between sections; no hover, no cursor/link/button appearance.
2. Primary red stays reserved for the active item and "+ Nova Solicitação"; labels never use `primary`.
3. Titles remain non-interactive `<p>` elements (never `<a>`/`<button>`); sidebar order, items, routes, authorization and `SidebarNavigation` catalogue unchanged.
4. ThemeTokensTest updated to require `text-secondary` (and no `primary`, no `hover:`) on `.sidebar-group-label`; existing SidebarNavigation* tests stay green unchanged.
5. Tailwind classes literal (no interpolation); no new dependency; Pint/no PHP change expected.
