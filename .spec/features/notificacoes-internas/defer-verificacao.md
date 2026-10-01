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
