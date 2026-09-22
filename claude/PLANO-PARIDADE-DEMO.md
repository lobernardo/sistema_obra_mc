# PLANO — Paridade com o demo de apresentação

**Data:** 22/09/2026
**Base:** `origin/build/v0-demo-laravel` em `5d36ba2`
**Origem:** `claude/COMPARATIVO-DEMO-VS-PRODUCAO.md`
**Status:** Plano. Nenhuma alteração executada. Execução via BC Harness mediante autorização.

---

## PRINCÍPIO

**Nada do sistema atual é removido.** Todo item deste plano é acréscimo. Onde o demo e o sistema divergem na forma (ex.: o dashboard tem os dados em texto, o demo tinha em gráfico), a solução é **somar a camada que falta, preservando a existente** — o texto vira a alternativa acessível do gráfico, não o seu substituto.

O sistema atual tem coisas que o demo não tinha (busca textual, filtros de data, paginação, gestão de usuários, auditoria). Tudo permanece.

---

## PRÉ-REQUISITO BLOQUEANTE

> **A correção da normalização de e-mail precisa estar no ar antes deste plano começar.**
> Ver `claude/DIAGNOSTICO-LOGIN-E-FEEDBACK-CLIENTE.md`. Sem ela, o cliente não consegue entrar para validar nada do que for entregue aqui, e o trabalho fica invisível.

---

## O QUE ESTE PLANO **NÃO** INCLUI

| Item | Por quê |
|---|---|
| Seletor de perfil no login | Era artifício do demo ("usuários já provisionados"). Implementar troca de papel é mudança de modelo de autorização, não paridade. Resolve-se entregando três contas ao cliente. |
| Cadastro de obras | Não estava no demo. Escopo novo — plano separado. |
| Campo de observações | Não estava no demo. Escopo novo. |
| Aprovação, valor, fornecedor, itens estruturados, anexos | Não estavam no demo. Escopo novo. |

---

## ARQUITETURA EXISTENTE QUE O PLANO REAPROVEITA

Entender isto é o que torna o plano barato:

| Peça existente | O que já faz | Como o plano usa |
|---|---|---|
| `resources/views/components/pedido-table.blade.php` | Tabela única usada pelas **três** listagens (Obra, Suprimentos, Gestão) | Uma alteração propaga para as três — e quebra as três se errar |
| `App\Services\DashboardIndicatorsService` | Já devolve `volumeTotal`, `pendentes`, `atrasados`, `porStatus`, `porObra`, `prazos` | **Os gráficos não exigem nenhuma query nova.** Só falta acrescentar `entregues` |
| `App\Domain\Pedidos\AtrasoClassifier` | `isAtrasado()` e `scopeAtrasado()` | KPIs e filtros novos |
| `App\Domain\Pedidos\PendenteClassifier` / `PrazoClassifier` | Classificação pendente / dentro-vencendo-atrasado | KPIs e gráfico de prazos |
| `Pedido::visibleTo(User)` | Escopo de visibilidade da Obra | Continua aplicado **antes** de qualquer filtro novo |
| `Dashboard::drillDownUrl()` + `Gestao\TodosPedidos` aceitando `atrasado`/`pendente` por querystring | Drill-down já funciona | Estender para `entregue` |
| Padrão de filtro em Blade (`form-label`, `form-control`, `wire:model.live`, `fieldset`+`legend`) | Padrão visual estabelecido | Todo filtro novo segue este padrão |

---

# FASE 1 — Legibilidade das listagens

**Impacto:** alto · **Esforço:** trivial · **Risco:** baixo
**Problema que resolve:** hoje a lista mostra código, obra, datas e status — mas não **o que foi pedido**. O usuário precisa abrir cada pedido para saber se é cimento ou uma betoneira. No demo isso estava na lista.

### 1.1 Coluna de descrição

**Arquivo:** `resources/views/components/pedido-table.blade.php`

Acrescentar coluna **"Itens"** após "Obra":

```blade
<td class="max-w-[22rem]">
    <span class="line-clamp-2 text-text-muted" title="{{ $pedido->items_description }}">
        {{ Str::limit($pedido->items_description, 90) }}
    </span>
</td>
```

Cabeçalho: `<th>Itens</th>`. Atualizar o `colspan` do estado vazio de `8` para `9` (e para `10` após 1.2).

### 1.2 Coluna "Solicitado em"

Mesma tabela, após "Obra":

```blade
<td class="whitespace-nowrap">{{ $pedido->requested_at->format('d/m/Y') }}</td>
```

`requested_at` já vem carregado — nenhuma query adicional.

