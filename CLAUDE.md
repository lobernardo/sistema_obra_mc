<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>

<!-- ===================================================================== -->
<!-- Seções abaixo geradas em 2026-09-21 por leitura direta do código,     -->
<!-- migrations, testes, configuração e do serviço Railway (somente leitura). -->
<!-- Convenção: "não implementado" = ausência confirmada no código;          -->
<!-- "não verificado" = não foi possível confirmar nesta sessão.             -->
<!-- ===================================================================== -->

# Sistema de Solicitações e Compras — Albuquerque Engenharia (V0 Laravel)

## 1. Objetivo

Sistema interno que centraliza, padroniza e rastreia solicitações de compra originadas por obras de construção (`docs/product/PRD-V1.md` §1–§3). A Obra registra uma necessidade em um formulário simples (obra, data necessária, itens em texto livre); o sistema a converte em um **pedido rastreável** com código único; Suprimentos conduz o pedido por um workflow fixo até a entrega; toda mutação relevante vira um evento imutável no histórico; Gestão lê indicadores consolidados e administra usuários (`docs/agents/project_overview.md`, seção "Purpose").

Dores resolvidas (PRD §2): saber o que foi pedido, para qual obra, quando, para quando, quem é o responsável, prioridade, estágio, previsão, atraso e histórico. Fora de escopo por decisão de produto (PRD §5, §40): ERP, fornecedores, cotação, SKU/catálogo, financeiro, aprovações hierárquicas, entrega parcial, notificações externas além dos e-mails de acesso, anexos, comentários.

Três papéis (`app/Enums/RoleSlug.php:7-10`): `obra`, `suprimentos`, `gestao`. Milestone atual: **V0 Demo em produção** (PRD §35), com identidade visual "Albuquerque Engenharia" e módulo de administração de usuários adicionados posteriormente (`.spec/features/ajustes-finais-albuquerque/SPEC.md`).

## 2. Arquitetura em produção

### Stack (versões travadas em `composer.lock` / `node_modules`)

| Camada | Pacote | Versão instalada | Restrição declarada |
|---|---|---|---|
| Runtime | PHP | **8.4.25 em produção** (FrankenPHP, log de deploy 2026-09-21); 8.5.4 no ambiente local | `composer.json:9` → `^8.4` |
| Framework | laravel/framework | v13.32.0 | `^13.17` |
| UI reativa | livewire/livewire | v4.4.5 | `^4.4` |
| E-mail | resend/resend-php | v1.15.0 | `^1.15` |
| REPL | laravel/tinker | v3.0.2 | `^3.0` |
| Testes | pestphp/pest 4.7.8, pest-plugin-laravel 4.1.0, pest-plugin-browser 4.3.1, phpunit 12.5.33 | dev | |
| Lint | laravel/pint v1.32.1 | dev | |
| Agente | laravel/boost v2.9.1 | dev | |
| Build front | vite 8.3.0, tailwindcss 4.3.3, @tailwindcss/vite 4.3.3, laravel-vite-plugin 3.2.0 | `package.json:10-17` | |
| E2E | playwright 1.59.1 (npm) | `package.json:14` | |
| Banco | PostgreSQL — único driver suportado (`config/database.php:20` default `pgsql`; `.env.example:49`) | Railway: serviço `Postgres` | |

Divergência de versão: o bloco `<laravel-boost-guidelines>` acima diz "PHP 8.5"; produção roda PHP 8.4.25. Escreva código compatível com **8.4**.

Sem API JSON: não existe `routes/api.php`; só `routes/web.php` e `routes/console.php` (`bootstrap/app.php:11-15`). Toda interação passa por componentes Livewire full-page (`app/Livewire/**`) e pelo endpoint `/livewire/update`. Único controller é a base vazia `app/Http/Controllers/Controller.php`.

### Deploy (Railway) — verificado via API do Railway em 2026-09-21 (somente leitura)

| Item | Valor verificado | Fonte |
|---|---|---|
| Repositório / branch conectada | `lobernardo/sistema_obra_mc` @ `build/v0-demo-laravel`; `checkSuites: false` | `describe-service` → `source` |
| Builder | Railpack (`buildEnvironment V3`, `runtime V2`) — sem `Dockerfile`, `railway.json`, `railpack.json`, `Procfile` ou `Caddyfile` no repositório | `describe-service`; `ls` na raiz |
| Servidor | FrankenPHP (`FrankenPHP started`, `php_version 8.4.25`, 64 threads) escutando em `[::]:8080` | log de deploy `238f3223…` |
| Healthcheck | `GET /up` (registrado em `bootstrap/app.php:14`; `healthcheckPath: /up` no serviço) | ambos |
| Domínio técnico | `laravel-app-production-16ed.up.railway.app` | `describe-service` |
| Domínio custom | `albuquerque.mcinteligencia.com` → porta **8080** (registrado; status de verificação DNS/certificado **não verificado** nesta sessão) | `describe-service` → `customDomains` |
| Região / réplicas | `europe-west4-drams3a`, 1 réplica | idem |
| Proxy | TLS terminado no edge do Railway; app confia em `X-Forwarded-*` de qualquer origem (`bootstrap/app.php:22` `trustProxies(at: '*')`) | código |
| Build | Railpack executa `composer install` (inclui `require-dev`, por isso `RAILPACK_PHP_EXTENSIONS=sockets` e `PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1`), `npm ci && npm run build`, depois `php artisan config:cache`, `event:cache`, `route:cache`, `view:cache` **em tempo de build** — as variáveis precisam existir antes do build | log de build |
| Pre-Deploy Command | **Não configurado** (`deploy` do serviço não contém `preDeployCommand` nem `startCommand`) | `describe-service` |
| Migrations no deploy | Apesar de não haver Pre-Deploy Command, o script de start padrão do Railpack para Laravel executa `php artisan migrate` a cada início de container — log: `Running migrations and seeding database ... INFO Nothing to migrate.` (2026-09-21T20:21:16Z). Isso é comportamento do Railpack, **não** algo configurado no repositório nem no serviço; pode mudar com o Railpack. Se o seeder também roda nesse passo: **não verificado** (a mensagem cita "seeding", o log só mostra "Nothing to migrate"; o seed demo foi executado manualmente via `railway ssh` segundo memória de sessão) | log de deploy |
| Deploy automático | Push na branch dispara build+deploy | `source.branch`; README "Procedimento de deploy" |

Divergências do `README.md` (seção "Produção (Railway)") em relação ao serviço real: o README descreve *Pre-Deploy Command* `php artisan migrate --force` (não existe) e *Start Command* `php artisan serve --host=0.0.0.0 --port=$PORT` (não existe; o servidor é FrankenPHP do Railpack).

### Variáveis de ambiente (nomes apenas; valores nunca versionados)

Nomes presentes no serviço `laravel-app` (Railway, `variableNames`) + `.env.example`:

