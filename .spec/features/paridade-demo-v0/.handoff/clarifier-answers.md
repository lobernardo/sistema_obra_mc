# Respostas às questões do clarifier — paridade-demo-v0

## Q-01 — Fonte do select de obra na listagem da Obra — RESOLVIDO (correção de segurança)

**Restaurar a restrição do plano principal (`claude/PLANO-PARIDADE-DEMO.md:161`), que se perdeu na redação do RF-18.**

- `/obra/pedidos`: o conjunto de opções é `Auth::user()->obras()->orderBy('name')` — **as obras do próprio usuário**, e **sem** `->active()` (obras inativas do usuário continuam filtráveis, decisão 3 já tomada).
- `/suprimentos/pedidos` e `/gestao/pedidos`: `Obra::query()->orderBy('name')` sem escopo, como o `Gestao\Dashboard::render()` já faz — esses papéis enxergam todas as obras por política.

Reescrever o RF-18 com essa cláusula específica por tela, em vez de uma regra única para as três. Motivo registrado no SPEC: com o conjunto sem escopo, o select de um usuário Obra enumeraria o nome de toda obra da empresa — defeito de divulgação que o RF-17 não pega (ele afirma contagem de linhas) e que o `ObraVisibleToGuardTest` não pega (ele inspeciona statements `Pedido::`, não `Obra::`).

**Teste adicional obrigatório**, em `tests/Feature/Authorization/PedidoVisibleToScopeTest.php`, ao lado do caso de `obraId` forjado: o **select renderizado** de um usuário Obra não contém o nome de nenhuma obra alheia.

## Q-02 — Escopo do `#[Url]` — RESOLVIDO: migrar todo o estado de filtro

Todo o estado de filtro das três listagens passa a `#[Url]`; as leituras manuais em `mount()` (`request()->boolean('atrasado')`, `request()->has('pendente')`, `request()->query('requestedFrom'|'requestedTo')`) são **removidas**.

Motivo: `mount()` não re-executa num update Livewire. Misturar os dois mecanismos faz o parâmetro antigo sobreviver na URL depois de "Limpar filtros" e voltar sozinho no próximo reload — o que torna o AC do RF-19 ("a URL resultante não carrega parâmetro de filtro") insatisfatível e contradiz o RF-23 (drill-down exato).

Condições:
- As URLs de drill-down que hoje funcionam (`?atrasado=true`, `?pendente=true`, `?requestedFrom=`, `?requestedTo=`) **continuam funcionando** — usar aliases/`as` do `#[Url]` ou manter os mesmos nomes de parâmetro.
- Usar `except` para que valores default não poluam a URL.
- Os testes que hoje afirmam o comportamento de `mount()` são **atualizados**, não removidos.
- **CT-02 passa a ser declarado como contrato NOVO introduzido por esta feature**, não como restatement de algo pré-existente. Motivo verificado no HEAD: `Suprimentos\TodosPedidos::mount()` não lê nada do request (nenhum dos seus 6 filtros é endereçável por URL) e `Gestao\TodosPedidos::mount()` lê só `atrasado`, `pendente`, `requestedFrom`, `requestedTo` — `search` e `neededAt*` não são lidos em lugar nenhum.
- Isto **não** conta como remoção de comportamento para efeito do RF-32: nenhum filtro perde função; eles ganham endereçabilidade por URL. Registrar essa leitura explicitamente no RF-32.

## Q-03 — Definição de "Entregues hoje" — RESOLVIDO: evento `entrega` datado de hoje (fecha NC-02)

Definição: pedido com status `entregue` **e** que possua um `pedido_event` do tipo `entrega` com `created_at` no dia corrente. Mede a entrega que de fato aconteceu, não a previsão.

Rejeitada a definição `expected_delivery_at = today` porque a coluna é nullable e só Suprimentos a preenche — toda entrega sem previsão registrada sumiria da contagem.

