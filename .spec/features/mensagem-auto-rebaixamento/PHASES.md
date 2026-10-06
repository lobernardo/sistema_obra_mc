# Phases: mensagem-auto-rebaixamento

Gerado por /plan a partir de PLAN.md — view executável para `./ralph.sh .spec/features/mensagem-auto-rebaixamento/PHASES.md`.

## Phase 1: Mensagem PT-BR na autoalteração de perfil e página 403 própria

Antes de implementar, leia:
1. `.spec/features/mensagem-auto-rebaixamento/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/mensagem-auto-rebaixamento/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T01 — `UserPolicy::changeRole` nega a própria conta com mensagem PT-BR
      Arquivos: `app/Policies/UserPolicy.php`, `tests/Feature/Authorization/UserPolicyTest.php`, `tests/Feature/Livewire/UsuariosFormTest.php`
      Mudança: `changeRole` passa a retornar `Illuminate\Auth\Access\Response`: sem `manage-users` → `Response::deny()` (checado primeiro); própria conta → `Response::deny(self::SELF_ROLE_CHANGE_DENIED_MESSAGE)` com a constante `'Não é possível regredir próprio acesso. Solicite à gestão!'`; senão `Response::allow()`. `deactivate`, demais abilities, `Form.php`, `UpdateUserAction` e `GuardsGestaoLockout` não mudam. Adicionar testes RF-01/RF-02/RF-05 via `Gate::forUser(...)->inspect(...)` e RF-03/RF-04 via `Livewire::test(Form::class, ['user' => ...])`. Rodar `vendor/bin/pint --dirty --format agent`.
      Cobre: RF-01, RF-02, RF-03, RF-04, RF-05, RNF-01, RNF-02
      Acceptance criteria: `inspect('changeRole', $gestao)` do próprio gestao é negado com a mensagem exata; `obra`/`suprimentos` negados (alvo próprio e alheio) com mensagem diferente; `inspect('deactivate', $gestao)` negado sem a mensagem nova e `deactivate` continua `bool`; salvar o próprio perfil no Form → `assertForbidden()`, `role_id` inalterado e nenhuma linha nova em `user_admin_events`; gestao muda `obra`→`suprimentos` de outro usuário com redirect para `gestao.usuarios.index`; teste existente `UserPolicyTest.php:50-54` sem alteração; `composer.json`/`package.json` sem diff.
      Testes: `php artisan test --compact tests/Feature/Authorization/UserPolicyTest.php tests/Feature/Livewire/UsuariosFormTest.php tests/Feature/Actions/Usuarios/GestaoLockoutGuardTest.php`
- [ ] T02 — Página 403 própria em PT-BR com "Voltar"
      Arquivos: `resources/views/errors/403.blade.php` (novo), `tests/Feature/Http/ForbiddenPageTest.php` (novo, `php artisan make:test --pest Http/ForbiddenPageTest --no-interaction`)
      Mudança: view HTML autônoma (sem estender `layouts/app`, sem `<aside`), `@vite(['resources/css/app.css'])`, título PT-BR com `config('app.name')`, fundo branco e conteúdo centralizado com classes Tailwind literais; `@php` define `$message` = mensagem trimada de `$exception`, trocada por `Você não tem permissão para acessar esta página.` quando vazia ou igual a `This action is unauthorized.`; exibida só com `{{ $message }}`; link `<a href="{{ route('home') }}" target="_top">Voltar</a>` sem `history.back()`/`javascript:`. Testes: GET de `obra` em `/gestao/usuarios` e a negação de RF-03 (pela resposta do Livewire ou, se o corpo não for exposto, por uma rota-sonda de teste que chama `Gate::authorize('changeRole', auth()->user())`), mais varredura do arquivo. Rodar `vendor/bin/pint --dirty --format agent`.
      Cobre: UI-01, UI-02, UI-03, RNF-01, RNF-02
      Acceptance criteria: o 403 de `obra` em `/gestao/usuarios` contém `Você não tem permissão para acessar esta página.`, `Voltar` e `href="<route('home')>"`, e não contém `This action is unauthorized.` nem `Forbidden`; o 403 da autoalteração de perfil contém `Não é possível regredir próprio acesso. Solicite à gestão!`, `Voltar` e o mesmo `href`; o arquivo da view não contém `{!!`, `<aside`, `Albuquerque Engenharia`, `history.back` nem `javascript:`; `BladeEscapingTest.php` e `BrandIdentityComplianceTest.php` passam sem alteração.
      Testes: `php artisan test --compact tests/Feature/Http/ForbiddenPageTest.php tests/Feature/Security/BladeEscapingTest.php tests/Feature/Compliance/BrandIdentityComplianceTest.php tests/Feature/Livewire/UsuariosIndexTest.php tests/Feature/Livewire/UsuariosFormTest.php`