| Função | Variáveis | Observação |
|---|---|---|
| Identidade da app | `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_LOCALE`, `APP_FALLBACK_LOCALE` | `APP_NAME` alimenta `config('app.name')` — marca nunca hardcoded (`tests/Feature/Compliance/BrandIdentityComplianceTest.php:120`). `APP_URL` ancora os links dos e-mails (`app/Notifications/Concerns/BuildsAppUrl.php:16-19`) |
| Segredo de criptografia | `APP_KEY` | Sessões/cookies; trocar invalida sessões |
| Banco | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (alternativa: `DB_URL`) | Referências `${{Postgres.PG*}}` no Railway |
| Sessão | `SESSION_DRIVER` (=database), `SESSION_SECURE_COOKIE`, `SESSION_LIFETIME`, `SESSION_ENCRYPT`, `SESSION_DOMAIN`, `SESSION_PATH` | `config/session.php:21,35,50,159,172` |
| Cache / fila | `CACHE_STORE` (=database), `QUEUE_CONNECTION` (=database) | Fila existe só como configuração; nada é despachado |
| Log | `LOG_CHANNEL` (stderr em prod), `LOG_LEVEL`, `LOG_STACK`, `LOG_DEPRECATIONS_CHANNEL` | |
| Hash | `BCRYPT_ROUNDS` | bcrypt é o driver padrão (não há `config/hashing.php`; framework `vendor/laravel/framework/config/hashing.php:18`) |
| E-mail | `MAIL_MAILER` (`log` \| `resend`), `RESEND_API_KEY`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`; fallback SMTP: `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` | `config/mail.php:17,64-66`; `config/services.php:21` |
| Build Railpack | `RAILPACK_PHP_EXTENSIONS`, `PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD` | Só no Railway |
| Bootstrap Gestão | `GESTAO_BOOTSTRAP_PASSWORD` | Lida em runtime por `app/Console/Commands/CreateGestaoUser.php:122`; **não** deve existir no `.env` nem no Railway |
| Não usadas | `AWS_*`, `REDIS_*`, `MEMCACHED_HOST`, `BROADCAST_CONNECTION`, `FILESYSTEM_DISK` | Presentes no `.env.example` por herança do skeleton |

### O que NÃO existe em produção (confirmado)

| Ausência | Evidência |
|---|---|
| CI/CD | Sem `.github/`, `.gitlab-ci.yml`, `.circleci/`; Railway com `checkSuites: false`. Nenhum gate automático antes do deploy |
| Filas / workers | Nenhum job em `app/`; notificações não implementam `ShouldQueue` (`app/Notifications/FirstAccessInvite.php:18`, `ResetPasswordPtBr.php:18`); só 2 serviços no projeto Railway (`laravel-app`, `Postgres`) |
| Scheduler / cron | `routes/console.php` contém apenas o comando `inspire` (linhas 6-8); sem `cronSchedule` no serviço |
| Redis | Não instalado nem referenciado (cache e sessão em `database`) |
| Pre-Deploy Command | Ver tabela acima |
| Storage externo (S3) | `FILESYSTEM_DISK` não usado; sem uploads no domínio |

## 3. Regras de negócio

### Workflow de status (`app/Enums/StatusSlug.php:7-12`, seed `database/seeders/DemoSeeder.php:69-76`)

| `sort_order` | slug | Nome | Classe |
|---|---|---|---|
| 1 | `solicitado` | Solicitado | ativo (inicial) |
| 2 | `em_analise` | Em análise | ativo |
| 3 | `em_compra_preparacao` | Em compra/preparação | ativo |
| 4 | `aguardando_entrega` | Aguardando entrega | ativo |
| 5 | `entregue` | Entregue | **terminal** |
| 6 | `cancelado` | Cancelado | **terminal**; nunca é coluna do Kanban (`app/Livewire/Kanban/KanbanBoard.php:37-41`) |

`StatusSlug::activeNonFinal()` = os 4 primeiros (`:19-27`); `isTerminal()` = `entregue || cancelado` (`:29-32`). Status inicial de um pedido novo = o de menor `sort_order` (`app/Actions/Pedidos/CreatePedidoAction.php:56`).

### Transições (`app/Actions/Pedidos/UpdatePedidoStatusAction.php:32-41`)

- Alvos permitidos: qualquer status ativo **ou** `entregue` (`$allowedTargets`, linha 36). **Não há sequência obrigatória**: de qualquer status ativo pode-se ir a qualquer outro ativo, inclusive para trás, ou direto a `entregue`.
- Bloqueado: alvo igual ao atual ou alvo `cancelado` → `ValidationException` `status_id: "Transição de status inválida."` (linhas 38-41). Cancelar é caminho exclusivo de `CancelPedidoAction`.
- Evento gerado: `entrega` quando alvo é `entregue`, senão `mudanca_status` (linhas 44-46). Sempre grava `previous_value`/`new_value` = ids de status.
- Kanban: reordenar dentro da mesma coluna é ignorado antes de qualquer verificação (`KanbanBoard.php:63-65`); mover de coluna e o controle acessível "Mover para" convergem em `moveViaControl` → policy `updateStatus` → Action (`:70-77`).

### Estado terminal

- `GuardsOperationalMutation::ensurePedidoIsNotTerminal` (`app/Actions/Pedidos/Concerns/GuardsOperationalMutation.php:33-40`) roda no início das 5 Actions operacionais (status, responsável, prioridade, previsão, cancelamento). Viola → `PedidoTerminalStateException`, renderizada como **HTTP 409** com texto "Pedido em status terminal não pode ser alterado." (`app/Exceptions/Pedidos/PedidoTerminalStateException.php:24-30`).
- UI: os controles somem no detalhe de Suprimentos quando `isTerminal` (`app/Livewire/Suprimentos/PedidoDetalhe.php:127`); a proteção real é a Action (payload forjado no Kanban é coberto por `tests/Feature/Livewire/KanbanForgedMoveTest.php`).
- `cancelado` é irreversível: nenhuma Action aceita `cancelado` como origem (guard) nem como alvo de `UpdatePedidoStatusAction`.

### Cancelamento (`app/Actions/Pedidos/CancelPedidoAction.php:23-43`)

Só `suprimentos`, só pedido não terminal; muda `status_id` para `cancelado` e grava evento `cancelamento` com ids anterior/novo. Confirmação em duas etapas na UI (`Suprimentos/PedidoDetalhe.php:90-107`).

### Demais mutações operacionais (todas `suprimentos`-only, todas no-op quando o valor não muda, todas transacionais com 1 evento)

| Action | Validação | Evento |
|---|---|---|
| `UpdatePedidoResponsavelAction` (`:23-54`) | `required|integer|exists:users,id` + `ResponsibleMustBeSuprimentos` (`app/Rules/ResponsibleMustBeSuprimentos.php:23-30`) — responsável precisa ter papel `suprimentos` | `alteracao_responsavel` |
| `UpdatePedidoPrioridadeAction` (`:22-53`) | `exists:priorities,id` | `alteracao_prioridade` |
| `UpdatePedidoPrevisaoAction` (`:22-52`) | `required|date` (aceita data passada) | `alteracao_previsao` |

### Atraso, pendência e prazo (`app/Domain/Pedidos/`)

- **Atrasado** (`AtrasoClassifier.php:17-27`): status não terminal **e** `needed_at` (início do dia) `<` hoje. Mesmo pedido entregue/cancelado com data vencida → não atrasado. Versão SQL equivalente `scopeAtrasado` (`:39-47`) usada nas listagens paginadas. Calculado a cada consulta; não é coluna persistida.
- **Pendente** (`PendenteClassifier.php:16-22`): status não terminal. `scopePendente($query, bool)` (`:32-39`).
- **Prazo** (`PrazoClassifier.php:15-40`): `null` se não pendente; `atrasado` se atrasado; `vencendo_em_breve` se faltam ≤ 3 dias (`VENCENDO_EM_BREVE_DIAS = 3`, constante fixa); senão `dentro_do_prazo`.
- Dashboard (`app/Services/DashboardIndicatorsService.php`) calcula os indicadores em PHP sobre um único dataset filtrado, usando exclusivamente esses classificadores.

### Indicadores agregados (`DashboardIndicatorsService::compute()`)

`compute(array $filters): array` devolve **8 chaves**: `volumeTotal`, `pendentes`, `atrasados`, `entregues`, `entreguesHoje`, `porStatus`, `porObra`, `prazos` (array shape no PHPDoc do método). Consumido por `Gestao\Dashboard` (com filtros) e por `Suprimentos\VisaoGeral` (sem filtros).

- `entregues`: pedidos em status `entregue`, contados em PHP sobre o dataset já carregado — **nenhuma** consulta extra.
- `entreguesHoje`: pedidos entregues cujo **evento `entrega`** foi registrado hoje. A definição é o evento, nunca `expected_delivery_at` (nullable, e só Suprimentos preenche — uma regra baseada na previsão perderia entregas silenciosamente). É a **única** chave autorizada a emitir consulta própria: exatamente um `whereExists` constante sobre `pedido_events` × `event_types.slug = 'entrega'`, jamais uma consulta por pedido.
- **Ressalva operacional do `DemoSeeder`**: o seeder grava `pedido_events.created_at` com `useCurrent()`, então `entreguesHoje` mostra N no dia do seed e **0** no dia seguinte. A mitigação é rodar `php artisan db:seed` no dia da demonstração; nenhuma política de deslocamento de datas foi inventada no seeder.
- **Dívida aceita (RNF-10)**: exceto `entreguesHoje`, tudo é calculado em PHP sobre um `->get()` único. Acima de aproximadamente **5 000 pedidos** no escopo filtrado isso precisa migrar para agregação SQL (`GROUP BY` por status e por obra + predicados de data). Registrado no docblock da classe.
- O serviço é deliberadamente agnóstico de papel e **não** aplica `Pedido::visibleTo` — correto hoje porque só telas de Suprimentos e Gestão o consomem. Qualquer reuso futuro em contexto `obra` precisa adicionar o escopo antes, senão os números vazam entre obras (aviso no docblock da classe).

### Código do pedido

`PED-%06d` a partir de `nextval('pedido_code_sequence')` (`app/Services/PedidoCodeGenerator.php:15-20`); sequência criada e **reiniciada em 1** em toda migration (`database/migrations/2026_09_18_230919_create_pedido_code_sequence.php:16-17`) — atenção: `migrate` em banco com pedidos existentes reinicia a sequência e provocará colisão na `unique` de `pedidos.code`. Pedidos do seeder usam `PED-DEMO-000N` e não consomem a sequência.

### Prioridades (`app/Enums/PrioritySlug.php:7-10`, seed `DemoSeeder.php:94-97`)

`baixa` (1), `normal` (2), `alta` (3), `urgente` (4). Nullable no pedido; só Suprimentos define.

### Criação do pedido (`CreatePedidoAction.php:33-73`)

Valida `obra_id|needed_at|items_description`; rejeita `obra_id` fora de `obra_profile` do solicitante (`:50-56`) e obra Concluída — "A obra informada está inativa e não recebe novas solicitações." (`:58-62`) — com `ValidationException`, antes de consumir código; insere pedido + evento `criacao_pedido` numa única `DB::transaction`. `needed_at` aceita data passada (pedido já nasce atrasado). `requested_at` = `useCurrent()` do banco.

## 4. User stories por papel (spec `.spec/init/user-stories.md` conferida contra `routes/web.php` e `app/Livewire/`)

### Papel `obra` — rotas sob `can:is-obra`, prefixo `/obra` (`routes/web.php:91-95`)

| US | Implementação | Status |
|---|---|---|
| US-2.1 Criar solicitação | `GET /obra/nova-solicitacao` → `App\Livewire\Obra\NovaSolicitacao` (`submit()` :60-71); select de obras vem de `Auth::user()->obras()` (`:76-79`); código exibido após criar | ✅ |
| US-2.2 Não editável após envio | Nenhuma rota/Action de edição para `obra`; detalhe é read-only (`App\Livewire\Obra\PedidoDetalhe`) | ✅ |
| US-4.1 Listar pedidos da própria obra | `GET /obra/pedidos` → `Acompanhamento`: a consulta abre com `Pedido::query()->visibleTo(Auth::user())` (`:78`), paginação 10, busca textual (código/itens/obra) e filtros `obraId`, `statusId`, `atrasado` — todos `#[Url]` (`:36-46`) | ✅ |
| US-4.2 Detalhe + histórico | `GET /obra/pedidos/{pedido}` → `PedidoDetalhe::mount` chama `authorize('view')` (`:23`); eventos em ordem cronológica (`:31-38`) | ✅ |
| US-1.1 Login | Comum aos 3 papéis: `LoginForm::authenticate` (`app/Livewire/Auth/LoginForm.php:41-56`) | ✅ (ver divergências) |

