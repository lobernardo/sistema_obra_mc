# Verificação de `defer()` sob FrankenPHP (RNF-03, parte estática — T01)

Data: 2026-10-01. Escopo: leitura do vendor instalado e do log de deploy do Railway (somente leitura). Nenhum arquivo em `app/`, `config/` ou `routes/` foi alterado.

## 1. Ordem resposta → callbacks diferidos (framework instalado, laravel/framework v13.32.0)

| Passo | Evidência |
|---|---|
| `public/index.php` → `Application::handleRequest()` faz `$kernel->handle($request)->send()` e só **depois** `$kernel->terminate($request, $response)` | `vendor/laravel/framework/src/Illuminate/Foundation/Application.php:1223-1230` |
| `Response::send()` envia headers e corpo e, se a função existir, chama `fastcgi_finish_request()` (senão `litespeed_finish_request()`, senão fecha os buffers e faz `flush()` fora de `cli`/`phpdbg`/`embed`) | `vendor/symfony/http-foundation/Response.php:406-424` |
| `InvokeDeferredCallbacks` é middleware **global** padrão | `vendor/laravel/framework/src/Illuminate/Foundation/Configuration/Middleware.php:455` |
| Em `terminate()`, invoca a `DeferredCallbackCollection` só para `status < 400` ou callback com `always: true` | `vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/InvokeDeferredCallbacks.php:31-36` |
| `DeferredCallbackCollection` é `scoped` (uma por requisição/comando) | `vendor/laravel/framework/src/Illuminate/Foundation/Providers/FoundationServiceProvider.php:213` |
| Em console, roda no `CommandFinished` quando `runningInConsole()` e `exitCode === 0` (ou `always`) | `FoundationServiceProvider.php:215-217`. O evento só é emitido por `Console\Kernel::handle()` (`rerouteSymfonyCommandEvents`, `Console/Kernel.php:160-175`), não por `Kernel::call()` |

Conclusão: no pipeline HTTP, um callback de `defer()` sempre roda depois de a resposta estar montada e enviada ao SAPI; o que muda entre runtimes é só se o SAPI **encerra a conexão** com o cliente antes de `terminate()`.

## 2. Runtime de produção (Railway, consulta read-only em 2026-10-01)

| Item | Valor | Fonte |
|---|---|---|
| Builder | Railpack 0.40.1 | log de build do deploy `32d5f586-4104-49d3-ae6b-c0f5f3d96648` |
| Imagem base | `docker.io/dunglas/frankenphp:php8.4.26-trixie` | idem |
| PHP | 8.4.26 | log de deploy `FrankenPHP started` (2026-10-01T02:07:38Z), `num_threads 64`, `max_requests 0` |
| Versão do FrankenPHP | **[UNVERIFIED]** — a tag da imagem fixa só o PHP; o log de start não imprime a versão do FrankenPHP | — |
| Modo | clássico (sem `worker` no Caddyfile gerado; Octane não instalado) → `scoped` da `DeferredCallbackCollection` continua por requisição | `composer.json` sem `laravel/octane`; log sem worker |

## 3. `fastcgi_finish_request()` / `frankenphp_finish_request()` no SAPI do FrankenPHP

O stub do FrankenPHP (`frankenphp.stub.php`, branch `main` de `php/frankenphp`) declara `frankenphp_finish_request(): bool` e registra `fastcgi_finish_request()` como **alias** dele. Portanto, no SAPI HTTP do FrankenPHP, `function_exists('fastcgi_finish_request')` é `true`, e o `Response::send()` do Symfony já encerra a resposta ao cliente antes de `terminate()` — e, por consequência, antes dos callbacks de `defer()`.

Ressalva: como a versão exata do FrankenPHP da imagem é `[UNVERIFIED]`, a existência do alias é inferida da fonte atual do projeto (o alias acompanha o FrankenPHP desde as primeiras versões estáveis), não da versão em execução. A medição de G-3/T22 (p95 com 5 vs. 0 destinatários) é a prova final.

## 4. Decisão para T06 (`InternalNotificationMailer`)

**Não** chamar `frankenphp_finish_request()` no callback diferido. O alias `fastcgi_finish_request()` já é chamado por `Response::send()` antes de `terminate()`; uma segunda chamada seria código morto (devolveria `false`). Se a medição de G-3 falhar, a primeira hipótese a investigar é a ausência do alias na versão em execução; nesse caso a chamada guardada `if (function_exists('frankenphp_finish_request')) { frankenphp_finish_request(); }` como primeira instrução do callback é a correção prevista, sem infra nova.

## 5. Prova automatizada

`tests/Feature/Notifications/DeferredCallbacksAfterResponseTest.php` registra rotas e callbacks só dentro do teste e prova:

- HTTP 200: ordem `handler` → `response-built` (`RequestHandled`) → `deferred`;
- HTTP 500 e 403 sem `always`: o callback não roda;
- HTTP 500 com `always: true`: o callback roda;
- console: roda no `CommandFinished` com exit 0; com exit 1 só roda o callback `always`.

