# Sistema de Solicitações e Compras — V0 Laravel

Sistema interno para centralizar, padronizar e rastrear solicitações de compras originadas
pelas obras de uma empresa de construção. Substitui pedidos informais (mensagens, planilhas
soltas) por um **fluxo único, rastreável e auditável**:

`Obra → Nova Solicitação → Suprimentos → Acompanhamento → Entrega → Histórico → Gestão`

Três perfis com visões e permissões distintas:

| Perfil | O que faz |
|---|---|
| **Obra** | Autentica, cria solicitações e acompanha os pedidos das obras às quais está associado (detalhe + histórico). Não edita a solicitação após o envio. |
| **Suprimentos** | Tela inicial em **Pedidos** (`/suprimentos/pedidos`, do mais antigo para o mais novo). Cria solicitações e conduz todos os pedidos pelo workflow via Kanban e detalhe: responsável, prioridade, previsão de entrega, status, observações, romaneio, finalização e cancelamento. Cadastra obras, convites e associações. |
| **Gestão** | Tela inicial em **Pedidos** (`/gestao/pedidos`). Visão consolidada somente leitura: listagem com filtros, dashboard de indicadores e Kanban read-only. Administra os usuários (criação, edição, associação a obras, ativação/desativação), dispara o convite de primeiro acesso e cadastra obras, convites e associações. |

A navegação principal é uma **sidebar** por perfil (no celular, atrás do botão **Menu**), com
"+ Nova Solicitação" em destaque para Obra e Suprimentos.

Workflow oficial (6 status + cancelamento): `Solicitado → Em análise → Em compra/preparação →
Aguardando entrega → Entregue → Finalizado`; `Finalizado` exige romaneio anexado; de qualquer status
não-terminal é possível `Cancelar` (irreversível).
Toda mutação relevante gera um evento de histórico imutável.

## Stack