### Papel `suprimentos` — `can:is-suprimentos`, prefixo `/suprimentos` (`routes/web.php:97-102`)

| US | Implementação | Status |
|---|---|---|
| US-3.1 Kanban 5 colunas | `GET /suprimentos/kanban` → `Kanban\KanbanBoard`; colunas = statuses ≠ cancelado ordenados; cancelados excluídos dos cards (`:46-52`) | ✅ |
| US-3.5 Drag-and-drop | `moveCard` via `wire:sort` (`:59-68`) | ✅ |
| US-3.6 Alternativa acessível | `moveViaControl` no Kanban (`:70-77`) e `updateStatus` no detalhe (`Suprimentos/PedidoDetalhe.php:81-88`) | ✅ |
| US-3.2/3.3/3.4 Responsável, prioridade, previsão | Só no detalhe `GET /suprimentos/pedidos/{pedido}` (`:57-79`); **não** no card do Kanban | ✅ (parcial quanto a "e/ou no card") |
| US-3.7 Marcar Entregue | Via `updateStatus`/Kanban com alvo `entregue`; permitido a partir de **qualquer** status ativo, não só de "Aguardando entrega" | ✅ (mais permissivo que a spec) |
| US-5.1 Cancelar | `cancelarPedido` (`:100-107`) | ✅ |
| US-5.2 Consultar cancelados | `GET /suprimentos/pedidos` → `TodosPedidos`: busca textual (código/itens/obra) + `atrasado`, `obraId`, **`statusId`**, `priorityId`, `responsibleId` e 2 faixas de data, todos `#[Url]` (`:38-66`). Filtrar por `Cancelado` isola os cancelados; a listagem também exibe os indicadores total/pendentes/atrasados do recorte (`indicators()` :113) | ✅ |
| PRD §26 Dashboard de Suprimentos | `GET /suprimentos/visao-geral` → `Suprimentos\VisaoGeral` (`routes/web.php:79`, nome `suprimentos.visao-geral`): 3 KPIs (volume total, atrasados, entregues hoje), contagem por status sem `cancelado`, atalho para o Kanban e as 5 solicitações mais recentes. Consome `DashboardIndicatorsService::compute([])` — nenhum indicador é reimplementado | ✅ |

### Papel `gestao` — `can:is-gestao`, prefixo `/gestao` (`routes/web.php:119-130`)

| US | Implementação | Status |
|---|---|---|
| US-7.1 Indicadores | `GET /gestao/dashboard` → `Gestao\Dashboard` + `DashboardIndicatorsService` (volume, pendentes, atrasados, **entregues**, **entregues hoje**, por status, por obra, prazos — 8 chaves) | ✅ |
| US-7.2 Filtros | período (`requested_at`), obra, status, prioridade, responsável (`Dashboard.php:28-42`) | ✅ |
| US-7.3 Drill-down | `drillDownUrl($criterion)` com `$criterion ∈ {atrasado, pendente, entregue}` (`:79`) → `/gestao/pedidos?<criterion>=true` **carregando todos os filtros ativos** do dashboard; `Gestao\TodosPedidos` resolve esses parâmetros por `#[Url]`, não em `mount()` | ✅ |
| US-7.4 Kanban leitura | `GET /gestao/kanban` → `KanbanReadOnly` sem handlers de mutação | ✅ |
| US-4.2 análogo | `GET /gestao/pedidos/{pedido}` → `Gestao\PedidoDetalhe` read-only | ✅ |
| Administração de usuários (**não está em `user-stories.md`**; vem de `.spec/features/ajustes-finais-albuquerque/SPEC.md`) | `GET /gestao/usuarios` (listar/buscar/ativar/desativar/reenviar convite), `/gestao/usuarios/novo`, `/gestao/usuarios/{user}/editar` sob `can:manage-users` (`routes/web.php:125-129`) → `Gestao\Usuarios\Index`, `Form`. O formulário aceita **0..N** obras para `obra` e `suprimentos` e proíbe obras para `gestao` (`CreateUserAction::obraIdsRules` :132-144); mudar para `gestao` faz `detach()` de todas (`UpdateUserAction.php:75`) | ✅ |

### Áreas compartilhadas `gestao` + `suprimentos` — `can:manage-obras`, sem prefixo (`routes/web.php:109-117`)

Vêm de `.spec/features/obras-associacoes-cadastro-convites/SPEC.md` (não estão em `user-stories.md`).

| Funcionalidade | Implementação | Status |
|---|---|---|
| Cadastro de obras | `GET /obras` (`obras.index` → `Obras\Index`), `/obras/nova` (`obras.create`) e `/obras/{obra}/editar` (`obras.edit`) → `Obras\Form::save()` → `CreateObraAction` / `UpdateObraAction`. Campos: Nome (trim, único sob `lower(btrim(name))` → 422 "Já existe uma obra com este nome."), Responsável (opcional; vazio → `null`) e Status `a_iniciar` \| `em_andamento` \| `concluido` (`app/Enums/ObraStatus.php`), com qualquer transição permitida. **Não há exclusão** (`ObraPolicy::delete` sempre `false`). Obra ativa = status ≠ `concluido` (`ObraStatus::isActive()`, `Obra::scopeActive`): obra Concluída não recebe pedido novo (`CreatePedidoAction.php:58`) nem convite, mas mantém pedidos, histórico e associações visíveis. Cada criação/edição grava `obra_admin_events` (só as chaves alteradas; reenvio idêntico é no-op) | ✅ |
| Associações usuário × obra (0..N) | `GET /associacoes` (`associacoes.index` → `Associacoes\Index`): localizar usuário, ver, adicionar uma ou várias obras, remover com confirmação. `AttachUserObrasAction` / `DetachUserObraAction`: alvo só `obra` ou `suprimentos` (`GuardsObraAssociationTarget`); `attach()` por id, então duplicata ou corrida na PK → 422 citando a obra; aceita obra em qualquer status; auto-associação permitida; remover não toca pedidos; auditoria `obra_access_changed` em `user_admin_events` | ✅ |
| Convites de obra | Seção "Convites" em `Obras\Form`: `generateInvitation()` → `GenerateObraInvitationAction` (422 se a obra está Concluída; token `bin2hex(random_bytes(32))`, só o SHA-256 persistido; validade **24 h** — `VALIDITY_HOURS`; link `<APP_URL>/convite#<token>` exibido **uma única vez**), listagem com estado Pendente/Expirado/Revogado/Utilizado e revogação em duas etapas → `RevokeObraInvitationAction` (UPDATE condicional; 422 se não pendente). Aceite em `/convite` (seção 5): visitante cria conta `obra` já associada à obra; conta `obra` existente faz login e confirma; outro papel → 422 | ✅ |