## 6. Roteiro de medição G-3 (RNF-03, parte em produção — T22)

Objetivo: provar em produção que o envio de e-mail diferido não pesa na resposta. **Critério:** p95 do cenário A (5 destinatários) **≤ p95 do cenário B (0 destinatários) + 150 ms**, com 20 execuções em cada cenário.

### 6.1 Pré-condições

- Deploy da feature `notificacoes-internas` ativo em `https://albuquerque.mcinteligencia.com` com `MAIL_MAILER=resend` (conferir em Railway → Variables, sem copiar valores).
- Um usuário `gestao` ativo que executa as alterações (o autor nunca é destinatário).
- Um pedido **P5** de uma obra **X** com exatamente **5 destinatários elegíveis** além do autor: por exemplo, 1 outro `gestao` ativo + 2 usuários `obra` ativos associados a X + 2 usuários `suprimentos` ativos associados a X, todos com e-mail real que aceite mensagens de teste. Conferir a contagem pela própria regra: depois da 1ª execução, `select count(*) from internal_notifications where pedido_event_id = <id do evento>` deve dar **5**.
- Um pedido **P0** com **0 destinatários**: nenhum outro usuário ativo elegível além do autor (ex.: obra sem associações num momento em que o autor é o único `gestao` ativo, ou os demais `gestao` temporariamente desativados). Conferir: a mesma consulta dá **0**.
- P5 e P0 em status não terminal (a mudança de status é a operação medida).

### 6.2 Captura de uma execução

A operação medida é a **mesma alteração de status** pelo detalhe do pedido (`/gestao/pedidos/{pedido}` → "Status" → salvar), alternando entre dois status ativos (ex.: `em_analise` ↔ `em_compra_preparacao`) para que nenhuma execução seja no-op.

Opção 1 — aba Network do navegador: filtrar por `livewire/update`, executar a alteração e anotar a coluna *Time* (ou *Waiting for server response* + *Content download*) da requisição POST.

Opção 2 — `curl` repetível: na aba Network, botão direito na requisição `livewire/update` → *Copy as cURL*; salvar o comando em `g3-a.sh` (P5) e `g3-b.sh` (P0) **sem versionar** (contêm cookie de sessão e CSRF). Para cada execução, trocar o status de destino no corpo JSON e acrescentar:

```bash
-o /dev/null -s -w '%{time_total}\n'
```

`time_total` é o tempo até o último byte da resposta; como a conexão é encerrada antes dos callbacks de `defer()`, os envios ao Resend não entram nele se o runtime se comportar como descrito na §3.

### 6.3 Execução

1. 2 execuções de aquecimento em cada cenário, descartadas.
2. 20 execuções do cenário A (P5) e 20 do cenário B (P0), intercaladas (A, B, A, B, …), com ≥ 2 s entre execuções (o envio de 5 e-mails com `SEND_INTERVAL_MS = 600` leva ~3 s no processo do servidor; esperar evita medir contenção de thread).
3. Anotar os 20 tempos de cada cenário em milissegundos.

### 6.4 Cálculo do p95

Ordenar os 20 valores de cada cenário em ordem crescente; com 20 amostras, p95 = o **19º** valor (método do ranque mais próximo: `ceil(0,95 × 20) = 19`).

```bash
sort -n tempos-a.txt | sed -n '19p'   # p95 de A
sort -n tempos-b.txt | sed -n '19p'   # p95 de B
```

**Passa** se `p95(A) − p95(B) ≤ 150 ms`; **falha** caso contrário.

### 6.5 Conferências depois da medição

- `select email_status, count(*) from internal_notifications where pedido_id = <P5> group by 1` → todas `enviado` (nenhuma `pendente` presa).
- Os 5 destinatários receberam os e-mails da última execução.
- Nenhum log de aplicação contém e-mail, token nem texto de observação (só `internal_notification_id`, `pedido_event_id`, `exception_class`).

### 6.6 Se falhar

1. Os e-mails continuam corretos; nada é revertido.
2. Primeira hipótese: a versão do FrankenPHP em execução não tem o alias `fastcgi_finish_request()` (§3). Correção prevista: `if (function_exists('frankenphp_finish_request')) { frankenphp_finish_request(); }` como primeira instrução do callback em `InternalNotificationMailer::queueEvent()`, nova medição.
3. Reverter `defer()` para envio síncrono **não** é aceitável. A opção C (fila + worker no Railway) só entra com aprovação explícita.

### 6.7 Resultado (preencher após a medição)

| Campo | Valor |
|---|---|
| Data / hora da medição | _(pendente)_ |
| Deploy medido (id Railway) | _(pendente)_ |
| Método (Network / curl) | _(pendente)_ |
| p95 cenário A — 5 destinatários (ms) | _(pendente)_ |
| p95 cenário B — 0 destinatários (ms) | _(pendente)_ |
| Diferença p95(A) − p95(B) (ms) | _(pendente)_ |
| Critério | ≤ +150 ms |
| **Resultado (passa/falha)** | _(pendente)_ |
| Responsável | _(pendente)_ |
| Observações | _(pendente)_ |