- **PHP 8.3+** (desenvolvido e validado com PHP 8.5) · **Laravel 13** · **Livewire 4** · **Blade**
- **Tailwind CSS 4** via **Vite 8**
- **PostgreSQL 17** (configurado inteiramente por environment variables)
- **Pest 4** (+ `pest-plugin-laravel`, `pest-plugin-browser`/Playwright para o roteiro E2E)
- **Laravel Boost** (guidelines, skills e servidor MCP para o Claude Code) — ver [Laravel Boost e Claude Code](#laravel-boost-e-claude-code)
- **E-mail transacional** pela camada de mail do Laravel: `log` em desenvolvimento, `array` nos testes e
  o transporte nativo `resend` (SDK `resend/resend-php`) em produção — ver [E-mail transacional](#e-mail-transacional)
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
| `APP_NAME` | Nome exibido no layout, no título das páginas e como remetente dos e-mails | `"Albuquerque Engenharia"` |
| `APP_ENV` | Ambiente (`local`, `production`) | `local` |
| `APP_KEY` | Chave de criptografia — gerada no passo 5 | *(vazio até o passo 5)* |
| `APP_DEBUG` | Páginas de erro detalhadas — `false` em produção | `true` |
| `APP_URL` | URL base da aplicação | `http://localhost:8000` |
| `DB_CONNECTION` | Driver do banco — sempre `pgsql` | `pgsql` |
| `DB_HOST` / `DB_PORT` | Host/porta do PostgreSQL | `127.0.0.1` / `5432` |
| `DB_DATABASE` | Nome do banco | `laravel` |
| `DB_USERNAME` / `DB_PASSWORD` | Credenciais do banco | `laravel` / *(a sua)* |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | Já configurados para `database` (tabelas criadas pelas migrations padrão) | `database` |
| `MAIL_MAILER` | Transporte de e-mail — `log` grava convites e redefinições de senha (com o link) em `storage/logs/laravel.log` | `log` |

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

#### Anexos de pedidos em ambiente local

Os anexos (inclusive o romaneio) ficam no disco privado `pedido_anexos`; sem `PEDIDO_ANEXOS_ROOT`
no `.env`, a raiz é `storage/app/pedido-anexos` — nunca sob `public/`. O PHP CLI local costuma vir
com `upload_max_filesize=2M` e `post_max_size=8M`, abaixo do limite de 10 MB por arquivo. Para
testar uploads grandes, carregue os limites versionados em `config/php/uploads.ini`:

```bash
PHP_INI_SCAN_DIR=:$(pwd)/config/php php artisan serve
# equivalente: php -d upload_max_filesize=12M -d post_max_size=16M artisan serve
```

O `:` inicial preserva o diretório de `.ini` padrão da instalação.

### 9. Acessar a aplicação

Abra <http://localhost:8000> (ou a `APP_URL` configurada). A raiz redireciona para `/login`;
após autenticar, cada perfil é levado à sua tela inicial (`/obra/pedidos`, `/suprimentos/pedidos`
ou `/gestao/pedidos`); as demais telas ficam na sidebar.

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
entrar como Obra (cai em Acompanhamento) → "+ Nova Solicitação" na sidebar → criar solicitação →
acompanhar; entrar como Suprimentos (cai em Pedidos) → abrir Kanban pela sidebar → localizar o pedido →
definir responsável/prioridade/previsão → mover pelo workflow → conferir histórico; voltar como Obra
→ confirmar atualização; entrar como Gestão (cai em Pedidos) → Dashboard e Kanban read-only pela
sidebar; voltar como Suprimentos → marcar como Entregue → confirmar histórico e indicadores.

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
| `APP_NAME` | `"Albuquerque Engenharia"` | Nome exibido na tela de login, no layout, no título das páginas e como remetente padrão dos e-mails (`MAIL_FROM_NAME`). Não é segredo; a marca nunca é hardcoded — vem sempre de `config('app.name')` |
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

### Variáveis de e-mail transacional

Necessárias para que convites de primeiro acesso e redefinições de senha cheguem de fato aos
usuários. Enquanto não estiverem definidas (ponto de intervenção humana **IH-01**, ver
[E-mail transacional](#e-mail-transacional)), mantenha `MAIL_MAILER=log`: a aplicação sobe e todos
os demais fluxos funcionam. Os valores abaixo são **placeholders** — os reais existem somente em
Railway → serviço Laravel → Variables.

| Variável | Valor em produção | Observação |
|---|---|---|
| `MAIL_MAILER` | `resend` | Transporte nativo do Laravel; o SDK `resend/resend-php` já está no `composer.lock`. Enquanto IH-01 estiver aberto, use `log` |
| `RESEND_API_KEY` | `<chave gerada no painel do Resend>` | Lida por `config('services.resend.key')`. **Nunca** commitada nem colocada no `.env.example` |
| `MAIL_FROM_ADDRESS` | `<remetente no domínio verificado no Resend>` | Endereço do remetente; o domínio precisa estar verificado no Resend |
| `MAIL_FROM_NAME` | `"Albuquerque Engenharia"` (ou `${APP_NAME}`) | Nome do remetente exibido ao destinatário |

As variáveis SMTP (`MAIL_SCHEME`, `MAIL_URL`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`,
`MAIL_PASSWORD`, `MAIL_EHLO_DOMAIN`) continuam disponíveis como *fallback* do framework
(`MAIL_MAILER=smtp`), mas não são o caminho de produção. Nenhum outro provedor (Postmark, SES,
Mailgun) é instalado.

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

As demais chaves do `.env.example` (`AWS_*`, `REDIS_*`, `MEMCACHED_HOST`, `BROADCAST_CONNECTION`,
`FILESYSTEM_DISK`) não são usadas e podem ser omitidas — os valores padrão dos arquivos
`config/*.php` são suficientes. A variável `PORT` é injetada pelo
próprio Railway e é usada pelo comando de start.

### Anexos de pedidos (Volume e limites de upload)

Os anexos de pedidos (arquivos da Nova Solicitação e o romaneio) são gravados no disco privado
`pedido_anexos` (`config/filesystems.php`), cuja raiz vem de `PEDIDO_ANEXOS_ROOT`. O sistema de
arquivos do container do Railway é efêmero: **sem um Volume, todo anexo se perde no próximo
deploy**. Os arquivos nunca ficam sob `public/`; o download passa sempre pela rota autorizada.
`PEDIDO_ANEXOS_ROOT` não é segredo. Os limites de upload do PHP estão versionados em
`config/php/uploads.ini` (`upload_max_filesize = 12M`, `post_max_size = 16M`), arquivo inerte até
que `PHP_INI_SCAN_DIR` inclua o diretório.

Passos operacionais (humanos — nenhum deles é executado pelo código ou pelas migrations):

1. Criar um **Volume** no serviço `laravel-app` montado em `/data`.
2. Em Railway → Variables, definir `PEDIDO_ANEXOS_ROOT=/data/pedido-anexos` e
   `PHP_INI_SCAN_DIR=:/app/config/php` **antes do build** — o `config:cache` roda em tempo de
   build e congela o valor de `PEDIDO_ANEXOS_ROOT`.
3. Depois do deploy seguinte, verificar somente leitura no shell do serviço (com confirmação do
   desenvolvedor): `php --ini` lista `/app/config/php/uploads.ini`;
   `php -r 'echo ini_get("upload_max_filesize"), " ", ini_get("post_max_size");'` imprime
   `12M 16M`; `test -w /data && echo ok` confirma que o processo PHP escreve no Volume (se não
   escrever, a alternativa documentada pelo Railway é `RAILWAY_RUN_UID=0` — não verificada).
4. **Não dar `git push`** desta entrega antes dos passos 1 e 2: o push dispara o deploy.
5. Teste manual pós-deploy: enviar um PDF e um DOCX numa solicitação → redeploy → baixar os dois
   e conferir que os bytes são os mesmos (confirma também a detecção de OOXML em produção).
6. associar os usuários Suprimentos às obras em /associacoes antes de anunciar a Nova Solicitação
   — um usuário Suprimentos sem obra ativa associada vê o estado vazio da Nova Solicitação;
   nenhuma migration ou seeder associa usuários reais.

Notas:

- `php artisan demo:reset --force` apaga apenas os arquivos de anexos de pedidos de demonstração.
- "Hoje" (atraso, prazo, entregues hoje) e o período dos filtros seguem o **dia de São Paulo**
  (`America/Sao_Paulo`); os instantes continuam gravados em UTC.
- Ambiente local: ver [Anexos de pedidos em ambiente local](#anexos-de-pedidos-em-ambiente-local).

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
- **Fora de escopo:** Redis, filas, workers, scheduler/cron e storage externo (S3) — nenhum é
  provisionado ou configurado (brief §38: "não adicionar infraestrutura além da necessidade real").
- **E-mails transacionais (em escopo):** convite de primeiro acesso e redefinição de senha são
  enviados de forma **síncrona**, sem fila, pelo transporte configurado em `MAIL_MAILER` — ver
  [E-mail transacional](#e-mail-transacional).

### E-mail transacional

A aplicação envia dois e-mails, ambos em PT-BR, gerados pelos templates markdown em
`resources/views/mail/auth/` e pelas notificações `App\Notifications\FirstAccessInvite` e
`App\Notifications\ResetPasswordPtBr`:

| Mensagem | Quando | Link (rota nomeada) | Validade |
|---|---|---|---|
| Convite de primeiro acesso | Gestão cria um usuário ou reenvia o acesso em `Usuários` | `invite.show` (`/primeiro-acesso/{token}`) | **72 horas** (`passwords.invites`, `expire = 4320`) |
| Redefinição de senha | Usuário ativo usa "Esqueci minha senha" na tela de login | `password.reset` (`/redefinir-senha/{token}`) | **60 minutos** (`passwords.users`, `expire = 60`) |

Regras comuns: os links são construídos a partir de `APP_URL` + rota nomeada (nenhum host é
hardcoded, então continuam válidos após uma futura troca de domínio); o remetente é sempre
`MAIL_FROM_NAME` / `MAIL_FROM_ADDRESS` (padrão `APP_NAME`); o corpo nunca contém senha; o token é
temporário, de uso único e armazenado com hash pelo broker do framework; um novo pedido para o
mesmo e-mail é limitado a 1 por 60 segundos. Os dois brokers compartilham a tabela
`password_reset_tokens` (uma linha por e-mail): emitir um convite substitui um token de
redefinição pendente daquele e-mail, e vice-versa — basta pedir um novo link.

Transporte por ambiente (`MAIL_MAILER`):

| Ambiente | Transporte | Comportamento |
|---|---|---|
| Local | `log` (padrão do `.env.example`) | A mensagem completa — cabeçalhos, corpo PT-BR e o link — é gravada em `storage/logs/laravel.log`; copie o link do log para testar o fluxo |
| Testes | `array` (`phpunit.xml`) | Nada é enviado; as mensagens ficam em memória e são inspecionadas pela suíte |
| Produção | `resend` | Envio real pela API do Resend com as variáveis da tabela [Variáveis de e-mail transacional](#variáveis-de-e-mail-transacional) |

**IH-01 — intervenção humana necessária antes do envio real em produção.** O código está pronto;
o que falta é operacional e não pode ser automatizado nem versionado:

1. Criar a conta no [Resend](https://resend.com).
2. Adicionar e verificar (DNS) o domínio do remetente.
3. Gerar a API key no painel do Resend.
4. Em Railway → serviço Laravel → Variables definir `MAIL_MAILER=resend`, `RESEND_API_KEY`,
   `MAIL_FROM_ADDRESS` (no domínio verificado) e `MAIL_FROM_NAME`; fazer um novo deploy para que
   `config:cache` recarregue os valores.
5. Validar ponta a ponta: reenviar um convite em `Usuários` e usar "Esqueci minha senha".

**Enquanto IH-01 não estiver concluído** (`MAIL_MAILER=log` em produção): a aplicação sobe e
todos os outros fluxos funcionam; convites e redefinições são apenas gravados no log do container
(Railway → Logs) — a interface de Gestão continua informando que o convite foi enviado, mas nenhum
e-mail chega ao usuário. Não crie usuários do cliente antes de concluir a etapa; o convite pode
ser reenviado depois em `Usuários`.

### Bootstrap do primeiro Gestão

O primeiro usuário Gestão real (o do responsável pela operação) é criado por um comando Artisan
idempotente, executado uma única vez no shell do serviço Railway (ou localmente):

```bash
php artisan users:create-gestao --name="<nome>" --email=<e-mail> --password='<senha digitada agora>'
```

- A senha existe **apenas em tempo de execução**: pela opção `--password=` ou pela variável de
  ambiente `GESTAO_BOOTSTRAP_PASSWORD` exportada no shell imediatamente antes do comando. Ela não
  tem valor padrão no código, não é lida de arquivo versionado, não é impressa pelo comando e
  **nunca** deve ser colocada no `.env`, no `.env.example`, nas Variables do Railway ou no Git.
  Limpe o histórico do shell se aplicável.
- Comportamento: cria (ou atualiza, sem duplicar) o usuário pelo e-mail com perfil `gestao`,
  `is_active = true`, `is_demo = false` e senha com hash. Se o usuário já existe, a senha **não** é
  sobrescrita a menos que `--reset-password` seja informado explicitamente. Sem `--email` ou sem
  senha o comando falha com mensagem de uso e não cria nada.
- Saída esperada: `Usuário Gestão garantido: <e-mail> (criado|atualizado)`.
- Depois do primeiro login, o responsável troca a senha inicial por "Esqueci minha senha" (exige
  IH-01 concluído) e cria o usuário Gestão do cliente pela área `Usuários`, que recebe o convite
  de primeiro acesso por e-mail.

### Runbook de produção (Etapa 10)

Passos humanos executados após os gates de qualidade (suíte verde, `npm run build` ok, diff
revisado, `NoCommittedSecretsTest` verde). Nunca `migrate:fresh`; nunca editar código no Railway;
nenhum segredo no Git.

0. Antes do push: cumprir os passos 1 e 2 de
   [Anexos de pedidos (Volume e limites de upload)](#anexos-de-pedidos-volume-e-limites-de-upload)
   (Volume em `/data`, `PEDIDO_ANEXOS_ROOT`, `PHP_INI_SCAN_DIR`) e, após o deploy, os passos 3, 5
   e 6 — inclusive associar os usuários Suprimentos às obras em `/associacoes`.
1. `git push` na branch conectada — o Railway faz build + deploy; o *Pre-Deploy Command*
   `php artisan migrate --force` roda (sem migrations novas nesta entrega, é um no-op).
2. Railway → Variables: `APP_NAME="Albuquerque Engenharia"`; novo deploy para o `config:cache`.
3. Quando IH-01 estiver concluído: `MAIL_MAILER=resend`, `RESEND_API_KEY`, `MAIL_FROM_ADDRESS`,
   `MAIL_FROM_NAME`; novo deploy. Até lá, manter `MAIL_MAILER=log`.
4. Validar: `/up` → `200`; `/login` exibe "Albuquerque Engenharia", as logos versionadas em
   `public/images/` (Albuquerque no topo do card, MC Inteligência menor junto à assinatura) e
   "Esqueci minha senha"; login de demonstração ainda funciona.
5. Executar `users:create-gestao` (seção acima) no shell do serviço, com a senha digitada na hora.
6. Verificar o login do responsável em produção e abrir `Usuários`.
7. Desativar as contas de demonstração (`obra.demo@example.com`, `obra.multiobra.demo@example.com`,
   `suprimentos.demo@example.com`, `gestao.demo@example.com`) em `Usuários` → `Desativar` — só
   depois de o Gestão real estar ativo e com login verificado. Conferir:
   `SELECT count(*) FROM users WHERE is_demo = true AND is_active = true;` → `0`. Pedidos e obras
   de demonstração permanecem (histórico preservado).
8. Responsável troca a senha inicial por "Esqueci minha senha" (requer o passo 3).
9. Responsável cria o Gestão do cliente pela área `Usuários` (o convite requer o passo 3).
10. Validar o envio ponta a ponta (convite + redefinição) e registrar IH-01 como concluído.

### Domínio definitivo (Etapa 11 — diferido)

Só quando o subdomínio definitivo for informado (não inventar domínio). Checklist: adicionar o
domínio customizado no serviço Railway; configurar o DNS (CNAME para o alvo do Railway); aguardar e
validar o certificado HTTPS; definir `APP_URL=https://<subdominio>` (manter
`SESSION_SECURE_COOKIE=true`; revisar `SESSION_DOMAIN` só se necessário); novo deploy para o
`config:cache`; validar redirecionamentos (`/` → `/home`, login → tela inicial do perfil), cookies
de sessão no novo host, assets (`@vite`), Livewire (`/livewire/update`), e os links de convite e
redefinição de senha — que já derivam de `APP_URL`, sem host hardcoded. Até lá, o domínio gerado
pelo Railway é o endpoint técnico.

### Segredos e Git

- `.env`, `.env.backup` e `.env.production` estão no `.gitignore` e **nunca** são versionados; o
  único arquivo de ambiente commitado é o `.env.example`, que contém apenas placeholders
  (`APP_KEY=` vazio, `DB_PASSWORD=` vazio, credenciais locais de exemplo).
- `APP_KEY`, `DB_PASSWORD` e `RESEND_API_KEY` de produção existem **somente** nas *Variables* do
  Railway; o `.env.example` traz `RESEND_API_KEY` apenas como nome comentado e vazio.
- A senha inicial do primeiro Gestão (`users:create-gestao`) é digitada em tempo de execução e
  nunca armazenada — nem em `GESTAO_BOOTSTRAP_PASSWORD` persistida, nem no Railway, nem no Git.
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
├── Livewire/             # Auth (login, esqueci senha, redefinição, primeiro acesso), Obra, Suprimentos, Kanban, Gestao (inclui Usuários)
├── Notifications/        # FirstAccessInvite (convite, 72 h) e ResetPasswordPtBr (redefinição, 60 min) — PT-BR, links via APP_URL
├── Models/               # User, Role, Obra, Pedido, PedidoEvent, Status, Priority, EventType
├── Policies/             # PedidoPolicy (view/create/5 mutações), PedidoEventPolicy (imutável)
├── Rules/                # ResponsibleMustBeSuprimentos
├── Services/             # PedidoCodeGenerator, DashboardIndicatorsService, PedidoEventValuePresenter
└── Console/Commands/     # demo:reset, users:create-gestao (bootstrap do primeiro Gestão)
database/
├── migrations/           # schema PostgreSQL completo
├── seeders/              # DemoSeeder (idempotente)
└── factories/
resources/views/          # layouts, componentes Blade, views Livewire e templates de e-mail (mail/auth, components/mail)
routes/web.php            # rotas por perfil (obra/, suprimentos/, gestao/) sob auth + gates
tests/                    # Unit, Feature, Browser (Pest) — ver tests/README.md
docs/                     # PRD (docs/product), brief de migração (docs/migration), contexto AS IS (docs/agents)
.spec/                    # SPEC.md, PLAN.md, PHASES.md (reimplementação e ajustes finais Albuquerque)
```

## Documentação relacionada

- [`docs/product/PRD-V1.md`](docs/product/PRD-V1.md) — fonte de verdade de produto e regras de negócio.
- [`docs/migration/LARAVEL-MIGRATION-BRIEF.md`](docs/migration/LARAVEL-MIGRATION-BRIEF.md) — brief da reimplementação (33 critérios de conclusão em §47).
- [`.spec/features/reimplementacao-v0-laravel-livewire/SPEC.md`](.spec/features/reimplementacao-v0-laravel-livewire/SPEC.md) — requisitos RIGID/FLEXIBLE.
- [`.spec/features/reimplementacao-v0-laravel-livewire/PLAN.md`](.spec/features/reimplementacao-v0-laravel-livewire/PLAN.md) — decomposição em tarefas e fases.
- [`.spec/features/reimplementacao-v0-laravel-livewire/TRACEABILITY.md`](.spec/features/reimplementacao-v0-laravel-livewire/TRACEABILITY.md) — matriz de rastreabilidade V0 Next.js → Laravel.
- [`.spec/features/ajustes-finais-albuquerque/SPEC.md`](.spec/features/ajustes-finais-albuquerque/SPEC.md) e
  [`PLAN.md`](.spec/features/ajustes-finais-albuquerque/PLAN.md) — administração de usuários, primeiro acesso,
  recuperação de senha, e-mail transacional (IH-01) e identidade Albuquerque.
- [`tests/README.md`](tests/README.md) — mapa de cobertura de testes.