### Transversais

| US | Implementação |
|---|---|
| US-6.1/6.2 Histórico | `pedido_events` + `PedidoEventValuePresenter` (`app/Services/`) nos 3 detalhes; ordem `created_at, id` |
| US-8.1 Atraso consistente | Seção 3 |
| US-9.1 Dados demo | `php artisan db:seed` (`DemoSeeder`, idempotente, `[DEMO]` nos nomes) e `php artisan demo:reset --force` (seção 6) |
| Recuperação de senha / primeiro acesso (fora de `user-stories.md`) | `/esqueci-senha`, `/redefinir-senha/{token}`, `/primeiro-acesso/{token}` (`routes/web.php:57-59`) |
| Novo Cadastro público (fora de `user-stories.md`) | `GET /cadastro` (`register`, grupo `guest`, `routes/web.php:50`) → `Auth\Register`, link na tela de login. `RegisterObraUserAction` lê só nome, e-mail, senha e confirmação: papel sempre `obra`, `is_active = true`, `is_demo = false`, **zero obras** (a associação vem depois, por Gestão/Suprimentos ou por convite); e-mail duplicado → 422 "Já existe uma conta com este e-mail. Entre ou use Esqueci minha senha."; grava `account_registration_events` (origem `novo_cadastro`); depois autentica e regenera a sessão. Limitadores `register` / `register-ip`, compartilhados com a criação de conta pelo convite |
| Convite de obra (aceite) | `GET /convite` (`obra-invitation.show` → `Auth\ObraInvitationPage`) + desfechos fixos `/convite/indisponivel` (404) e `/convite/limite` (429) — `routes/web.php:39-41`; regras na seção 5 |

### Divergências spec ↔ código

1. `user-stories.md` US-1.1 exige "Supabase Auth" e "sem self-signup; usuários via seed" → implementado com guard `web`/sessão do Laravel; usuários são criados por Gestão na UI, por `users:create-gestao`, pelo Novo Cadastro público (`/cadastro`, sempre papel `obra` com zero obras) ou pelo aceite de um convite de obra.
2. US-1.2 exige isolamento "via RLS no PostgreSQL" → **não implementado**; isolamento é feito por Policy/Gate na aplicação (seção 5).
3. US-3.7 restringe "Entregue" a partir de "Aguardando entrega" → código aceita de qualquer status ativo; não há ordem obrigatória entre status ativos.
4. ~~US-5.2 / PRD §25 pedem filtro por status (e obra, responsável, prioridade) nas listagens~~ → **fechada** pela feature `paridade-demo-v0`: Suprimentos e Gestão têm obra, status, prioridade, responsável, atraso e as duas faixas de data; Obra tem um conjunto deliberadamente reduzido (obra, status, atraso — sem prioridade/responsável, que não são decisões da obra). Todo o estado de filtro é `#[Url]`.
5. PRD §7 "Editar solicitação original — Suprimentos: Sim, quando aplicável" → **não implementado**: nenhuma Action altera `obra_id`, `needed_at` ou `items_description` após a criação.
6. ~~PRD §26 dashboard de Suprimentos~~ → **fechada** pela feature `paridade-demo-v0`: `GET /suprimentos/visao-geral` (`Suprimentos\VisaoGeral`), alimentada pelo mesmo `DashboardIndicatorsService` do dashboard de Gestão.
7. ~~`docs/agents/project_overview.md` afirmava "No user/obra administration UI"~~ → **corrigida** na regeneração de 2026-09-22 (`/ai-context`). A lacuna restante (cadastro de obras) foi fechada pela feature `obras-associacoes-cadastro-convites` (`/obras`, `/associacoes`).
8. `app/Livewire/Examples/HelloWorld.php` não é roteado nem referenciado — código morto do skeleton.

## 5. Autorização — ATENÇÃO

**Este sistema NÃO usa Row Level Security do PostgreSQL.** RLS era conceito da stack anterior (Next.js + Supabase, `docs/product/PRD-V1.md` §31; `.spec/init/project-description.md:84-104`), descontinuada. Verificação: `grep -rniE "CREATE POLICY|ROW LEVEL SECURITY|rls" database/ app/ config/` → **zero ocorrências** nas migrations e no código (o único hit é a palavra "URL" em `config/app.php:49`). `tests/Feature/Compliance/NoSupabaseDependencyTest.php` garante que nenhum pacote, variável ou fonte referencie Supabase. Todo isolamento é aplicado na camada da aplicação; uma conexão direta ao banco vê todas as linhas.

### Camadas, na ordem em que uma requisição as atravessa

| # | Camada | Regra | Arquivo | Violação → |
|---|---|---|---|---|
| 1 | Middleware `guest` / `auth` | Rotas de auth (login, `/cadastro`, recuperação, primeiro acesso) só para visitantes; todo o resto exige sessão. **Exceção:** `/convite`, `/convite/indisponivel` e `/convite/limite` ficam **fora** de `guest` e de `auth` — visitante e usuário autenticado chegam à mesma página; `/convite` carrega só `active`, então um autenticado desativado é cortado ali também | `routes/web.php:39-43,62`; `app/Http/Middleware/Authenticate.php:13-16` | redirect para `login` (ou 401 JSON) |
| 2 | Middleware `active` (`EnsureUserIsActive`) | `is_active === false` (estrito) → logout, sessão invalidada, CSRF regenerado, redirect a `/login` com flash "Sua conta foi desativada. Fale com a Gestão." | `app/Http/Middleware/EnsureUserIsActive.php:30-37`; alias em `bootstrap/app.php:24-27`; também persistido para `/livewire/update` via `Livewire::addPersistentMiddleware` (`app/Providers/AppServiceProvider.php:45`) | 302 → login |
| 3 | Gates de papel (`can:` nas rotas) | `is-obra`, `is-suprimentos`, `is-gestao`, `manage-users` = comparação com `role->slug`; `manage-users` hoje equivale a `is-gestao` mas é a única abilidade que a área de usuários consulta. `manage-obras` = `gestao` **ou** `suprimentos`: única abilidade das áreas Obras, Convites e Associações, deliberadamente separada de `manage-users` (que continua só Gestão) | `AppServiceProvider.php:41-45`; `routes/web.php:91,97,109,119,125` | HTTP 403 |
| 3b | `mount()` dos componentes | Re-checa o gate de papel (`$this->authorize('is-…')`) em todo componente; `/home` faz `match` do papel e `abort(403)` para papel desconhecido (`routes/web.php:44-51`) | ex.: `Obra/Acompanhamento.php:25`, `Kanban/KanbanBoard.php:29`, `Gestao/Dashboard.php:47` | 403 |
| 4 | Policies | `PedidoPolicy::view`: `obra` só se `obra_profile` contém `pedido.obra_id`; `suprimentos`/`gestao` irrestrito; outros `false` (`app/Policies/PedidoPolicy.php:19-26`). `create`: `obra` + obra associada (`:28-32`). `setResponsavel/setPrioridade/setPrevisao/updateStatus/cancelar`: só `suprimentos` (`:34-57`). `PedidoEventPolicy::update/delete` sempre `false` (`app/Policies/PedidoEventPolicy.php:14-21`). `UserPolicy`: tudo via gate `manage-users`; `changeRole`/`deactivate` recusam a própria conta (`app/Policies/UserPolicy.php:31-44`). `ObraPolicy` (`viewAny/create/update/manageAssociations`) e `ObraInvitationPolicy` (`create/revoke`): tudo via gate `manage-obras`; `ObraPolicy::delete` sempre `false` | chamadas `authorize()` nos componentes antes de cada Action | `AuthorizationException` → 403 |
| 5 | Guards das Actions (defesa em profundidade — funcionam mesmo sem UI) | `GuardsOperationalMutation::ensureActorIsSuprimentos` (`:23-28`) + `ensurePedidoIsNotTerminal` (`:33-40`) nas 5 Actions de pedido. `GuardsUserAdministration::ensureActorManagesUsers` (`app/Actions/Usuarios/Concerns/GuardsUserAdministration.php:21-23`) nas 4 Actions de usuário. `GuardsGestaoLockout` (`GuardsGestaoLockout.php:21-53`): não desativar/mudar papel da própria conta; nunca deixar o sistema sem pelo menos 1 `gestao` ativo. `GuardsObraAdministration::ensureActorManagesObras` (`app/Actions/Obras/Concerns/GuardsObraAdministration.php:19-23`) nas Actions de obra e convite e em `Attach/DetachUserObra*`; `GuardsObraAssociationTarget` (`app/Actions/Usuarios/Concerns/`): alvo de associação só `obra`/`suprimentos` | Actions | `AuthorizationException` (403) / `PedidoTerminalStateException` (409) / `ValidationException` (422, mensagem PT-BR inline) |
| 6 | Validação de dados nas Actions | `CreatePedidoAction:50-62` rejeita `obra_id` fora de `obra_profile` e obra Concluída; `ResponsibleMustBeSuprimentos`; `CreateUserAction::obraIdsRules` (`:132-144`): `obra` e `suprimentos` aceitam 0..N obras, `gestao` proíbe obras; `AcceptObraInvitationAction::acceptAsExistingAccount` recusa conta de papel ≠ `obra` | Actions | `ValidationException` 422 |
| 7 | Login | `Auth::guard('web')->attempt([...credentials, 'is_active' => true])` — inativo recebe a mesma mensagem genérica que senha errada (`app/Livewire/Auth/LoginForm.php:47-51`) | componente | erro no campo `email` |

