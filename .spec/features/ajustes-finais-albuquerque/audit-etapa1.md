# Auditoria — Etapa 1 (T01): baseline e preflight

- Feature: `ajustes-finais-albuquerque` (SPEC v1.1, PLAN T01, §47 Etapa 1 do documento de requisitos)
- Data: 2026-09-20
- Cobre: RF-01 (baseline), RF-26 (versão do SDK Resend), UI-19 (pré-verificação das logos), RNF-13, RNF-18
- Natureza: **somente leitura** — nenhum código de aplicação, dependência, migration, configuração Railway ou cópia de logo foi alterado/criado nesta etapa (RNF-18, §48).

---

## 1. Preflight Git (§40, RNF-13)

| Verificação | Resultado |
|---|---|
| Remote | `origin` → `https://github.com/lobernardo/sistema_obra_mc.git` |
| `git fetch --all --prune` | executado, sem novidades |
| Branch atual | `feat/ajustes-finais-albuquerque` (upstream `origin/feat/ajustes-finais-albuquerque`, em sincronia) |
| `HEAD` | `b1f8ec8` — "docs: plan Albuquerque final adjustments" |
| `HEAD` descende de `82e4d48`? | **Sim** (`git merge-base --is-ancestor 82e4d48 HEAD`); único commit à frente: `b1f8ec8` (apenas `.spec/`) |
| `build/v0-demo-laravel` local / `origin/build/v0-demo-laravel` | ambos em `82e4d48` = baseline da SPEC ("fix(railway): PHP >=8.4 constraint and trusted proxies for Railway edge") |
| `build/v0-demo` / `origin/build/v0-demo` (default do remote) | `471a89f` |
| `git status` | working tree limpa antes e depois desta etapa |

Observação: a SPEC nomeia `build/v0-demo-laravel` como branch de referência. O trabalho está em uma feature branch derivada dela (`feat/ajustes-finais-albuquerque`), cujo `HEAD` descende diretamente de `82e4d48`; a branch base permanece intocada em `82e4d48`.

## 2. Baseline da suíte e do build

Comando de teste do projeto: `composer test` (`composer.json:52-55` → `php artisan config:clear` + `php artisan test`).

| Item | Resultado |
|---|---|
| `composer test -- --compact` | `{"tool":"pest","result":"passed","tests":296,"passed":296,"assertions":854,"duration_ms":79997}` — exit 0 |
| Esperado (SPEC AC-53.2) | 296 testes / 854 assertions / 0 falhas — **confere** |
| `npm run build` | Vite: `✓ built in 544ms` — **exit 0**; artefatos em `public/build/` (ignorado pelo Git; `git status` continua limpo) |
| Ambiente | PHP 8.5.4 CLI (`composer.json:9` exige `^8.4`), Node v24.18.0, `laravel/framework` v13.32.0 |

## 3. Os 11 itens da Etapa 1 (§47) com evidência `arquivo:linha`

### 3.1 Autenticação existente
- Único ponto de entrada: `routes/web.php:21` — `Route::get('/login', LoginForm::class)->name('login')` sob `guest`.
- `app/Livewire/Auth/LoginForm.php:47` — `Auth::guard('web')->attempt([...$credentials, 'is_active' => true])`; qualquer falha (credencial inválida **ou** usuário inativo) responde `'E-mail ou senha inválidos.'` (`LoginForm.php:48-50`); `Session::regenerate()` em `:53`; redirect para `route('home')` em `:55`.
- `/home` (`routes/web.php:29-36`) redireciona por `RoleSlug`; `abort(403, 'Perfil de acesso não reconhecido.')` sem papel.
- Logout: `routes/web.php:38-45` (`POST /logout`, invalida sessão, regenera token CSRF).
- Não existe: "Esqueci minha senha", convite, `app/Mail`, `app/Notifications`, `lang/` (verificado: os três diretórios não existem).
- Broker de senha configurado mas sem consumidor: `config/auth.php:95-101` (`passwords.users`, `expire => 60`, `throttle => 60`); tabela `password_reset_tokens` criada em `database/migrations/0001_01_01_000000_create_users_table.php:24`.
- Middleware: `bootstrap/app.php:15-23` (`trustProxies(at: '*')` em `:21`, `alias([...])` em `:23`) — nenhum guard de usuário ativo por request ainda (ponto de entrada de `EnsureUserIsActive`, RF-31).

