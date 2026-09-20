# Sistema de Solicitações e Compras — V0 Laravel

Sistema interno para centralizar, padronizar e rastrear solicitações de compras originadas
pelas obras de uma empresa de construção. Substitui pedidos informais (mensagens, planilhas
soltas) por um **fluxo único, rastreável e auditável**:

`Obra → Nova Solicitação → Suprimentos → Acompanhamento → Entrega → Histórico → Gestão`

Três perfis com visões e permissões distintas:

| Perfil | O que faz |
|---|---|
| **Obra** | Autentica, cria solicitações e acompanha os pedidos das obras às quais está associado (detalhe + histórico). Não edita a solicitação após o envio. |
| **Suprimentos** | Conduz todos os pedidos pelo workflow via Kanban e detalhe: responsável, prioridade, previsão de entrega, status e cancelamento. |
| **Gestão** | Visão consolidada somente leitura: dashboard de indicadores, listagem com filtros e Kanban read-only. |

Workflow oficial (5 status + cancelamento): `Solicitado → Em análise → Em compra/preparação →
Aguardando entrega → Entregue`; de qualquer status não-terminal é possível `Cancelar` (irreversível).
Toda mutação relevante gera um evento de histórico imutável.

## Stack

- **PHP 8.3+** (desenvolvido e validado com PHP 8.5) · **Laravel 13** · **Livewire 4** · **Blade**
- **Tailwind CSS 4** via **Vite 8**
- **PostgreSQL 17** (configurado inteiramente por environment variables)
- **Pest 4** (+ `pest-plugin-laravel`, `pest-plugin-browser`/Playwright para o roteiro E2E)
- **Laravel Boost** (guidelines, skills e servidor MCP para o Claude Code) — ver [Laravel Boost e Claude Code](#laravel-boost-e-claude-code)
- Infraestrutura alvo: **Railway** (Laravel App + PostgreSQL) — sem Redis, filas, workers ou cron nesta V0.

Não há nenhuma dependência de Next.js, React ou Supabase na aplicação executável
(garantido por `tests/Feature/Compliance/NoNextJsDependencyTest.php` e
`NoSupabaseDependencyTest.php`). A implementação Next.js/Supabase anterior permanece apenas no
histórico do Git como referência.

## Pré-requisitos

| Ferramenta | Versão |
|---|---|
| PHP | ≥ 8.3 com extensões `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `ctype`, `fileinfo`, `tokenizer`, `xml`, `bcmath` |
| Composer | 2.x |
| Node.js / npm | Node ≥ 20 (validado com Node 24) |
| PostgreSQL | 17 (local ou em Docker) |
| Docker (opcional) | apenas se preferir subir o PostgreSQL em container |

## Instalação e execução local

Os passos abaixo cobrem, na ordem, o procedimento completo de um clone limpo até a primeira
sessão autenticada. Existe um atalho (`composer setup`) descrito ao final da seção.

### 1. Instalar dependências PHP

```bash
composer install
```

### 2. Instalar dependências frontend

```bash
npm install
```

### 3. Criar e configurar o `.env`

```bash
cp .env.example .env
```

O `.env.example` já vem com `DB_CONNECTION=pgsql` e valores locais de exemplo. Ajuste pelo menos
as variáveis de banco (`DB_*`) conforme o PostgreSQL configurado no passo 4. O arquivo `.env`
está no `.gitignore` e **nunca** deve ser commitado.

| Variável | Descrição | Exemplo local |
|---|---|---|
| `APP_NAME` | Nome exibido no layout | `"Sistema de Solicitações e Compras"` |
| `APP_ENV` | Ambiente (`local`, `production`) | `local` |
| `APP_KEY` | Chave de criptografia — gerada no passo 5 | *(vazio até o passo 5)* |
| `APP_DEBUG` | Páginas de erro detalhadas — `false` em produção | `true` |
| `APP_URL` | URL base da aplicação | `http://localhost:8000` |
| `DB_CONNECTION` | Driver do banco — sempre `pgsql` | `pgsql` |
| `DB_HOST` / `DB_PORT` | Host/porta do PostgreSQL | `127.0.0.1` / `5432` |
| `DB_DATABASE` | Nome do banco | `laravel` |
| `DB_USERNAME` / `DB_PASSWORD` | Credenciais do banco | `laravel` / *(a sua)* |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | Já configurados para `database` (tabelas criadas pelas migrations padrão) | `database` |

### 4. Configurar o PostgreSQL

Crie um usuário e um banco vazio para a aplicação. Com Docker, um container pronto para uso:

```bash
docker run --name sistema-obra-pgsql \
  -e POSTGRES_USER=laravel \
  -e POSTGRES_PASSWORD=laravel \
  -e POSTGRES_DB=laravel \
  -p 5432:5432 -d postgres:17-alpine
```

Sem Docker, com o `psql` de uma instalação local:

```sql
CREATE USER laravel WITH PASSWORD 'laravel';
CREATE DATABASE laravel OWNER laravel;
```

Em seguida preencha `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` no `.env`.
Nenhum outro serviço (Redis, fila, worker) é necessário.

> Para rodar a suíte de testes você também precisará de um **segundo banco** (`laravel_testing`);
> ver [Executando os testes](#executando-os-testes).

### 5. Gerar a `APP_KEY`

```bash
php artisan key:generate
```

### 6. Executar as migrations

```bash
php artisan migrate
```

Cria do zero todo o schema: lookups (`roles`, `statuses`, `priorities`, `event_types`), `users`
(com `role_id`), `obras`, `obra_profile` (N:N usuário↔obra), `pedidos`, `pedido_events`
(append-only), os índices de consulta e a sequence `pedido_code_sequence` que gera os códigos
`PED-000001`. Não há dependência das migrations Supabase antigas.

### 7. Executar o seed de demonstração

```bash
php artisan db:seed
```

O `DemoSeeder` é **idempotente**: popula lookups, 4 usuários de demonstração, 3 obras e 6
pedidos em diferentes status/prioridades (incluindo um atrasado, um entregue e um cancelado),
com histórico suficiente para alimentar Kanban, listagens e dashboard. Rodar novamente não
duplica nem corrompe dados. Todos os registros de demonstração levam `is_demo = true` e o
prefixo `[DEMO]` no nome.

Para remover apenas os dados de demonstração (preservando qualquer dado real) e recomeçar:

```bash
php artisan demo:reset --force
php artisan db:seed
```

### 8. Iniciar a aplicação

Servidor Laravel + Vite (assets com hot reload) em um único comando:

```bash
composer run dev
```

Ou, separadamente:

```bash
npm run build          # compila os assets uma vez (ou `npm run dev` para watch)
php artisan serve      # http://localhost:8000
```

### 9. Acessar a aplicação

Abra <http://localhost:8000> (ou a `APP_URL` configurada). A raiz redireciona para `/login`;
após autenticar, cada perfil é levado à sua tela inicial (`/obra/pedidos`, `/suprimentos/kanban`
ou `/gestao/dashboard`).

### 10. Autenticar com um usuário de demonstração

Use qualquer credencial da tabela em [Credenciais de demonstração](#credenciais-de-demonstração).

### Atalho: `composer setup`

Executa em sequência `composer install`, cópia do `.env.example` (se ainda não houver `.env`),
`key:generate`, `migrate --force`, `npm install` e `npm run build`. Configure o PostgreSQL e as
variáveis `DB_*` **antes** de rodá-lo; o seed de demonstração (`php artisan db:seed`) continua
sendo um passo separado.

## Credenciais de demonstração

Válidas apenas para ambiente local/demo (criadas pelo `DemoSeeder`). A senha de todos os usuários
de demonstração é **`password`**. Nenhuma credencial real de produção existe neste repositório.

| Perfil | E-mail | Senha | Obras associadas |
|---|---|---|---|
| Obra | `obra.demo@example.com` | `password` | `[DEMO] Obra Alfa` |
| Obra (multi-obra) | `obra.multiobra.demo@example.com` | `password` | `[DEMO] Obra Beta`, `[DEMO] Obra Gama` |
| Suprimentos | `suprimentos.demo@example.com` | `password` | todas (sem restrição por obra) |
| Gestão | `gestao.demo@example.com` | `password` | todas (somente leitura) |

Roteiro sugerido de demonstração (brief §31, automatizado em `tests/Browser/DemoRoteiroTest.php`):
entrar como Obra → criar solicitação → acompanhar; entrar como Suprimentos → localizar no Kanban →
definir responsável/prioridade/previsão → mover pelo workflow → conferir histórico; voltar como Obra
→ confirmar atualização; entrar como Gestão → dashboard e Kanban read-only; voltar como Suprimentos
→ marcar como Entregue → confirmar histórico e indicadores.

## Executando os testes

A suíte (Pest) é executada **sempre** com:

```bash
composer test
```

Isso limpa o cache de configuração e roda `php artisan test` (suítes `Unit`, `Feature` e
`Browser`), que deve terminar com exit code 0.

### Banco de testes

`phpunit.xml` aponta os testes para um banco **separado**, recriado a cada execução
(`RefreshDatabase`):

| Variável | Valor em `phpunit.xml` |
|---|---|
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `5434` |
| `DB_DATABASE` | `laravel_testing` |
| `DB_USERNAME` / `DB_PASSWORD` | `laravel` / `laravel` |

Crie esse banco antes de rodar a suíte — por exemplo, um segundo container dedicado:

```bash
docker run --name sistema-obra-pgsql-testing \
  -e POSTGRES_USER=laravel \
  -e POSTGRES_PASSWORD=laravel \
  -e POSTGRES_DB=laravel_testing \
  -p 5434:5432 -d postgres:17-alpine
```

Ou, no mesmo servidor do passo 4, `CREATE DATABASE laravel_testing OWNER laravel;` e ajuste
`DB_PORT` (e demais valores) em `phpunit.xml` para o seu ambiente. **Nunca** aponte os testes para
o banco de desenvolvimento: `RefreshDatabase` apaga todas as tabelas.

### Suíte Browser (roteiro E2E)

`tests/Browser/DemoRoteiroTest.php` dirige a interface real (Blade/Livewire) em um Chromium
headless via Playwright. Na primeira execução instale o navegador:

```bash
npx playwright install chromium
```

Em distribuições Linux sem as bibliotecas de sistema do Chromium, `npx playwright install-deps
chromium` (requer root) as instala. O teste é o passo automatizado do roteiro oficial de 19 passos
(brief §31).

### Execuções parciais

```bash
php artisan test --compact tests/Feature/Actions          # um diretório
php artisan test --compact --filter=UpdatePedidoStatus     # por nome
vendor/bin/pest tests/Unit                                  # direto pelo runner
```

O mapa de cobertura (tema do brief §30 → arquivo de teste) está em [`tests/README.md`](tests/README.md).

### Formatação

Após modificar arquivos PHP, rode o Laravel Pint:

```bash
vendor/bin/pint --dirty
```

## Laravel Boost e Claude Code

O [Laravel Boost](https://laravel.com/docs/ai) está instalado como dependência de desenvolvimento
(`laravel/boost` em `require-dev`) e configurado para o Claude Code:

- `boost.json` — configuração do pacote (guidelines, MCP e skills habilitadas:
  `laravel-best-practices`, `testing-best-practices`, `livewire-development`,
  `tailwindcss-development`, `deploying-to-cloud`, `infer-conventions`).
- `CLAUDE.md` / `AGENTS.md` — guidelines geradas pelo Boost para a versão instalada do Laravel e
  seus pacotes, carregadas automaticamente pelo agente.
- `.mcp.json` — registra o servidor MCP `laravel-boost` (`php artisan boost:mcp`), que expõe ao
  agente ferramentas como `search-docs`, `database-schema`, `database-query`, `browser-logs`
  e `last-error`. O comando registrado usa um caminho absoluto de máquina; ajuste `command`/`args`
  para o seu ambiente (ex.: `"command": "php", "args": ["artisan", "boost:mcp"]`).

Para reinstalar/reconfigurar após um clone (regenera guidelines e o registro MCP):

```bash
php artisan boost:install
```

Para atualizar guidelines e skills para a orientação mais recente:

```bash
php artisan boost:update
```

## Produção (Railway)

A aplicação é configurada **integralmente por environment variables** e preparada para a
arquitetura Railway `Laravel App + PostgreSQL` (brief §37–§39). Nenhum outro serviço (Redis,
fila, worker, cron) é necessário nesta V0.

```
Railway Project
├── Laravel App   ← este repositório (deploy automático a partir do GitHub)
└── PostgreSQL    ← plugin gerenciado do Railway
```

### Variáveis de ambiente obrigatórias

Defina-as em **Railway → serviço Laravel App → Variables**. Nenhuma delas é lida de um arquivo
`.env` em produção — o `.env` não existe no container e **nunca** é commitado (ver
[Segredos e Git](#segredos-e-git)).

| Variável | Valor em produção | Observação |
|---|---|---|
| `APP_NAME` | `"Sistema de Solicitações e Compras"` | Nome exibido no layout e no título das páginas |
| `APP_ENV` | `production` | Desliga comportamentos de desenvolvimento |
| `APP_KEY` | `base64:...` (32 bytes) | Gere **fora** do repositório com `php artisan key:generate --show` e cole o valor. Chave de criptografia de sessões/cookies — trocá-la invalida todas as sessões |
| `APP_DEBUG` | `false` | **Obrigatório.** Com `true` a aplicação expõe stack traces, variáveis de ambiente e SQL nas páginas de erro |
| `APP_URL` | `https://<dominio-gerado-ou-custom>` | URL pública em HTTPS (o TLS é terminado pelo proxy do Railway) |
| `DB_CONNECTION` | `pgsql` | Único driver suportado |
| `DB_HOST` | `${{Postgres.PGHOST}}` | Referência ao serviço PostgreSQL do mesmo projeto (rede privada) |
| `DB_PORT` | `${{Postgres.PGPORT}}` | |
| `DB_DATABASE` | `${{Postgres.PGDATABASE}}` | |
| `DB_USERNAME` | `${{Postgres.PGUSER}}` | |
| `DB_PASSWORD` | `${{Postgres.PGPASSWORD}}` | |

> Alternativa equivalente às cinco variáveis `DB_HOST`…`DB_PASSWORD`: uma única
> `DB_URL=${{Postgres.DATABASE_URL}}` (Laravel decompõe a URL `postgresql://…` automaticamente).
> A sintaxe `${{Servico.VARIAVEL}}` é a de *reference variables* do Railway — substitua `Postgres`
> pelo nome do serviço PostgreSQL no seu projeto.

### Variáveis recomendadas

| Variável | Valor em produção | Observação |
|---|---|---|
| `LOG_CHANNEL` | `stderr` | Envia os logs para o stdout/stderr do container, capturado pelo painel de logs do Railway (sem gravar em `storage/logs`) |
| `LOG_LEVEL` | `info` (ou `warning`) | `debug` é excessivamente verboso em produção |
| `SESSION_DRIVER` | `database` | Padrão do `.env.example`; a tabela `sessions` é criada pelas migrations |
| `SESSION_SECURE_COOKIE` | `true` | Cookie de sessão só trafega em HTTPS |
| `CACHE_STORE` | `database` | Padrão do `.env.example`; a tabela `cache` é criada pelas migrations |
| `QUEUE_CONNECTION` | `database` | Nenhum job é despachado na V0; mantido apenas para não exigir Redis |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `en` | Idem `.env.example` |
| `BCRYPT_ROUNDS` | `12` | Custo do hash de senha |

As demais chaves do `.env.example` (`MAIL_*`, `AWS_*`, `REDIS_*`, `MEMCACHED_HOST`,
`BROADCAST_CONNECTION`, `FILESYSTEM_DISK`) não são usadas pela V0 e podem ser omitidas — os
valores padrão dos arquivos `config/*.php` são suficientes. A variável `PORT` é injetada pelo
próprio Railway e é usada pelo comando de start.

### Procedimento de deploy

O deploy é automático a partir do GitHub: cada push na branch conectada dispara build + deploy.
O pipeline completo, na ordem exigida pelo brief §39:

| Etapa | Comando | Onde configurar no Railway |
|---|---|---|
| 1. Dependências PHP | `composer install --no-dev --optimize-autoloader --no-interaction` | Build (detectado automaticamente pelo builder PHP; explícito via *Build Command* se necessário) |
| 2. Dependências e build dos assets | `npm ci && npm run build` | Build — gera `public/build/` (Vite). O diretório está no `.gitignore`; **sempre** é compilado no deploy |
| 3. Environment variables | tabela acima | *Variables* do serviço (antes do primeiro deploy) |
| 4. Migrations | `php artisan migrate --force` | *Pre-Deploy Command* (roda a cada deploy, antes do start, com as variáveis de produção). `--force` é obrigatório: sem ele o Artisan pede confirmação interativa em `APP_ENV=production` e o deploy trava |
| 5. Caches de produção | `php artisan config:cache && php artisan route:cache && php artisan view:cache` | Início do *Start Command* (ver abaixo). Precisam rodar **depois** das variáveis estarem definidas, pois `config:cache` congela os valores de `env()` |
| 6. Start | `php artisan serve --host=0.0.0.0 --port=$PORT` | *Start Command* |

*Start Command* consolidado (etapas 5 + 6):

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan serve --host=0.0.0.0 --port=$PORT
```

Se preferir o servidor nginx + php-fpm provisionado pelo builder PHP do Railway em vez do
`php artisan serve`, mantenha as etapas 1–5 e configure o *document root* como `public/`.

Observações:

- **Health check:** a rota `/up` (registrada em `bootstrap/app.php`) responde `200` quando a
  aplicação subiu; use-a como *Healthcheck Path* do serviço.
- **Rollback de cache:** se alguma variável mudar após o deploy, basta um novo deploy (o
  *Start Command* recria os caches). Nunca rode `config:cache` localmente com um `.env` de
  desenvolvimento e commite `bootstrap/cache/*.php` — o diretório já está ignorado.
- **Seed de demonstração (opcional):** em um ambiente de demo, rode uma única vez, pelo shell do
  serviço, `php artisan db:seed --force`. O `DemoSeeder` é idempotente e marca tudo com
  `is_demo = true`; `php artisan demo:reset --force` remove apenas esses registros.
- **HTTPS:** o certificado e o redirecionamento HTTP→HTTPS são responsabilidade do proxy do
  Railway; a aplicação não precisa de configuração adicional além de `APP_URL` em `https://` e
  `SESSION_SECURE_COOKIE=true`.
- **Fora de escopo da V0:** Redis, filas, workers, scheduler/cron, storage externo (S3) e envio
  de e-mail — nenhum é provisionado ou configurado (brief §38: "não adicionar infraestrutura além
  da necessidade real").

### Segredos e Git

- `.env`, `.env.backup` e `.env.production` estão no `.gitignore` e **nunca** são versionados; o
  único arquivo de ambiente commitado é o `.env.example`, que contém apenas placeholders
  (`APP_KEY=` vazio, `DB_PASSWORD=` vazio, credenciais locais de exemplo).
- `APP_KEY` e `DB_PASSWORD` de produção existem **somente** nas *Variables* do Railway.
- As credenciais de demonstração (`*.demo@example.com` / `password`) são geradas pelo seeder e
  não correspondem a nenhum ambiente real.
- O teste `tests/Feature/Compliance/NoCommittedSecretsTest.php` é a barreira automatizada: falha
  se algum `.env` real for versionado, se o `.gitignore` deixar de cobri-lo ou se qualquer arquivo
  versionado contiver um valor com formato de segredo (`APP_KEY=base64:…`, chaves AWS/GitHub/
  Stripe/Slack, JWTs, chaves privadas PEM, `SECRET/TOKEN/PASSWORD=<valor longo>`).

## Estrutura do projeto

```
app/
├── Actions/Pedidos/      # Create/UpdateResponsavel/UpdatePrioridade/UpdatePrevisao/UpdateStatus/Cancel — regras de domínio + evento de histórico na mesma transação
├── Domain/Pedidos/       # AtrasoClassifier, PendenteClassifier, PrazoClassifier — fonte única das regras de atraso/pendente/prazo
├── Enums/                # RoleSlug, StatusSlug, PrioritySlug, EventTypeSlug — literais congelados
├── Livewire/             # Auth, Obra, Suprimentos, Kanban, Gestao — telas interativas
├── Models/               # User, Role, Obra, Pedido, PedidoEvent, Status, Priority, EventType
├── Policies/             # PedidoPolicy (view/create/5 mutações), PedidoEventPolicy (imutável)
├── Rules/                # ResponsibleMustBeSuprimentos
├── Services/             # PedidoCodeGenerator, DashboardIndicatorsService, PedidoEventValuePresenter
└── Console/Commands/     # demo:reset
database/
├── migrations/           # schema PostgreSQL completo
├── seeders/              # DemoSeeder (idempotente)
└── factories/
resources/views/          # layouts, componentes Blade e views Livewire
routes/web.php            # rotas por perfil (obra/, suprimentos/, gestao/) sob auth + gates
tests/                    # Unit, Feature, Browser (Pest) — ver tests/README.md
docs/                     # PRD (docs/product), brief de migração (docs/migration), contexto AS IS (docs/agents)
.spec/                    # SPEC.md, PLAN.md, PHASES.md e TRACEABILITY.md da reimplementação
```

## Documentação relacionada

- [`docs/product/PRD-V1.md`](docs/product/PRD-V1.md) — fonte de verdade de produto e regras de negócio.
- [`docs/migration/LARAVEL-MIGRATION-BRIEF.md`](docs/migration/LARAVEL-MIGRATION-BRIEF.md) — brief da reimplementação (33 critérios de conclusão em §47).
- [`.spec/features/reimplementacao-v0-laravel-livewire/SPEC.md`](.spec/features/reimplementacao-v0-laravel-livewire/SPEC.md) — requisitos RIGID/FLEXIBLE.
- [`.spec/features/reimplementacao-v0-laravel-livewire/PLAN.md`](.spec/features/reimplementacao-v0-laravel-livewire/PLAN.md) — decomposição em tarefas e fases.
- [`.spec/features/reimplementacao-v0-laravel-livewire/TRACEABILITY.md`](.spec/features/reimplementacao-v0-laravel-livewire/TRACEABILITY.md) — matriz de rastreabilidade V0 Next.js → Laravel.
- [`tests/README.md`](tests/README.md) — mapa de cobertura de testes.