### Escopo por obra (`obra_profile`)

Pivot `obra_profile(obra_id, user_id)` com PK composta (`database/migrations/2026_09_18_230113_create_obra_profile_table.php:14-20`); relações `User::obras()` / `Obra::users()` (`app/Models/User.php:61-64`, `Obra.php:29-32`). Aplicação do escopo: (a) listagem `Acompanhamento` abre a consulta com `Pedido::query()->visibleTo(Auth::user())`; (b) detalhe via `PedidoPolicy::view`; (c) criação via select restrito (só obras ativas) **e** re-validação server-side na Action; (d) associações 0..N só para usuários `obra` e `suprimentos` — pela tela `/associacoes`, pelo formulário de usuários de Gestão ou por convite (só `obra`); `UpdateUserAction.php:75` faz `detach()` de tudo ao mudar para `gestao`. As associações de um usuário `suprimentos` **não** filtram o que ele vê: `suprimentos`/`gestao` nunca são filtrados por obra. Não há escopo de obra no banco.

**Decisão travada — `visibleTo` antes de qualquer filtro.** `Pedido::scopeVisibleTo(Builder, User)` (`app/Models/Pedido.php:40-47`) é a única codificação de visibilidade de linha por papel (`obra` → apenas suas obras; `suprimentos`/`gestao` → tudo; papel desconhecido → `whereRaw('1 = 0')`). Ela precisa ser aplicada **na mesma instrução que abre a consulta**, antes de busca, filtro de obra, ordenação ou paginação. Um filtro de obra escolhido pelo usuário só pode **estreitar** o recorte, nunca ampliá-lo — se o escopo viesse depois, um `obraId` forjado no query string alargaria a listagem. Fixado por `tests/Feature/Authorization/PedidoVisibleToScopeTest.php` e `tests/Feature/Compliance/ObraVisibleToGuardTest.php`.

### Convite de obra — o token só viaja no fragmento (RF-38, decisão travada)

O token em claro do convite é tratado como segredo que nunca pode chegar a log de aplicação nem a log de acesso (edge do Railway / FrankenPHP registram path + query):

- **Nunca em path ou query.** `/convite` não tem parâmetro de rota nem de query; o link compartilhável é `<APP_URL>/convite#<token>` e o fragmento não é enviado pelo navegador.
- **Transporte.** Um `@script` inline da página (`resources/views/livewire/auth/obra-invitation-page.blade.php:10-12`) lê `location.hash`, apaga-o com `history.replaceState` e chama `$wire.lookup(token)` — o token chega no corpo do POST `/livewire/update`. `ObraInvitationPage::lookup()` confere e conta o limitador `invite-ip` (20/min, chave só por IP) **antes** de validar o formato, calcular o SHA-256 e buscar por `token_hash`.
- **Nada em claro depois do lookup.** O componente guarda só o id do convite (`#[Locked]`), sem `#[Url]`; o retorno pós-login grava só o id inteiro em `obra_invitation.return_id` na sessão (`LoginForm.php:102-103`). Nenhum log, auditoria, coluna ou exceção recebe o token; a tabela guarda só `token_hash`.
- **Desfechos fixos, sem token.** Toda causa de convite inválido (expirado, usado, revogado, malformado, desconhecido, obra Concluída) → mesmo redirect para `/convite/indisponivel` (404, texto genérico, sem dados do convite); limitador estourado → `/convite/limite` (429).
- **Consumo atômico.** Nos dois caminhos de `AcceptObraInvitationAction`, a primeira instrução da transação é um UPDATE condicional sobre `consumable()` (0 linhas → rollback e desfecho "indisponível"): dois aceites concorrentes nunca consomem o mesmo convite.
- O primeiro acesso de Gestão (`/primeiro-acesso/{token}`) é outro fluxo, fora desta regra.

Fixado por `tests/Feature/Compliance/ObraInvitationTokenLeakTest.php`, `tests/Feature/ObraInvitations/{ObraInvitationTokenTransportTest,ObraInvitationOutcomePagesTest}.php`, `tests/Feature/Security/Adversarial/ObraInvitationConcurrencyTest.php` e `tests/Browser/ObraInvitationFlowTest.php`.

Testes que fixam essas regras: `tests/Feature/Authorization/{RoleGatesTest,PedidoPolicyTest,UserPolicyTest,ObraPolicyTest,BypassUiAuthorizationTest}.php`, `tests/Feature/Security/Adversarial/ObrasAuthorizationTest.php`, `tests/Feature/Auth/EnsureUserIsActiveTest.php` (inclui asserção de que toda rota autenticada carrega `active`, linha 109), `tests/Feature/Livewire/KanbanForgedMoveTest.php`.

## 6. Modelo de dados (lido de `database/migrations/`)