### 3.2 Usuários / profiles
- `app/Models/User.php:18` — `#[Fillable(['name', 'email', 'password', 'role_id', 'is_active', 'is_demo'])]`; casts `is_active`/`is_demo` boolean em `:35-36`; `role()` BelongsTo em `:43`; `obras()` BelongsToMany via pivot `obra_profile` em `:51-53`; escopo `scopeSuprimentos` em `:87`.
- Colunas: `database/migrations/2026_09_18_230111_add_role_and_profile_fields_to_users_table.php:15-17` — `role_id` FK `roles` com `restrictOnDelete()`, `is_active` default `true`, `is_demo` default `false`.
- Provisionamento: apenas `database/seeders/DemoSeeder.php:136-156` (4 contas `*.demo@example.com`, `is_demo => true`, linhas 139-142) e `database/factories/UserFactory.php`. Não há tela de administração de usuários.

### 3.3 Roles (3)
- `app/Enums/RoleSlug.php` — exatamente 3 cases: `Obra = 'obra'`, `Suprimentos = 'suprimentos'`, `Gestao = 'gestao'`.
- Tabela: `database/migrations/2026_09_18_230107_create_roles_table.php:14-21` (`name`, `slug` unique, `description`, `is_active`).
- Linhas seedadas: `database/seeders/DemoSeeder.php:52-61` (`firstOrCreate` por slug: Obra, Suprimentos, Gestão) — 3 linhas, sem 4º papel (§5.1).
- `app/Models/Role.php:27` — `users()` HasMany. Factory: `database/factories/RoleFactory.php` (estados por `RoleSlug`, ex. `:34`).

### 3.4 Policies / Gates
- Gates coarse: `app/Providers/AppServiceProvider.php:29-31` — `is-obra`, `is-suprimentos`, `is-gestao` (`$user->role?->slug === RoleSlug::X->value`).
- Policies existentes (2): `app/Policies/PedidoPolicy.php` (`view:19`, `create:28`, `setResponsavel:34`, `setPrioridade:39`, `setPrevisao:44`, `updateStatus:49`, `cancelar:54`) e `app/Policies/PedidoEventPolicy.php` (`update:14`, `delete:19`). Não existe `UserPolicy` nem gate `manage-users` (entra em T02).
- Uso nas rotas: `routes/web.php:48` (`can:is-obra`), `:54` (`can:is-suprimentos`), `:60` (`can:is-gestao` — Dashboard, TodosPedidos, PedidoDetalhe, KanbanReadOnly, todos read-only).
- Testes de proteção: `tests/Feature/Authorization/RoleGatesTest.php`, `PedidoPolicyTest.php`, `BypassUiAuthorizationTest.php`, `tests/Feature/Livewire/KanbanForgedMoveTest.php` (TC-17).

### 3.5 Obras
- `database/migrations/2026_09_18_230112_create_obras_table.php:14-20` — `name`, `is_active` default `true`, `is_demo` default `false`.
- `app/Models/Obra.php:29-31` — `users()` BelongsToMany via `obra_profile`; `pedidos()` HasMany em `:37-39`.
- Seed: `database/seeders/DemoSeeder.php:180` (`Obra::query()->firstOrCreate([...], ['is_active' => true, 'is_demo' => true])`). Factory: `database/factories/ObraFactory.php`.

### 3.6 Relacionamento obra-profile
- `database/migrations/2026_09_18_230113_create_obra_profile_table.php:14-21` — `obra_id` FK `cascadeOnDelete`, `user_id` FK `cascadeOnDelete`, `created_at` `useCurrent()`, PK composta `['obra_id', 'user_id']`.
- Lados do relacionamento: `app/Models/User.php:53` e `app/Models/Obra.php:31`.
- Teste: `tests/Feature/ObraProfileCardinalityTest.php` (`:7` N:N multi-obra; `:22` PK composta impede pares duplicados).

### 3.7 Mail config
- `config/mail.php:17` — `'default' => env('MAIL_MAILER', 'log')`; mailer `resend` já declarado em `config/mail.php:64-65` (`'transport' => 'resend'`).
- `config/services.php:21-23` — `'resend' => ['key' => env('RESEND_API_KEY')]`.
- `phpunit.xml:36` — `<env name="MAIL_MAILER" value="array"/>` (suíte nunca envia e-mail real).
- `.env.example:75-82` — `MAIL_MAILER=log`, `MAIL_FROM_ADDRESS="hello@example.com"`, `MAIL_FROM_NAME="${APP_NAME}"`.
- `.gitignore:3-6` mantém `.env`/`.env.*` ignorados (RNF-07); `tests/Feature/Compliance/NoCommittedSecretsTest.php` verde na baseline.
- Não há `app/Mail`, `app/Notifications`, nem nenhum fluxo que envie mensagens.