### 1.3 Decisão obrigatória: responsividade

A tabela fica com **10 colunas**. Hoje já tem `overflow-x-auto`, ou seja, rola lateralmente. **O perfil Obra usa celular em campo** — dez colunas com rolagem horizontal é ruim.

Três caminhos, em ordem de recomendação:

**A — Variante card no mobile (recomendado).** Tabela em `md:` para cima; abaixo disso, cards empilhados com código, itens, status, data necessária e atraso. Mais trabalho, resolve de verdade, e melhora o uso real em obra.

**B — Colunas configuráveis por chamada.** `x-pedido-table` ganha prop `:columns` e cada tela declara as suas. A Obra fica enxuta, Suprimentos e Gestão completas. Mantém tudo, só não mostra tudo em toda tela.

**C — Não fazer nada.** Aceitar a rolagem lateral.

> Se o discovery com o Marcelo confirmar uso intenso em celular, **A**. Caso contrário, **B** é o melhor custo-benefício.

### Testes da fase

- Feature: a tabela renderiza `items_description` truncada e o `title` traz o texto completo
- Feature: `requested_at` aparece formatado `d/m/Y`
- Browser: atualizar `tests/Browser/DemoRoteiroTest.php` (o roteiro conta colunas)
- Browser: `ResponsiveIdentityTest` — se escolher o caminho A, novo caso para o breakpoint

---

# FASE 2 — Filtros

**Impacto:** alto · **Esforço:** baixo/médio · **Risco:** médio (segurança na tela da Obra)
**Problema que resolve:** o demo tinha Obra, Status, Prioridade e Responsável em todas as listagens. O sistema tem busca textual e intervalos de data — que o demo não tinha — mas nenhum dos quatro selects. A tela da Obra não tem filtro nenhum.

### 2.1 Suprimentos › Todos os Pedidos

**Arquivos:** `app/Livewire/Suprimentos/TodosPedidos.php`, `resources/views/livewire/suprimentos/todos-pedidos.blade.php`

Acrescentar **sem remover nada**:

```php
#[Url] public ?int $obraId = null;
#[Url] public ?int $statusId = null;
#[Url] public ?int $priorityId = null;
#[Url] public ?int $responsibleId = null;
```

No método `pedidos()`, após os filtros já existentes:

```php
if ($this->obraId)        { $query->where('obra_id', $this->obraId); }
if ($this->statusId)      { $query->where('status_id', $this->statusId); }
if ($this->priorityId)    { $query->where('priority_id', $this->priorityId); }
if ($this->responsibleId) { $query->where('responsible_id', $this->responsibleId); }
```

`updating()` já chama `resetPage()` para qualquer propriedade ≠ `page` — os novos filtros entram nisso de graça.

No `render()`, passar as listas: `Obra::orderBy('name')`, `Status::ordered()`, `Priority::ordered()`, `User::suprimentos()->orderBy('name')` — exatamente as mesmas fontes que o `Dashboard` já usa.

Blade: quatro `<select class="form-control" wire:model.live>` dentro de um `<fieldset>` com `<legend>Filtros</legend>`, seguindo o padrão do dashboard. Cada um com opção vazia ("Todas" / "Todos").

Acrescentar **"Limpar filtros"** — o demo tinha, o sistema não. Método `limparFiltros()` que reseta os filtros novos **e** os existentes.

### 2.2 Gestão › Todos os Pedidos

**Arquivos:** `app/Livewire/Gestao/TodosPedidos.php`, blade correspondente.

Idêntico ao 2.1. O componente já tem `pendenteOnly` vindo por querystring — os novos `#[Url]` convivem e habilitam o drill-down da Fase 3.

### 2.3 Obra › Acompanhamento

**Arquivos:** `app/Livewire/Obra/Acompanhamento.php`, blade correspondente.

Hoje o componente não tem nenhuma propriedade de filtro. Acrescentar:

```php
#[Url] public ?int $obraId = null;
#[Url] public ?int $statusId = null;
#[Url] public bool $atrasoOnly = false;
public string $search = '';
```

E `use WithPagination` já está lá; falta o `updating()` com `resetPage()`.

> **⚠️ Regra de segurança inegociável.** `visibleTo(Auth::user())` continua sendo aplicado **antes** de qualquer filtro. O `obraId` só restringe dentro do conjunto já visível — nunca amplia. O select só lista `Auth::user()->obras`. Um `obraId` forjado na URL para uma obra de outro usuário deve retornar **vazio**, jamais dados.