| Tabela | Colunas relevantes | FKs / invariantes |
|---|---|---|
| `roles` | `id, name, slug UNIQUE, description, is_active, timestamps` (`2026_09_18_230107:14-21`) | 3 slugs fixos em `RoleSlug` |
| `statuses` | `id, name, slug UNIQUE, description, sort_order UNIQUE, is_active, timestamps` (`230108:14-22`) | 6 slugs em `StatusSlug` |
| `priorities` | `id, name, slug UNIQUE, sort_order UNIQUE, is_active, timestamps` (`230109:14-21`) | 4 slugs em `PrioritySlug` |
| `event_types` | `id, name, slug UNIQUE, description, is_active, timestamps` (`230110:14-21`) | 7 slugs em `EventTypeSlug` |
| `users` | `id, role_id FK roles RESTRICT, name, email UNIQUE, email_verified_at, password, remember_token, is_active (default true), is_demo (default false), timestamps` (`0001_…000000:14-22`; `230111:14-18`) | `#[Hidden(['password','remember_token'])]` (`User.php:20`); além da `unique` da coluna, o índice único **funcional** `users_email_lower_unique` sobre `lower(email)` (`2026_09_22_155011:68-69`) |
| `obras` | `id, name, responsavel varchar(255) NULL, status varchar(20) NOT NULL DEFAULT 'a_iniciar', is_demo, timestamps` (`230112:14-20`; `2026_09_23_040313`) | check `obras_status_check` (`a_iniciar`, `em_andamento`, `concluido` — `ObraStatus`); índice único funcional `obras_name_normalized_unique` sobre `lower(btrim(name))`. `is_active` **não existe mais** (ver abaixo) |
| `obra_profile` | `obra_id FK CASCADE, user_id FK CASCADE, created_at`; **PK (obra_id, user_id)** (`230113:14-20`) | N:N usuário↔obra |
| `pedidos` | `id, code UNIQUE, obra_id FK RESTRICT, requester_id FK users RESTRICT, requested_at (useCurrent), needed_at DATE, items_description TEXT, status_id FK RESTRICT, priority_id FK NULL SET NULL, responsible_id FK users NULL SET NULL, expected_delivery_at DATE NULL, is_demo, timestamps` (`230114:14-28`) | índices `(obra_id, status_id)`, `needed_at` (`230116:14-17`) |
| `pedido_events` | `id, pedido_id FK CASCADE, event_type_id FK RESTRICT, previous_value TEXT NULL, new_value TEXT NULL, actor_id FK users RESTRICT, created_at (useCurrent)` — **sem `updated_at`** (`230115:14-22`) | índice `(pedido_id, created_at)` (`230116:19-21`) |
| `obra_invitations` | `id, obra_id FK RESTRICT, token_hash char(64) UNIQUE, created_by FK users RESTRICT, created_at (useCurrent), expires_at, revoked_by FK users NULL RESTRICT, revoked_at NULL, used_by FK users NULL RESTRICT, used_at NULL` — **sem `updated_at`** (`2026_09_23_042010`) | check `obra_invitations_revoked_or_used_check` (`revoked_at IS NULL OR used_at IS NULL`); índice `(obra_id, created_at)`. Só o SHA-256 do token é gravado (`#[Hidden]` em `token_hash`). Estado derivado em `ObraInvitation::state()`: Utilizado > Revogado > Expirado (`now >= expires_at`) > Pendente; `scopeConsumable()` = pendente **e** obra ativa |
| `obra_admin_events` | `id, actor_id FK users RESTRICT, obra_id FK RESTRICT, obra_invitation_id FK NULL RESTRICT, action varchar(40), before json, after json, created_at` (`2026_09_23_042011`) | append-only; índices `(obra_id, created_at)`, `(actor_id, created_at)`; ações `ObraAdminAction` (`obra_created`, `obra_updated`, `invitation_created`, `invitation_revoked`, `invitation_used`) |
| `account_registration_events` | `id, user_id FK RESTRICT, origin varchar(20) (novo_cadastro \| convite), obra_invitation_id FK NULL RESTRICT, ip varchar(45), created_at` (`2026_09_23_042012`) | append-only; índice `(user_id, created_at)`; nunca guarda senha nem e-mail |
| `user_admin_events` | `id, actor_id FK users RESTRICT, target_id FK users RESTRICT, action varchar(40), before json, after json, created_at` (`2026_09_21_000001`) | append-only; auditoria das Actions de usuário (`UserAdminAction`), incluindo `obra_access_changed` das associações e do aceite de convite |
| `authentication_events` | `id, event varchar(32), user_id FK NULL RESTRICT, email, ip varchar(45), user_agent varchar(255), created_at` (`2026_09_21_000002`) | append-only; login/logout/reset/senha definida/sessão revogada |
| `password_reset_tokens` | `email PK, token, created_at` (`0001…:24-28`) | compartilhada pelos 2 brokers |
| `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` | skeleton Laravel | `jobs*` nunca usadas |
| sequência `pedido_code_sequence` | `create sequence if not exists … ; alter sequence … restart with 1` (`230919:16-17`) | ver alerta na seção 3 |
| índice `users_email_lower_unique` | `create unique index users_email_lower_unique on users (lower(email))` (`2026_09_22_155011:68-69`) | ver "Normalização de e-mail" abaixo |
| índice `obras_name_normalized_unique` | `create unique index obras_name_normalized_unique on obras (lower(btrim(name)))` (`2026_09_23_040313`) | ver "Status da obra" abaixo |

### Status da obra (substitui `obras.is_active`)

A migration `2026_09_23_040313_convert_obras_activity_to_status.php` roda numa única transação: (1) aborta com `RuntimeException` em PT-BR se já houver nomes que colidem sob `lower(btrim(name))` — nada é escrito; (2) adiciona `status` e `responsavel`; (3) converte `is_active = true → em_andamento`, `false → concluido`; (4) fixa `NOT NULL DEFAULT 'a_iniciar'` + `obras_status_check`; (5) **remove `obras.is_active`**; (6) cria `obras_name_normalized_unique`. O `down()` é com perda (`is_active = status <> 'concluido'`, `responsavel` descartado). A definição de "obra ativa" existe num lugar só — `ObraStatus::isActive()` / `Obra::scopeActive()` (status ≠ `concluido`) — e é consumida por `CreatePedidoAction`, `NovaSolicitacao`, `GenerateObraInvitationAction` e `ObraInvitation::scopeConsumable` (`tests/Feature/Compliance/ObraActivityDefinitionTest.php`). `CreateObraAction`/`UpdateObraAction` checam o nome normalizado antes e capturam a violação do índice fora da transação → 422 "Já existe uma obra com este nome.".

### Normalização de e-mail — regra canônica

`App\Support\EmailNormalizer::normalize(string $email): string` = `mb_strtolower(trim($email))` é a **única** expressão de normalização da aplicação. Toda rota que grava, procura ou compara um e-mail passa por ela: `CreateUserAction`, `UpdateUserAction`, o formulário de Gestão, `users:create-gestao`, o consumo de convite/redefinição (`DefinesPasswordFromToken`), `users:email-case-report`, a migration de Fase 0 e `AuthenticationRateLimiter::normalizeEmail()` (que manteve a assinatura e delega o corpo, para que chave de rate limit, e-mail auditado e busca de credencial não possam divergir). Uma segunda implementação em `app/` reprova `tests/Feature/Compliance/EmailNormalizationGuardTest.php`.

No banco, a garantia é o índice único funcional `users_email_lower_unique` sobre `lower(email)` — não `citext`; a coluna continua `varchar(255)`. A migration `2026_09_22_155011_normalize_user_emails_and_add_lower_unique_index.php` faz três passos numa única `DB::transaction`: (1) aborta com `RuntimeException` em PT-BR se `users` ou `password_reset_tokens` já tiverem linhas que colidem sob `lower(btrim(email))` — nada é escrito e `migrate` sai com código ≠ 0; (2) reescreve as duas colunas para a forma canônica; (3) cria o índice. O `down()` só derruba o índice: a normalização dos dados é irreversível. Antes de migrar um banco existente, rodar o diagnóstico read-only `php artisan users:email-case-report`, que retorna 0 apenas quando não há colisão nem linha fora da forma canônica.

Relacionamentos (`app/Models/`): `Pedido` belongsTo `obra`, `status`, `priority`, `requester`, `responsible`; hasMany `events`. `User` belongsTo `role`; belongsToMany `obras`; hasMany `requestedPedidos`, `responsiblePedidos`, `pedidoEvents`. `previous_value`/`new_value` guardam **ids** (status/prioridade/responsável) ou datas ISO (previsão) como texto; a tradução para nome é feita por `app/Services/PedidoEventValuePresenter.php`.

### `pedido_events` é append-only (e as outras 4 tabelas `*_events`)

- Modelo (`app/Models/PedidoEvent.php`): `const UPDATED_AT = null` (`:21`); hooks `static::updating` e `static::deleting` lançam `LogicException` "PedidoEvent registros são imutáveis e não podem ser atualizados/excluídos." (`:29-37`). Qualquer `->update()`, `->save()` em registro existente ou `->delete()` via Eloquent aborta com exceção não tratada (500 se chegasse a uma requisição; nenhuma rota faz isso).
- Policy: `PedidoEventPolicy::update/delete` retornam `false` para qualquer usuário (`:14-21`).
- Banco: **não há** trigger ou regra SQL; `DELETE`/`UPDATE` diretos via SQL ou `Query Builder` (`DB::table('pedido_events')`) funcionam. A exclusão em cascata pelo `demo:reset` conta com isso (abaixo).
- Testes: `tests/Unit/Models/PedidoEventImmutabilityTest.php`, `tests/Feature/MigrationSchemaTest.php:129`.
- O mesmo padrão (`UPDATED_AT = null` + hooks `updating`/`deleting` que lançam) vale para `UserAdminEvent`, `AuthenticationEvent`, `ObraAdminEvent` e `AccountRegistrationEvent` (`tests/Feature/Compliance/AuditTrailsAppendOnlyTest.php`, `tests/Unit/Models/*ImmutabilityTest.php`).

### Desativação por `is_active` em vez de exclusão

