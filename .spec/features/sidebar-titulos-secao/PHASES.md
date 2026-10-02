# Phases: sidebar-titulos-secao

Gerado por /plan a partir de PLAN.md — view executável para `./ralph.sh .spec/features/sidebar-titulos-secao/PHASES.md`.

## Phase 1: Títulos de seção da sidebar com token secondary

Antes de implementar, leia:
1. `.spec/features/sidebar-titulos-secao/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/sidebar-titulos-secao/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T01 — Restilizar `.sidebar-group-label` com o token secondary e separador
      Arquivos: `resources/css/app.css`
      Mudança: trocar o `@apply` de `.sidebar-group-label` por classes literais como `mt-2 border-t border-border px-3 pt-4 pb-1 text-[11px] font-bold tracking-wider text-secondary uppercase`; não tocar `.sidebar-link`/`.sidebar-link-active`, `resources/views/layouts/app.blade.php` nem `app/Support/SidebarNavigation.php`.
      Cobre: UI-01, UI-02, UI-03, RNF-01, RNF-02
      Acceptance criteria: a regra `.sidebar-group-label` contém `text-secondary`, `text-[11px]`, `font-bold`, `tracking-wider`, `uppercase`, `border-t`, `border-border` e não contém `text-text-muted`, `primary`, `hover:`, `cursor-pointer` nem `underline`; `layouts/app.blade.php`, `SidebarNavigation.php` e `composer.json`/`package.json` sem diff.
      Testes: `tests/Feature/Livewire/SidebarNavigationTest.php` e `tests/Feature/Authorization/SidebarNavigationCatalogueTest.php` verdes sem modificação
- [ ] T02 — Atualizar o teste de tema de `.sidebar-group-label`
      Arquivos: `tests/Feature/Design/ThemeTokensTest.php`
      Mudança: no teste de `.sidebar-group-label` (linhas 212-214), remover a exigência de `text-text-muted` e afirmar `toContain('text-secondary')`, `not->toContain('primary')` e `not->toContain('hover:')` sobre `componentRule('.sidebar-group-label')`; renomear o teste de acordo.
      Cobre: RF-01, UI-02
      Acceptance criteria: o teste afirma `text-secondary` presente e `primary`/`hover:` ausentes, não exige mais `text-text-muted`, e `php artisan test --compact tests/Feature/Design/ThemeTokensTest.php` passa.
      Testes: `tests/Feature/Design/ThemeTokensTest.php` — `.sidebar-group-label uses the secondary token and is not styled as a link`