### 3.8 Views / layouts
- Layout autenticado: `resources/views/layouts/app.blade.php:36` — `<header class="bg-slate-900 text-white shadow">`; brand `{{ config('app.name') }}` em `:39`; nav horizontal por papel (badge de papel em `:62` `bg-sky-500/20 … text-sky-200`); **não há sidebar**.
- Layout de login: `resources/views/auth/login.blade.php:17` — título `{{ config('app.name') }}`; card em `:19`.
- Formulário de login: `resources/views/livewire/auth/login-form.blade.php:20` — botão `bg-sky-600 … hover:bg-sky-700 … focus:ring-sky-300` (o "botão azul" a ser substituído, UI-15); inputs com `focus:border-sky-500 focus:ring-sky-200` em `:8` e `:15`. Não há link "Esqueci minha senha".
- 22 arquivos Blade no total (`find resources/views -type f`): 1 layout, 1 auth, 6 componentes, 14 views Livewire (auth 1, examples 1, gestao 5, kanban 2, obra 3, suprimentos 2).
- Componentes Livewire (12 classes em `app/Livewire/`): `Auth/LoginForm`, `Examples/HelloWorld`, `Gestao/{Dashboard,KanbanReadOnly,PedidoDetalhe,TodosPedidos}`, `Kanban/KanbanBoard`, `Obra/{Acompanhamento,NovaSolicitacao,PedidoDetalhe}`, `Suprimentos/{PedidoDetalhe,TodosPedidos}`.

### 3.9 Tailwind
- `resources/css/app.css:1` `@import 'tailwindcss'` (Tailwind 4); `@theme` em `:6-9` define **somente** `--font-sans` — nenhum token de cor.
- `.btn-primary` em `resources/css/app.css:36-37` → `bg-sky-600 … hover:bg-sky-700 … focus:ring-sky-300`.
- `sky-*` hardcoded: **31 ocorrências em 15 arquivos** = `app.css` + 14 views Blade: `components/{pedido-history-timeline,pedido-table,priority-badge,status-badge}`, `layouts/app`, `livewire/auth/login-form`, `livewire/gestao/{dashboard,pedido-card-read-only,pedido-detalhe,todos-pedidos}`, `livewire/kanban/pedido-card`, `livewire/obra/pedido-detalhe`, `livewire/suprimentos/{pedido-detalhe,todos-pedidos}`.
- Barras do dashboard: `resources/views/livewire/gestao/dashboard.blade.php:110` (`bg-sky-500`) e `:148` (`bg-violet-500`).

### 3.10 Componentes (6)
Todos anônimos em `resources/views/components/` (não existe `app/View/Components/`):
1. `atraso-indicator.blade.php`
2. `pedido-history-timeline.blade.php`
3. `pedido-summary.blade.php`
4. `pedido-table.blade.php`
5. `priority-badge.blade.php` (badges semânticos de prioridade)
6. `status-badge.blade.php` (badges semânticos de status)

### 3.11 Testes existentes
56 arquivos `*Test.php` (+ `tests/Pest.php`, `tests/TestCase.php`), 296 testes / 854 assertions:

- `tests/Browser/`: `DemoRoteiroTest`
- `tests/Feature/Actions/`: `CancelPedidoActionTest`, `CreatePedidoActionTest`, `UpdatePedidoPrevisaoActionTest`, `UpdatePedidoPrioridadeActionTest`, `UpdatePedidoResponsavelActionTest`, `UpdatePedidoStatusActionTest`
- `tests/Feature/Auth/`: `LoginTest`, `UnauthenticatedAccessTest`
- `tests/Feature/Authorization/`: `BypassUiAuthorizationTest`, `PedidoPolicyTest`, `RoleGatesTest`
- `tests/Feature/Compliance/`: `NoCommittedSecretsTest`, `NoNextJsDependencyTest`, `NoSupabaseDependencyTest`
- `tests/Feature/Console/`: `ResetDemoDataTest`
- `tests/Feature/Livewire/`: `AccessibleStatusControlTest`, `AcompanhamentoTest`, `CancelPedidoControlTest`, `DashboardDrillDownTest`, `DashboardFiltersTest`, `DashboardIndicatorsTest`, `GestaoKanbanReadOnlyTest`, `KanbanBoardTest`, `KanbanForgedMoveTest`, `LoginFormTest`, `NovaSolicitacaoTest`, `ObraScreensRouteTest`, `PedidoCardRenderTest`, `PedidoDetalheGestaoTest`, `PedidoDetalheObraTest`, `PedidoDetalheSuprimentosTest`, `SuprimentosScreensRouteTest`, `TodosPedidosFiltersTest`
- `tests/Feature/Performance/`: `QueryCountTest`
- `tests/Feature/Rules/`: `ResponsibleMustBeSuprimentosTest`
- `tests/Feature/Security/`: `BladeEscapingTest`, `CsrfProtectionTest`, `MassAssignmentTest`
- `tests/Feature/Seeders/`: `DemoSeederIdempotencyTest`
- `tests/Feature/` (raiz): `BootstrapTest`, `DatabaseConnectionTest`, `ExampleTest`, `FreshMigrationTest`, `LivewireSmokeTest`, `MigrationSchemaTest`, `ObraProfileCardinalityTest`
- `tests/Unit/Domain/`: `AtrasoClassifierTest`, `PendenteClassifierTest`, `PrazoClassifierTest`
- `tests/Unit/Enums/`: `SlugEnumsTest`
- `tests/Unit/Models/`: `LookupModelsTest`, `PedidoEventImmutabilityTest`, `PedidoModelTest`
- `tests/Unit/Services/`: `PedidoCodeGeneratorTest`
- `tests/Unit/`: `ExampleTest`