`SetUserActiveAction` só faz `update(['is_active' => $active])` (`app/Actions/Usuarios/SetUserActiveAction.php:30`); nenhuma linha de `users`, `pedidos`, `pedido_events`, `obra_profile` ou `sessions` é apagada. Motivos verificáveis no schema: `pedidos.requester_id`, `pedidos.responsible_id` (SET NULL) e `pedido_events.actor_id` (**RESTRICT**) referenciam `users` — excluir um usuário com eventos falharia na FK, e excluir um requester perderia a autoria do histórico. Efeitos da desativação: login recusado (`LoginForm.php:47`), sessão viva cortada na próxima requisição (`EnsureUserIsActive`), "Esqueci minha senha" ignora contas inativas (`ForgotPassword.php:48-51`). Não existe rota nem Action de exclusão de usuário. Guard de lockout impede desativar a própria conta ou o último `gestao` ativo.

### Flag `is_demo` e `demo:reset`

`users.is_demo`, `obras.is_demo`, `pedidos.is_demo` (default `false`). `DemoSeeder` marca `true` em tudo que cria e usa nomes prefixados `[DEMO]` (`DemoSeeder.php:139-142, 156, 173-175, 308`); usuários demo têm senha `password` (`:153`) — **não deixe o seed em produção com usuários reais sem avaliar**. `CreateUserAction` e `users:create-gestao` gravam `is_demo = false` (`CreateUserAction.php:57`; `CreateGestaoUser.php:91`).

`php artisan demo:reset [--force]` (`app/Console/Commands/ResetDemoData.php:61-104`): numa transação, `Pedido::where('is_demo', true)->delete()` → via `DB::table` (sem Eloquent, logo o guard de imutabilidade não dispara) os `obra_invitations`, `obra_admin_events` e `account_registration_events` ligados a obras/usuários demo → `Obra` → `user_admin_events` e `authentication_events` dos usuários demo → `User`. `pedido_events` e `obra_profile` caem por `cascadeOnDelete` no banco. Ordem importa por causa dos `restrictOnDelete`. Sem `--force` pede confirmação. Lookups (`roles`, `statuses`, …) nunca são apagados. Teste: `tests/Feature/Console/ResetDemoDataTest.php`.

## 7. Proteção de dados

| Tema | Implementação verificada |
|---|---|
| Senhas | Cast `'password' => 'hashed'` (`app/Models/User.php:36`) → bcrypt (driver padrão do framework, custo `BCRYPT_ROUNDS`, 12 no `.env.example:41`, 4 em `phpunit.xml:28`). Usuário criado pela Gestão recebe `Str::password(32)` aleatória nunca exibida (`CreateUserAction.php:54`); define a própria senha pelo convite. Regra de força: `PasswordRule::defaults()` sem customização em `AppServiceProvider` → mínimo 8 caracteres do framework (`DefinesPasswordFromToken.php:44`). Nenhuma view emite atributo `password` (`BrandIdentityComplianceTest.php:142`); `tests/Feature/Auth/LoginTest.php:76` garante hash em repouso |
| Tokens de convite | Broker `passwords.invites` (`config/auth.php:109-114`): `expire = 4320` min (**72 h**), `throttle = 60` s, mesma tabela `password_reset_tokens`. Emitido por `SendAccessLinkAction` (`:30-35`) → `FirstAccessInvite` (link `invite.show` com `token` + `email`). Consumido em `AcceptInvite` → `DefinesPasswordFromToken::definePasswordThroughBroker('invites')` (`:65-88`): valida e-mail+token, grava senha via cast, rotaciona `remember_token`, dispara `PasswordReset`; qualquer status ≠ sucesso vira erro genérico (não revela se o e-mail existe). Teste de 71 h válido / 73 h expirado: `tests/Feature/Auth/FirstAccessInviteTest.php:155` |
| Tokens de redefinição | Broker `passwords.users` (`config/auth.php:96-101`): `expire = 60` min, `throttle = 60` s. `ForgotPassword::sendResetLink` só para `is_active = true` e responde sempre igual (`:44-54`); `User::sendPasswordResetNotification` usa `ResetPasswordPtBr` (`User.php:45-48`). Uma linha por e-mail: emitir convite substitui token de reset pendente e vice-versa |
| Armazenamento do token | O broker do framework (`DatabaseTokenRepository`) grava o token com hash na coluna `token`; o e-mail carrega o valor em claro, de uso único, apagado no consumo. Comportamento do framework — não há código próprio de token |
| Segredos | Vivem só em `.env` local (ignorado: `.gitignore:3-5`, confirmado por `git check-ignore`) e em Railway → Variables. `.env.example` é o único arquivo de ambiente versionado e contém apenas placeholders (`tests/Feature/Compliance/NoCommittedSecretsTest.php:117-135`); `NoCommittedSecretsTest.php:164` varre todos os arquivos versionados por valores com forma de segredo (JWT, chaves etc.). `GESTAO_BOOTSTRAP_PASSWORD` só em runtime (`CreateGestaoUser.php:114-125`). `APP_KEY` gerada fora do repo |
| CSRF | Middleware `web` padrão do Laravel (não desabilitado em `bootstrap/app.php`); formulários Livewire enviam o token pelo `/livewire/update`. `tests/Feature/Security/CsrfProtectionTest.php` garante 419 sem token em POST comum e no endpoint Livewire |
| Cookie de sessão | `SESSION_DRIVER=database` (`config/session.php:21`), `lifetime` 120 min (`:35`), `http_only` true (`:185`), `same_site` lax (`:202`), `secure` = `SESSION_SECURE_COOKIE` (`:172`; variável definida no serviço Railway, valor não lido), `encrypt` false (`:50`). Login regenera o id (`LoginForm.php:53`); logout invalida sessão e regenera token (`routes/web.php:53-60`) |
| Mass assignment | Todos os modelos declaram `#[Fillable]` explícito; `tests/Feature/Security/MassAssignmentTest.php` |
| XSS | Sem `{!! !!}` em views (`tests/Feature/Security/BladeEscapingTest.php:19`) |
| E-mails | Nunca contêm senha; links ancorados em `APP_URL`; envio síncrono; transporte `log` até `MAIL_MAILER=resend` |
| Rate limiting | 7 limitadores nomeados em `AppServiceProvider::configureRateLimiting` (`app/Providers/AppServiceProvider.php:73-82`), limites literais (decisão de produto, nunca lidos de env/config): `login` 5/min (e-mail + IP) e `login-account` 20/15 min (só e-mail, resiste a `X-Forwarded-For` forjado); `recovery` 3/min e `recovery-ip` 6/min; `register` 3/10 min (e-mail + IP) e `register-ip` 10/h, compartilhados pelo Novo Cadastro e pela criação de conta via convite; `invite-ip` 20/min no POST de lookup do token do convite, nunca num GET. Consumidos pelos componentes Livewire via `App\Services\AuthenticationRateLimiter` (chaves com SHA-256 do e-mail normalizado; `invite-ip` usa só o IP, nunca o token). **Não** há middleware `throttle` em rotas. Além deles, `throttle => 60` s dos dois brokers de senha (`config/auth.php:100,113`). Teste: `tests/Feature/Security/Adversarial/RateLimitTest.php` |
| Tokens de convite de obra | `bin2hex(random_bytes(32))` (256 bits), só o SHA-256 em `obra_invitations.token_hash`, validade 24 h, uso único por UPDATE condicional; o token só viaja no fragmento da URL (seção 5, RF-38) |
| Trilhas de auditoria | Além de `pedido_events`: `user_admin_events` (Actions de usuário e associações), `authentication_events` (login ok/falha, logout, reset, senha definida, sessão revogada), `obra_admin_events` (obras e convites) e `account_registration_events` (Novo Cadastro e convite; sem e-mail nem senha). Todas append-only |

### O que os testes de `tests/Feature/Compliance/` garantem (todos verdes em 2026-09-21)

| Arquivo | Garante |
|---|---|
| `NoCommittedSecretsTest.php` | `.gitignore` cobre `.env*` e mantém `.env.example`; nenhum `.env` real versionado; `.env.example` só placeholders e declara todas as variáveis do Railway; README documenta variáveis e deploy; nenhum arquivo versionado contém valor com forma de segredo |
| `EnvExampleTest.php` | `APP_NAME` = "Albuquerque Engenharia"; `MAIL_MAILER=log` como default; `RESEND_API_KEY` e `GESTAO_BOOTSTRAP_PASSWORD` apenas comentadas e vazias; README documenta IH-01, validades 72 h / 60 min, tabela compartilhada, runbook |
| `MailTransportTest.php` | `resend/resend-php ^1.x` no composer.json e travado no lock; nenhum outro SDK de e-mail; suíte usa mailer `array`; transporte `resend` nativo; chave só de `RESEND_API_KEY`; remetente só de `MAIL_FROM_*` |
| `NoNextJsDependencyTest.php` / `NoSupabaseDependencyTest.php` | Nenhum vestígio de Next.js/React/Supabase em `package.json`, `composer.json`, `.env.example`, código ou diretórios da raiz |
| `BrandIdentityComplianceTest.php` / `BrandAssetsTest.php` | Marca só via `config('app.name')`; logos versionadas em `public/images/` com sha256 fixado; regras visuais (sem gradientes, sem sidebar, assinatura MC só no layout de auth); nenhuma view expõe `password` |