O demo dava à Obra: Obra, Status, Somente atrasados. Acrescento busca textual por consistência com as outras duas telas (o sistema já a tem lá; não faz sentido a Obra ser a única sem).

### Testes da fase

- Feature, por tela: cada filtro isolado reduz o conjunto corretamente
- Feature: filtros combinados
- Feature: "Limpar filtros" zera tudo, inclusive os pré-existentes
- **Segurança (crítico):** usuário Obra com `?obraId=` de obra alheia → resultado vazio. Este teste vai em `tests/Feature/Authorization/PedidoVisibleToScopeTest.php`, que já existe
- Feature: filtro na querystring é lido no carregamento (habilita o drill-down)
- Feature: mudar filtro volta para a página 1

---

# FASE 3 — Indicadores

**Impacto:** médio/alto · **Esforço:** baixo · **Risco:** baixo

### 3.1 KPIs no topo de Suprimentos › Pedidos

O demo mostrava **Total · Pendentes · Atrasados** acima da lista. Suprimentos abre essa tela todo dia — é o primeiro lugar onde se olha.

**Decisão:** os KPIs refletem **os filtros aplicados**, não o total geral. É o comportamento do Dashboard e é mais útil (filtrou por obra, vê os números daquela obra). Divergência consciente do demo, a favor do usuário.

Implementação: os classifiers já existem. No `render()`, um `clone` da query antes da paginação, com `count()` para cada indicador. Reutilizar os mesmos componentes visuais de KPI do dashboard.

### 3.2 KPI "Entregues" no Dashboard

**Arquivo:** `app/Services/DashboardIndicatorsService.php`

O demo tinha quatro KPIs, o sistema tem três. Acrescentar:

```php
'entregues' => $pedidos
    ->filter(fn (Pedido $p) => $p->status?->slug === StatusSlug::Entregue->value)
    ->count(),
```

No blade, o quarto card, no mesmo padrão dos outros três.

### 3.3 Drill-down de "Entregues"

`Dashboard::drillDownUrl()` já monta URLs com `atrasado` e `pendente`. Acrescentar suporte a `entregue`, e em `Gestao\TodosPedidos` a leitura desse parâmetro — que, com a Fase 2 pronta, é só mapear para `statusId` do status Entregue.

### Testes da fase

- Feature: `DashboardIndicatorsService` devolve `entregues` correto, com e sem filtros
- Feature: KPIs de Suprimentos respeitam os filtros aplicados
- Feature: drill-down de Entregues chega na listagem já filtrada

---

# FASE 4 — Visualização e a tela ausente

**Impacto:** alto · **Esforço:** médio · **Risco:** médio

### 4.1 Gráficos no Dashboard

O demo tinha três gráficos Chart.js. O sistema tem **as mesmas três seções com os mesmos dados**, em números.

**Decisão técnica: SVG inline no Blade, sem biblioteca.** Justificativa:

1. **Livewire re-renderiza o DOM.** Chart.js em `<canvas>` exige destruir e reconstruir a instância a cada atualização — e o dashboard tem cinco filtros reativos. É a fonte clássica de gráfico que some ou duplica.
2. **Zero dependência nova.** Chart.js seria a primeira dependência JS de runtime do projeto.
3. **Volume trivial.** 5 status, 3 faixas de prazo, N obras. Não precisa de biblioteca.
4. **Acessibilidade de graça.** Os números em texto continuam ao lado do SVG — alternativa textual nativa, sem esforço extra.

| Seção | Forma |
|---|---|
| Distribuição por status | Barras horizontais, largura proporcional, cor do `status-badge` já existente |
| Prazos | Donut em SVG (`stroke-dasharray`), três fatias: dentro do prazo, vencendo, atrasado |
| Visão por obra | Barras horizontais |

O `DashboardIndicatorsService` já devolve as três coleções prontas. **Nenhuma query nova.** É puramente apresentação.

Cada gráfico acompanha `role="img"` e `aria-label` descritivo, e as cores respeitam os tokens de tema claro/escuro já definidos.

### 4.2 Tela "Visão Geral" de Suprimentos

A única tela do demo que simplesmente não existe. É o que o Marcelo procurou quando disse *"não identifiquei a área de suprimentos"*.

**Novos arquivos:**
- `app/Livewire/Suprimentos/VisaoGeral.php`
- `resources/views/livewire/suprimentos/visao-geral.blade.php`
- Rota: `Route::get('/visao-geral', VisaoGeral::class)->name('visao-geral')` dentro do grupo `can:is-suprimentos`
- Item de menu em `layouts/app.blade.php`, na seção de Suprimentos