## 4. SDK Resend — confirmação de versão (RF-26, RNF-09) — NÃO instalado

`composer show -a resend/resend-php`:

| Item | Valor |
|---|---|
| Última versão estável | **v1.15.0** (série `^1.x`: v1.0.0 … v1.15.0) |
| `requires` | `php ^8.1.0`, `guzzlehttp/guzzle ^7.8.2 \|\| ^8.0`, `guzzlehttp/psr7 ^2.6.3 \|\| ^3.0`, `psr/http-client ^1.0` |
| Instalados no projeto | `guzzlehttp/guzzle` 8.2.0, `guzzlehttp/psr7` 3.1.0, PHP 8.5.4 — **satisfazem** |
| `laravel/framework` v13.32.0 | `require-dev` `resend/resend-php: ^1.0` (`composer.lock:1196`); `suggest` "Required to enable support for the Resend mail transport (^0.10.0 \|\| ^1.0)" (`composer.lock:1232`) |
| Constraint a usar na Etapa 4 | `resend/resend-php:^1.15` (compatível; `^1.0` também aceito pelo framework) |
| Estado atual | `composer show resend/resend-php` → "Package not found"; `vendor/resend` não existe; `composer.json` sem a entrada — **não instalado nesta etapa** |

## 5. Logos originais — verificação SEM cópia (UI-19, §32, §33, §48)

Diretório: `/mnt/c/Users/leool/OneDrive/Documentos/Projetos/MC-Inteligência_Albuquerque - Sistema de Solicitações e Compras/`

| Arquivo | Existe | Formato (`file`) | Dimensões (`getimagesize`) | Bytes | mtime | SHA-256 |
|---|---|---|---|---|---|---|
| `logo_Albuquerque.png` | sim | PNG 8-bit RGBA, non-interlaced | **1063×345** | **209281** | 2026-09-20 15:11:12 -03 | `64e210022d3dcc398690ba8850233afa52960999f301d4f84d033f5b05f77b4d` |
| `logo_MC.png` | sim | PNG 8-bit RGBA, non-interlaced | **1305×200** | **23177** | 2026-09-20 15:18:32 -03 | `798e27b03956aac3d9572fc35b64a7ab81fe6d6336b23eba0a1c3c8276309b36` |

Confere com SPEC CT-06 / UI-19 (1063×345, 209281 B; 1305×200, 23177 B). Os checksums acima servem de referência para AC-52.7 na Etapa 9.

- Originais **não modificados** (somente leitura: `ls`, `file`, `getimagesize`, `hash_file`).
- **Nenhuma cópia feita**: `public/images/` não existe (`public/` contém apenas `build/`, `favicon.ico`, `index.php`, `robots.txt`); `resources/images/` não existe. Aplicação das logos fica para a Etapa 9 (UI-20).

## 6. Critérios de aceite da T01

| Critério | Status |
|---|---|
| `audit-etapa1.md` com os 11 itens + evidências `arquivo:linha` | ✅ §3 |
| Contagens da suíte (296/854/0) e do build (exit 0) | ✅ §2 |
| Versão do SDK Resend registrada, não instalada | ✅ §4 |
| Verificação das logos (nome, dimensões, tamanho) sem copiar | ✅ §5 |
| `git status` sem arquivo de aplicação alterado | ✅ único arquivo novo: este artefato em `.spec/` |
| `ls public/images` não existe | ✅ |
| Suíte existente inalterada e verde | ✅ |