### O que NÃO existe (cada item confirmado por busca no código)

| Ausência | Evidência |
|---|---|
| Criptografia de coluna | `grep -rniE "'encrypted'|encrypted:|Crypt::" app/ config/ database/` → 0 ocorrências; `SESSION_ENCRYPT=false` |
| 2FA / TOTP / verificação de e-mail | `grep -rniE "two.?factor|2fa|totp"` → 0; `MustVerifyEmail` comentado em `User.php:5` |
| Bloqueio de conta por tentativas | Não implementado — os limitadores recusam temporariamente, não bloqueiam a conta |
| Política de expiração/rotação de senha | Não implementado |
| Consentimento / retenção / anonimização (LGPD) | Não implementado; exclusão de usuário não existe (só desativação) |

## 8. Fluxo de desenvolvimento

### BC Harness (Beer and Code)

O projeto é conduzido pelo plugin `bc-harness@beer-and-code` (v0.2.0, instalado no escopo do usuário em `~/.claude/plugins/cache/beer-and-code/bc-harness/0.2.0`; não faz parte do repositório). O PRD delega a ele a derivação de descrição, user stories, schema e fases (`docs/product/PRD-V1.md` §42–§43). Artefatos versionados que ele produz e consome:

| Artefato | Papel |
|---|---|
| `.spec/init/{project-description,user-stories,database-schema,project-phases}.md` | Cadeia `init:*` derivada do PRD (ainda descreve a stack Next.js/Supabase original — ver divergências) |
| `.spec/features/<slug>/{SPEC.md,PLAN.md,PHASES.md}` | Pipeline `/plan` por feature (`reimplementacao-v0-laravel-livewire`, `ajustes-finais-albuquerque`, `security-hardening-production`, `paridade-demo-v0`) |
| `.phases/manifest.txt`, `.phases/phase-NN.md`, `.phases/logs/`, `.phases/prompts/` | Execução fase a fase (`ralph.sh`), com logs de ciclo/teste/verificação; commits seguem `feat(phase-N): …` |
| `docs/agents/*.md`, `AGENTS.md`, `CLAUDE.md` | Gerados/atualizados por `/ai-context` (o cabeçalho de `docs/agents/*.md` avisa que edições manuais são sobrescritas) |

Convenção de trabalho: toda alteração de código nasce de um `SPEC.md`/`PLAN.md` sob `.spec/features/` e é executada por fases em `.phases/`. **Não há hook, CI ou teste que imponha isso** — é disciplina de processo, não gate técnico. Regras de escrita: seguir o bloco `<laravel-boost-guidelines>` acima (Pint obrigatório, Pest, `php artisan make:*`, sem novas dependências sem aprovação).

### Propriedade dos arquivos de contexto

`CLAUDE.md` e `AGENTS.md` são **escritos à mão**, não carregam banner de geração e nunca são sobrescritos por máquina — `/ai-context` os classifica como `not-owned` e os pula. Os 8 arquivos de `docs/agents/*.md` são o oposto: carregam `<!-- Generated by /ai-context. Manual edits are overwritten on re-run. -->` na linha 3 e são regenerados pelo pipeline; **não os edite à mão**, rode `/ai-context`. Não existe `AI_CONTEXT.md` neste repositório, e nenhum deve ser criado: a árvore canônica é `AGENTS.md` + `docs/agents/*.md`, com este `CLAUDE.md` como companheiro manual.

### Convenções travadas de UI

**`#[Url]` é o único mecanismo de estado de filtro.** Toda propriedade de filtro das três listagens (`Obra\Acompanhamento`, `Suprimentos\TodosPedidos`, `Gestao\TodosPedidos`) é anotada com `Livewire\Attributes\Url`, com `except:` para que o valor neutro não apareça na URL. Ler parâmetros manualmente em `mount()` é **proibido** e foi removido de `Gestao\TodosPedidos`: `mount()` não roda de novo num update do Livewire, então o parâmetro sobreviveria ao "Limpar filtros" e a URL não poderia ficar limpa. Os nomes legados do drill-down (`atrasado`, `pendente`, `requestedFrom`, `requestedTo`) continuam resolvendo, via `as:`. Fixado por `tests/Feature/Compliance/FilterUrlStateComplianceTest.php`.

**Gráficos em SVG inline, sem biblioteca de chart.** O donut de prazos do dashboard é calculado em PHP num bloco `@php` da Blade, a partir das contagens que `DashboardIndicatorsService` já devolveu, e emitido como `<svg>` com um `<path>` por fatia — sem JavaScript, sem consulta extra e **sem nenhuma dependência de runtime nova** (Chart.js e similares estão proibidos; RNF-03). Cada bloco de indicador é `role="img"` com `aria-label` em PT-BR repetindo os mesmos números da lista visível.

**Classes Tailwind sempre literais.** O projeto não tem safelist, então uma classe interpolada (`fill-{{ $cor }}`) some do build de produção e só lá. As cores vêm de mapas literais no topo da view (`$prazoColors`, `$prazoFills`). Se você precisar de uma cor nova, escreva a classe por extenso.

### Comandos reais

| Ação | Comando | Fonte |
|---|---|---|
| Setup completo | `composer setup` (install → copia `.env` → `key:generate` → `migrate --force` → `npm install` → `npm run build`) | `composer.json:41-48` |
| Servidor dev | `composer run dev` (= `php artisan dev`) ou `php artisan serve` + `npm run dev` | `composer.json:49-52`; `package.json:7` |
| Build de assets | `npm run build` (Vite → `public/build/`, ignorado pelo Git) | `package.json:6` |
| Suíte completa | `composer test` (= `config:clear` + `php artisan test`) ou `vendor/bin/pest`; `npm test` delega ao composer | `composer.json:53-56`; `package.json:8` |
| Subconjunto | `php artisan test --compact tests/Feature/Actions` / `--filter=nome` | Boost guidelines |
| Suíte Browser (E2E Playwright) | `vendor/bin/pest tests/Browser` — exige Chromium instalado; ver README "Suíte Browser" | `phpunit.xml:14-16` |
| Formatação | `vendor/bin/pint --dirty --format agent` (obrigatório antes de finalizar) | Boost guidelines |
| Seed demo / reset | `php artisan db:seed` · `php artisan demo:reset --force` | `DatabaseSeeder.php:17`; `ResetDemoData.php:27` |
| Primeiro Gestão real | `php artisan users:create-gestao --name="…" --email=… --password='…' [--reset-password]` | `CreateGestaoUser.php:29-33` |

### PostgreSQL de teste (porta 5434)

`phpunit.xml:30-36` fixa `pgsql://laravel:laravel@127.0.0.1:5434/laravel_testing`; `tests/Pest.php:17-19` aplica `RefreshDatabase` a Feature, Unit e Browser (o banco é recriado a cada execução — **nunca** aponte para o banco de desenvolvimento). Subir:

```bash
docker run --name sistema-obra-pgsql-testing \
  -e POSTGRES_USER=laravel -e POSTGRES_PASSWORD=laravel -e POSTGRES_DB=laravel_testing \
  -p 5434:5432 -d postgres:17-alpine
# execuções seguintes:
docker start sistema-obra-pgsql-testing
```

(`README.md` "Banco de testes"; o container com esse nome existe nesta máquina e estava `Up` em 2026-09-21. Existe também um container antigo `sistema_obra_mc_laravel_pgsql` na mesma porta — só um pode estar ativo.) A migration da sequência faz `restart with 1` justamente para `migrate:fresh` repetido ser idempotente (`230919:13-17`).

### Mapa de testes

`tests/README.md` mapeia os 18 temas do brief a arquivos; suites: `tests/Unit` (classificadores, enums, modelos, gerador de código), `tests/Feature` (Actions, Auth, Authorization, Compliance, Console, Livewire, Notifications, Performance/`QueryCountTest`, Security, Seeders, schema), `tests/Browser` (roteiro demo, recuperação/usuários, responsividade).