Consequências contratuais a aplicar **na mesma edição**:
- `DashboardIndicatorsService::compute()` ganha uma **8ª chave**, `entreguesHoje`, calculada com **uma** consulta adicional (`whereExists` sobre `pedido_events` × `event_types.slug = 'entrega'`).
- **CT-05 passa de 7 para 8 chaves**; o AC do RF-22 que afirma "7 chaves" é atualizado para 8; a array shape documentada no PHPDoc do serviço acompanha.
- **RNF-10 é emendado** para permitir explicitamente essa única consulta adicional, mantendo no restante a dívida aceita de `->get()` + filtragem em PHP.
- O RF-28 (Visão Geral reusa o serviço sem introduzir segunda codificação) continua válido: a regra de "entregue hoje" fica **dentro** do serviço, nunca replicada no componente.

Risco operacional a registrar no SPEC (não muda a decisão): o `DemoSeeder` grava os eventos com `created_at = useCurrent()`, então o KPI mostra N no dia em que o seed roda e 0 nos dias seguintes. **Operacional: rodar `php artisan db:seed` no dia da demonstração.** Não inventar política de datas no seeder.

## Q-04 — Mecanismo de cor do SVG — RESOLVIDO: utilitários `fill-*` com mapa literal (fecha NC-01)

O Tailwind 4 gera `fill-success` / `fill-warning` / `fill-atraso` automaticamente a partir dos `--color-*` do bloco `@theme`, sem nenhuma alteração de CSS.

Implementação: um mapa `$prazoFills = ['dentro_do_prazo' => 'fill-success', 'vencendo_em_breve' => 'fill-warning', 'atrasado' => 'fill-atraso']`, espelhando o `$prazoColors` que o `dashboard.blade.php` já usa. **A classe é sempre literal e completa.**

**Proibido interpolar** (`fill-{{ $situacao }}`, `stroke-{{ … }}`): o projeto não tem safelist (só duas linhas `@source` em `resources/css/app.css:3-4`), então a classe interpolada nunca é gerada e o donut renderiza preto **apenas no build de produção** — invisível em teste, visível para o cliente.

Dois testes obrigatórios, porque o AC atual do RF-25 (sem hex, sem `rgb(`, sem `hsl(`, sem classe de paleta) passa nas três opções e não discrimina nem pega a armadilha:
1. o markup do donut não contém `fill-{{` nem `stroke-{{`;
2. a saída de `npm run build` contém os três utilitários gerados — é a única asserção que protege o render de produção.

## Q-05 — Filtros da listagem da Obra — RESOLVIDO: manter o conjunto reduzido do plano

`/obra/pedidos` recebe **obra, status, somente atrasados e busca textual** — exatamente o que `claude/PLANO-PARIDADE-DEMO.md:153-163` especifica. **Não** recebe prioridade nem responsável.

O documento principal é a fonte de verdade declarada pelo desenvolvedor, e a redução é coerente: o perfil Obra não define prioridade nem é dono do campo responsável. O AC-2 do handoff generalizou demais ao dizer "as três listagens ganham filtros de obra, status, prioridade e responsável"; **o plano vence**.

Acrescentar uma frase ao RF-16 registrando a divergência em relação ao AC-2 e sua justificativa, para que a redução fique como decisão e não como omissão.

## Correções menores apontadas pelo clarifier — aplicar todas

1. **RF-07**: o comando de diagnóstico (`users:email-case-report`) deve varrer **`users` e `password_reset_tokens`**, porque o RF-06 aborta a migration por colisão em qualquer uma das duas. Sem isso o operador pode receber "Nenhuma colisão encontrada." e ainda assim ver a migration falhar no deploy.
2. **RF-25**: o AC proíbe hex/`rgb(`/`hsl(` em arquivos "adicionados por esta feature" sob `resources/views/` — mas o donut é **edição** do `dashboard.blade.php` existente. Reescrever como "adicionados **ou modificados**".
3. **RF-01**: o AC (nenhum `mb_strtolower` aplicado a e-mail fora da função canônica) é uma varredura estática sobre `app/`. Então `AuthenticationRateLimiter::normalizeEmail()` (`app/Services/AuthenticationRateLimiter.php:34`) precisa **delegar** à função canônica, não duplicá-la, ou a varredura acusa justamente a função que o RF-01 abençoa. Deixar isso explícito no RIGID, não só no AC.