**Conteúdo, conforme o demo:**
1. KPIs: Total de pedidos · Atrasados · Entregues hoje
2. Contador por cada um dos 5 status
3. Atalho "Abrir Kanban →"
4. Tabela dos 5 pedidos mais recentes, com link "Ver todos"

**Implementação:** reaproveita `DashboardIndicatorsService` sem modificação. O serviço é agnóstico de papel — não aplica `visibleTo`, o que é correto aqui porque **Suprimentos enxerga todas as obras por política** (`PedidoPolicy::view` devolve `true` para suprimentos). Registrar isso em comentário, para que ninguém reutilize o serviço em contexto de Obra sem antes adicionar o escopo.

"Entregues hoje" é o único indicador novo: pedidos com status Entregue e `expected_delivery_at` = hoje.

**Navegação:** a home de Suprimentos continua sendo `/suprimentos/pedidos`, como no demo. A Visão Geral entra como terceiro item do menu.

### Testes da fase

- Feature: rota `/suprimentos/visao-geral` exige papel suprimentos; Obra e Gestão recebem 403
- Feature: os indicadores da tela batem com os dados semeados
- Feature: "Entregues hoje" conta só o dia corrente
- Feature: o dashboard renderiza os SVGs **e** mantém os números em texto
- Browser: os gráficos sobrevivem à troca de filtro (o risco real que motivou a escolha por SVG)
- Browser: gráficos legíveis em tema claro e escuro

---

## SEQUÊNCIA E ENTREGÁVEIS

| Fase | Entrega isolada ao cliente | Depende de |
|---|---|---|
| 0 — correção de e-mail | O cliente consegue entrar | — |
| 1 — legibilidade | "Agora eu vejo o que é cada pedido" | 0 |
| 2 — filtros | "Agora eu acho o que procuro" | 1 |
| 3 — indicadores | "Agora eu vejo a situação de imediato" | 2 |
| 4 — visualização + Visão Geral | "Agora está igual ao que vocês apresentaram" | 3 |

Cada fase é um deploy independente e demonstrável. Recomendo **não** juntar tudo num único release: entregar de forma incremental reconstrói a confiança que a entrega anterior consumiu.

---

## RISCOS

| # | Risco | Mitigação |
|---|---|---|
| 1 | `x-pedido-table` é compartilhado pelas três telas — um erro quebra as três de uma vez | Testar as três telas em toda fase; `DemoRoteiroTest` funciona como rede de segurança |
| 2 | Filtro de obra na tela da Obra vira vetor de vazamento de dados | `visibleTo` aplicado **antes** de qualquer filtro; teste de autorização obrigatório com `obraId` forjado |
| 3 | `DashboardIndicatorsService` faz `->get()` e filtra em PHP | Aceitável no volume atual. Acima de ~5.000 pedidos vira problema. Registrar como dívida; migrar para agregação em SQL quando o volume subir |
| 4 | 10 colunas na tabela, com uso em celular no canteiro | Decisão 1.3, preferencialmente o caminho A |
| 5 | Gráfico quebrando no re-render do Livewire | Motivo da escolha por SVG inline em vez de Chart.js; teste de browser trocando filtro |
| 6 | `tests/Browser/DemoRoteiroTest.php` quebra a cada fase | Atualizar junto, na mesma fase — nunca depois |
| 7 | Contraste dos gráficos no tema escuro | Usar exclusivamente os tokens de tema já existentes; nenhuma cor literal |

---

## DOCUMENTAÇÃO OBRIGATÓRIA AO FIM

Conforme a diretriz do projeto:

- **AI_CONTEXT.md** — nova rota `/suprimentos/visao-geral`, novo componente, novos filtros por tela, nova chave `entregues` no serviço de indicadores
- **CLAUDE.md** — registrar a decisão "gráficos em SVG inline, sem biblioteca de chart" e a regra "`visibleTo` antes de qualquer filtro"
- **docs/onboarding-albuquerque.md** — revisar a seção de limites conhecidos: filtros e Visão Geral deixam de ser limitação

---

## EXECUÇÃO VIA BC HARNESS

O plano está estruturado para virar SPEC/PLAN/PHASES direto, no mesmo formato do `security-hardening-production` (commits `spec(...)` → `feat(phase-N)`).

Sugestão de nome da branch: `feat/paridade-demo-v0`.

As quatro fases mapeiam uma a uma para fases do Harness, e cada uma tem critério de aceite verificável por teste. Nenhuma depende de decisão de produto — exceto a **decisão 1.3 (responsividade)**, que precisa ser resolvida antes de a Fase 1 começar.
