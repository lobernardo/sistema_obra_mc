# Phases: obras-associacoes-cadastro-convites

Gerado por /plan a partir de PLAN.md — view executável para `./ralph.sh .spec/features/obras-associacoes-cadastro-convites/PHASES.md`.
Branch: `build/v0-demo-laravel` · Base: `c987df8` · 28 tarefas em 9 fases · fatia 1 de 3 (fatias 2 e 3 fora de escopo). SPEC v1.3 (F-10 toolbar provisória; F-13 autoassociação de Suprimentos intencional com aviso não bloqueante) sobre v1.2 (RF-38/NC-08: sigilo do token do convite, decisão FINAL). Correções da revisão cruzada aplicadas: F-05, F-10, F-13, F-14(a); revisão cruzada v2 (fatia 1): N-03, N-04, N-05; defaults D-5, D-6, D-8, D-9, D-10 confirmados (pertencem às fatias 2 e 3, sem tarefa nesta fatia).

**Pré-requisito de execução (gate, não é tarefa) — antes de qualquer execução do Ralph:** as mudanças de `/ai-context` nos 8 arquivos `docs/agents/*.md` (hoje modificados e não commitados) entram num commit separado, somente de documentação, contendo apenas `docs/agents/*.md`. Verificação: `git status --short docs/agents` vazio e `git show --stat --format= HEAD -- . ':!docs/agents'` vazio. As pastas `.spec/` das fatias 2 e 3 não entram nesse commit e não são bloqueadas por ele. Detalhes no topo do PLAN.md.

Regras válidas para todas as fases:
- Código PHP compatível com **8.4**. Rodar `vendor/bin/pint --dirty --format agent` antes de finalizar qualquer mudança PHP.
- Criar arquivos com `php artisan make:* --no-interaction`. **Nenhuma dependência nova.**
- Textos de interface em PT-BR, identificadores em inglês, segmentos de URL em PT-BR. Marca apenas via `config('app.name')`.
- Componentes Livewire nunca gravam direto: `mount()` re-checa a habilidade, cada método mutante chama `authorize()` e delega a uma Action, e a Action valida, guarda o ator e grava mutação + auditoria no mesmo `DB::transaction`.
- `Pedido::visibleTo` e `PedidoPolicy` não mudam, e `manage-users` continua só Gestão.
- Um processo Pest por vez contra o PostgreSQL de teste em `127.0.0.1:5434`. `--filter` sem `--testsuite=Feature` arrasta `tests/Browser` para a execução.
- **"A fase fecha verde" = Unit + Feature verdes** (N-03). `tests/Browser` não é garantido fase a fase: nesta fatia ele só é garantido no gate T28 (Phase 8).
- Fases somente de documentação (Phase 9) re-executam toda `tests/Feature/Compliance` (N-05).
- Testes que codificam a regra removida ("obra ⇒ ≥1 obra; demais ⇒ nenhuma") ou o booleano `obras.is_active` são **atualizados, nunca apagados**.

## Phase 1: Status da obra e migração

Antes de implementar, leia:
1. `.spec/features/obras-associacoes-cadastro-convites/SPEC.md` — requisitos RIGID que esta fase cobre (RF-03, RF-04, RF-05, RF-35, RF-36, RF-36b, CT-01, UI-09, RNF-06)
2. `.spec/features/obras-associacoes-cadastro-convites/PLAN.md` — decomposição completa, dependências e riscos

Antes do merge desta fase, o Claude executa em produção, via Railway e somente leitura, a consulta de colisão de nomes da tabela de Riscos do PLAN — apenas depois de o desenvolvedor confirmar naquele momento (decisão em `.handoff/plan-decisions.md`). Zero linhas → merge; havendo colisão, o desenvolvedor renomeia as obras à mão antes do merge. O Railpack executa `migrate` a cada start do container. T02 e T03 vão no mesmo commit.

- [ ] T01 — Migration: status da obra, `responsavel`, remoção de `is_active` e unicidade do nome
      Arquivos: `database/migrations/<timestamp>_convert_obras_activity_to_status.php` (novo)
      Mudança: estrutura espelhada em `2026_09_22_155011_normalize_user_emails_and_add_lower_unique_index.php`. Em `up()`, num único `DB::transaction`, nesta ordem:
      (1) detectar colisões de `lower(btrim(name))` em `obras` e, havendo alguma, lançar `RuntimeException` PT-BR nomeando os valores, antes de qualquer escrita;
      (2) adicionar `status varchar(20) NULL` e `responsavel varchar(255) NULL`;
      (3) preencher o status a partir de `is_active`: `true` → `em_andamento`, `false` → `concluido`;
      (4) tornar `status` `NOT NULL DEFAULT 'a_iniciar'` e criar o check `obras_status_check` com os 3 valores;
      (5) remover `obras.is_active`;
      (6) criar o índice único funcional `obras_name_normalized_unique` sobre `lower(btrim(name))`.
      `down()` recria `is_active` a partir do status e remove índice, check, `status` e `responsavel`. Sem `DELETE`/`TRUNCATE`/drop de tabela.
      `down()` é rollback **com perda de dados** (F-14a): A iniciar/Em andamento viram `is_active = true` e todo `responsavel` se perde; o docblock da migration declara isso em PT-BR.
      Cobre: RF-36, RF-36b, CT-01, RNF-06
      Acceptance criteria: após `migrate`, toda obra tem status não nulo com o mapeamento acima, `obras.is_active` não existe e o índice existe. Com duas obras colidindo em `lower(btrim(name))`, `migrate` falha sem criar `status`, check nem índice e mantém `is_active`. Nenhuma linha das 7 tabelas de RF-36 é removida. O docblock da migration declara que `down()` perde a distinção A iniciar/Em andamento e os valores de `responsavel`.
      Testes: cobertos por T04.

- [ ] T02 — `ObraStatus`, modelo `Obra`, factory e `DemoSeeder`
      Arquivos: `app/Enums/ObraStatus.php` (novo), `app/Models/Obra.php`, `database/factories/ObraFactory.php`, `database/seeders/DemoSeeder.php`, `tests/Unit/Enums/SlugEnumsTest.php`, `tests/Unit/Models/ObraStatusTest.php` (novo)
      Mudança: enum string com os casos `AIniciar`, `EmAndamento` e `Concluido`, `label()` PT-BR e `isActive()` (≠ Concluido), que é a única definição de "obra ativa".
      Em `Obra`: `#[Fillable(['name','responsavel','status','is_demo'])]`, cast de `status` para o enum, `scopeActive` reescrito como `where('status','!=','concluido')` e relação `invitations()`.
      Factory: padrão `EmAndamento`, estados `aIniciar()`, `emAndamento()` e `concluida()`, e remoção de `inactive()`.
      `DemoSeeder::seedObras()` grava `status = EmAndamento`.
      Cobre: RF-03, RF-35, CT-01
      Acceptance criteria: `ObraStatus::isActive()` é true para `a_iniciar` e `em_andamento` e false para `concluido`, e os rótulos são exatamente "A iniciar", "Em andamento" e "Concluído". O modelo `Obra` não referencia `is_active`. `DemoSeederIdempotencyTest` passa, com toda obra demo com status não nulo.
      Testes: `tests/Unit/Models/ObraStatusTest.php` — ativo/inativo e rótulos. `SlugEnumsTest` inclui `ObraStatus`.

- [ ] T03 — Consumidores de "obra ativa" e estado vazio da Nova Solicitação
      Arquivos: `app/Livewire/Gestao/Usuarios/Form.php` (só `obras()`), `resources/views/livewire/obra/nova-solicitacao.blade.php`, `app/Actions/Pedidos/CreatePedidoAction.php` (só docblock), e os testes `CreatePedidoActionTest`, `NovaSolicitacaoTest`, `ObraInativaPreservaHistoricoTest`, `PedidoVisibleToScopeTest` (só a chamada de factory), `AcompanhamentoTest`, `TodosPedidosFiltersTest`, `Adversarial/CrossObraTest` e `MassAssignmentTest`
      Mudança: `Form::obras()` troca `where('is_active', true)` pelo escopo `active()`. `CreatePedidoAction` segue usando `->active()` antes da transação e do gerador de código.
      O texto do estado vazio passa a ser "Nenhuma obra ativa está associada ao seu usuário. Fale com a Gestão ou com Suprimentos.".
      Nos testes, `inactive()` e `is_active` de obra viram `concluida()`/`status`, sem enfraquecer asserções.
      Casos novos: sequência de pedido não avança em rejeição (RF-04), e pedidos de obra Concluída continuam visíveis e filtráveis (RF-05).
      Cobre: RF-03, RF-04, RF-05, UI-09, RF-36
      Acceptance criteria: obra Concluída associada não aparece no select da Nova Solicitação. `obra_id` forjado dela resulta em 422 em `obra_id`, com `pedidos`, `pedido_events` e o `last_value` de `pedido_code_sequence` inalterados. O usuário obra associado lista e abre (200) pedido de obra Concluída. As listagens de Suprimentos e Gestão filtradas por essa obra retornam seus pedidos. `grep` por `is_active` em contexto de obra em `app/` não encontra nada.
      Testes: os arquivos listados acima, incluindo os casos novos de RF-04 e RF-05.

- [ ] T04 — Testes da migration de status e do esquema
      Arquivos: `tests/Feature/Migrations/ObraStatusMigrationTest.php` (novo), `tests/Feature/MigrationSchemaTest.php`
      Mudança: somente testes, no estilo de `EmailNormalizationMigrationTest`: rollback até antes da migration de T01, inserção via `DB::table` e nova execução.
      Cobre: RF-36, RF-36b, RNF-06
      Acceptance criteria:
      • backfill true → `em_andamento` e false → `concluido`, com a coluna `is_active` removida;
      • contagens das 7 tabelas de RF-36 idênticas antes e depois;
      • com "Obra X" e " obra x" a migration lança exceção nomeando os dois valores, sem `status`, check nem índice, e `is_active` preservado;
      • `obras_name_normalized_unique` existe;
      • `migrate:rollback` seguido de `migrate` é repetível; após o rollback, obra `a_iniciar` fica com `is_active = true`, `concluido` com `false`, e `responsavel` não existe mais (perda documentada em T01);
      • `MigrationSchemaTest` espera `status` e `responsavel` e a ausência de `is_active`.
      Testes: `tests/Feature/Migrations/ObraStatusMigrationTest.php` — os cenários acima.

## Phase 2: Convites e trilhas de auditoria (dados)

Antes de implementar, leia:
1. `.spec/features/obras-associacoes-cadastro-convites/SPEC.md` — requisitos RIGID que esta fase cobre (CT-02, CT-07, RF-22, RF-26, RF-27, RF-34, RF-35, RNF-01)
2. `.spec/features/obras-associacoes-cadastro-convites/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T05 — Tabela `obra_invitations`, modelo `ObraInvitation` e estado derivado
      Arquivos: `database/migrations/<timestamp>_create_obra_invitations_table.php` (novo), `app/Models/ObraInvitation.php` (novo), `database/factories/ObraInvitationFactory.php` (novo), `app/Enums/ObraInvitationState.php` (novo), `tests/Unit/Models/ObraInvitationStateTest.php` (novo)
      Mudança: tabela CT-02 com as colunas:
      • `obra_id` (FK restrict) e `token_hash char(64)` único;
      • `created_by` (FK restrict), `created_at` e `expires_at`;
      • `revoked_by`/`revoked_at` e `used_by`/`used_at`, anuláveis, com FKs restrict.
      A tabela não tem `updated_at`. Tem o check `revoked_at is null or used_at is null` e o índice `(obra_id, created_at)`.
      Modelo: `UPDATED_AT = null`, `#[Fillable]` só com `obra_id`, `token_hash`, `created_by` e `expires_at`, `#[Hidden(['token_hash'])]`, e as relações `obra`, `creator`, `revoker` e `user`.
      `state()` deriva Utilizado > Revogado > Expirado (`now() >= expires_at`) > Pendente. `isConsumable()` exige Pendente e obra ativa. `scopeConsumable()` é a forma SQL única reutilizada nos UPDATEs condicionais. `hashToken(#[\SensitiveParameter] string $token)` aplica sha256; o modelo nunca guarda nem expõe token em claro.
      Factory com os estados `revoked()`, `used()` e `expired()`.
      Cobre: CT-02, RF-26, RF-27, RNF-01, RF-38
      Acceptance criteria: um convite em cada estado recebe o rótulo correto. Com viagem no tempo, 23 h 59 min após a criação é válido e 24 h 00 min 01 s é inválido. Convite pendente de obra Concluída não é consumível. O banco rejeita linha com `revoked_at` e `used_at` preenchidos e `token_hash` duplicado.
      Testes: `tests/Unit/Models/ObraInvitationStateTest.php` — estados, fronteira de 24 h, obra Concluída e constraints.

- [ ] T06 — Trilhas append-only: `obra_admin_events` e `account_registration_events`
      Arquivos: duas migrations novas; os modelos `ObraAdminEvent` e `AccountRegistrationEvent` com factories; os enums `ObraAdminAction` e `AccountOrigin`; `app/Policies/ObraAdminEventPolicy.php`, `app/Policies/AccountRegistrationEventPolicy.php`, `app/Services/ObraAdminAuditRecorder.php`, `tests/Feature/Compliance/AuditTrailsAppendOnlyTest.php` e dois testes unitários de imutabilidade
      Mudança: as duas tabelas novas:
      • `obra_admin_events`: `actor_id` e `obra_id` (FKs restrict), `obra_invitation_id` anulável (restrict), `action`, `before`/`after` em json e `created_at`;
      • `account_registration_events`: `user_id` (restrict), `origin`, `obra_invitation_id` anulável, `ip` com 45 caracteres e `created_at`. Não tem senha nem e-mail.
      Enums:
      • `ObraAdminAction` com `obra_created`, `obra_updated`, `invitation_created`, `invitation_revoked` e `invitation_used`;
      • `AccountOrigin` com `novo_cadastro` e `convite`.
      Os dois modelos copiam o padrão de `UserAdminEvent`: `UPDATED_AT = null` e `updating`/`deleting` lançando `LogicException`. As policies negam update e delete.
      `ObraAdminAuditRecorder::record()` aceita apenas a whitelist `name`, `responsavel` e `status`, e `snapshot(Obra)` devolve esses três campos.
      O teste de conformidade só ganha acréscimos: os 2 modelos em `AUDIT_MODELS` e as 2 tabelas na regex de query builder.
      Cobre: CT-07, RF-22, RF-34
      Acceptance criteria: `update()`, `delete()` e `save()` em registro existente dos dois modelos lançam `LogicException`. Uma chave fora da whitelist lança exceção antes de gravar e deixa 0 linhas. `AuditTrailsAppendOnlyTest` passa com as listas estendidas.
      Testes: `tests/Unit/Models/ObraAdminEventImmutabilityTest.php`, `tests/Unit/Models/AccountRegistrationEventImmutabilityTest.php` e um teste do recorder.

- [ ] T07 — `demo:reset` com convites e trilhas novas
      Arquivos: `app/Console/Commands/ResetDemoData.php`, `tests/Feature/Console/ResetDemoDataTest.php`, `tests/Feature/Console/ResetDemoDataAuditTrailsTest.php`, `tests/Feature/Compliance/AuditTrailsAppendOnlyTest.php` (só o teste de `ResetDemoData`)
      Mudança: dentro da transação existente, depois dos pedidos demo e antes de `Obra::...->delete()`, usando apenas `DB::table`:
      (1) calcular os convites condenados: obra demo, ou `created_by`/`revoked_by`/`used_by` de usuário demo;
      (2) apagar os `obra_admin_events` de obra demo, de ator demo ou de convite condenado;
      (3) apagar os `account_registration_events` de usuário demo ou de convite condenado;
      (4) apagar os convites condenados.
      O restante da ordem existente é mantido. O teste de conformidade exige cada tabela nova exatamente uma vez via `DB::table`, antes do delete de `User`.
      Cobre: RF-35
      Acceptance criteria: com convites pendentes, usados e revogados e com auditorias de obras e usuários demo, `demo:reset --force` sai com 0 e sem violação de FK. Sobrevivem o convite de obra real criado por usuário real e suas auditorias, e o registro `novo_cadastro` de usuário real.
      Testes: `ResetDemoDataTest` e `ResetDemoDataAuditTrailsTest` — acréscimos com todas as combinações.

## Phase 3: Autorização e cadastro de obras

Antes de implementar, leia:
1. `.spec/features/obras-associacoes-cadastro-convites/SPEC.md` — requisitos RIGID que esta fase cobre (RF-01, RF-02, RF-06, RF-07, CT-01, CT-03, UI-03, UI-04)
2. `.spec/features/obras-associacoes-cadastro-convites/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T08 — Habilidade `manage-obras`, policies e guarda de Action
      Arquivos: `app/Providers/AppServiceProvider.php` (só `boot()`), `app/Policies/ObraPolicy.php` (novo), `app/Policies/ObraInvitationPolicy.php` (novo), `app/Actions/Obras/Concerns/GuardsObraAdministration.php` (novo), `tests/Feature/Authorization/RoleGatesTest.php`, `tests/Feature/Authorization/ObraPolicyTest.php` (novo)
      Mudança: `Gate::define('manage-obras')` concede gestao e suprimentos, separado de `manage-users`, que continua só Gestão.
      `ObraPolicy`: `viewAny`, `create`, `update` e `manageAssociations` passam pelo gate, e `delete` devolve sempre false.
      `ObraInvitationPolicy`: `create` e `revoke` passam pelo gate.
      O trait `ensureActorManagesObras()` lança `AuthorizationException` PT-BR.
      Cobre: RF-07, CT-03, RF-06, RF-37
      Acceptance criteria: `manage-obras` é true para gestao e suprimentos e false para obra e para usuário sem papel. `manage-users` continua true só para gestao. `ObraPolicy::delete` é false para todo papel. O trait lança exceção para ator obra.
      Testes: `RoleGatesTest` (acréscimos) e `ObraPolicyTest`.

- [ ] T09 — `CreateObraAction` e `UpdateObraAction`
      Arquivos: `app/Actions/Obras/CreateObraAction.php`, `app/Actions/Obras/UpdateObraAction.php` (novos), `tests/Feature/Actions/Obras/CreateObraActionTest.php`, `tests/Feature/Actions/Obras/UpdateObraActionTest.php` (novos)
      Mudança: as duas Actions chamam `ensureActorManagesObras` e validam em PT-BR:
      • `name` obrigatório, até 255 e com trim;
      • `responsavel` anulável e até 255, com vazio virando null;
      • `status` via `Rule::enum(ObraStatus)`.
      A unicidade compara `lower(btrim(name))`, com `whereKeyNot` na edição. Colisão dá 422 em `name` com "Já existe uma obra com este nome.".
      A transação grava a obra e a auditoria: `obra_created` com o snapshot, ou `obra_updated` apenas com as chaves alteradas. Sem mudança, nada é gravado.
      `UniqueConstraintViolationException` é capturada **fora** da closure e vira a mesma 422. Nenhum caminho exclui obra.
      Cobre: RF-01, RF-02, RF-06, CT-01, CT-07
      Acceptance criteria:
      • Gestão e Suprimentos criam obra com 1 linha e 1 auditoria (ator correto);
      • nome vazio ou `responsavel` com mais de 255 caracteres dão 422 sem linha; status fora dos 3 valores dá 422;
      • com "Obra Centro" existente, "  obra centro " dá 422 sem linha nem auditoria;
      • editar B para o nome de A dá 422 com B inalterada;
      • a corrida simulada no índice termina com 1 linha e a outra requisição recebendo `ValidationException`, nunca 500;
      • Em andamento → Concluído gera 1 auditoria com o status antes e depois, e reenvio idêntico gera 0;
      • contagens de `pedidos`, `pedido_events` e `obra_profile` da obra idênticas;
      • ator obra recebe `AuthorizationException`.
      Testes: os dois arquivos novos, incluindo a corrida simulada via hook `beforeExecuting`.

- [ ] T10 — Telas Obras: lista e formulário (criar/editar)
      Arquivos: `app/Livewire/Obras/Index.php`, `app/Livewire/Obras/Form.php`, `resources/views/livewire/obras/index.blade.php`, `resources/views/livewire/obras/form.blade.php` (novos), `routes/web.php`, `tests/Feature/Livewire/ObrasIndexTest.php`, `tests/Feature/Livewire/ObrasFormTest.php` (novos)
      Mudança: rotas `GET /obras` (`obras.index`), `/obras/nova` (`obras.create`) e `/obras/{obra}/editar` (`obras.edit`). Ficam no grupo `auth`+`active`, fora dos prefixos de papel, com `can:manage-obras`.
      `mount()` e `save()` autorizam e delegam às Actions de T09.
      Lista: paginada por 15, com Nome, Responsável ("—" quando vazio) e rótulo de Status, "Nova obra" e "Editar", sem exclusão.
      Formulário: select de status com exatamente 3 opções. Com Concluído selecionado, exibe o aviso "Obras concluídas deixam de receber novas solicitações. Nenhum pedido, histórico ou associação é excluído.".
      Reutiliza apenas os componentes visuais existentes.
      Cobre: UI-03, UI-04, RF-01, RF-02, RF-06, RF-07, CT-03
      Acceptance criteria:
      • 3 obras em 3 estados listadas com os rótulos corretos e sem controle de exclusão;
      • o select tem exatamente 3 opções, e o aviso aparece só com Concluído;
      • Gestão e Suprimentos recebem 200 nas 3 rotas; usuário obra recebe 403; visitante é redirecionado para `/login`; inativo sofre logout;
      • `save` forjado por usuário obra dá 403 e 0 linhas;
      • o middleware das rotas `obras.*` contém `auth`, `active` e `can:manage-obras`.
      Testes: `ObrasIndexTest` e `ObrasFormTest`.

## Phase 4: Convites — geração, revogação e listagem

Antes de implementar, leia:
1. `.spec/features/obras-associacoes-cadastro-convites/SPEC.md` — requisitos RIGID que esta fase cobre (RF-23, RF-24, RF-25, RF-26, RF-33, RF-34, RF-38, UI-05, RNF-01)
2. `.spec/features/obras-associacoes-cadastro-convites/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T11 — `GenerateObraInvitationAction` e `RevokeObraInvitationAction`
      Arquivos: `app/Actions/Obras/GenerateObraInvitationAction.php`, `app/Actions/Obras/RevokeObraInvitationAction.php` (novos), `tests/Feature/Actions/Obras/GenerateObraInvitationActionTest.php`, `tests/Feature/Actions/Obras/RevokeObraInvitationActionTest.php` (novos)
      Mudança: Geração:
      • chama o guarda do ator; obra Concluída dá 422 "Não é possível gerar convite para uma obra concluída." sem escrever nada;
      • o token é `bin2hex(random_bytes(32))` (64 hex minúsculos), e a transação grava `token_hash`, `created_by` e `expires_at = now + 24h` (mesmo `now` de `created_at`) junto com a auditoria `invitation_created`;
      • devolve `{invitation, url}`, com `url = url('/convite').'#'.$token` — token **só no fragmento**, nunca no path nem na query (RF-38);
      • o texto puro não vai para coluna, log, auditoria, mensagem/contexto de exceção nem `report()`.
      Revogação: chama o guarda do ator e executa um UPDATE condicional (`used_at` e `revoked_at` nulos e `expires_at > now`) gravando `revoked_by` e `revoked_at`. Se afetar 0 linhas, dá 422 "Somente convites pendentes podem ser revogados." com rollback. Senão grava `invitation_revoked`.
      Cobre: RF-23, RF-24, RF-25, RF-33, RF-34, RNF-01, RF-38, CT-02
      Acceptance criteria:
      • 1 geração cria 1 linha com `expires_at = created_at + 24h` e `revoked_*`/`used_*` nulos;
      • `parse_url` da URL: `fragment` é o token de 64 hex, `path` é exatamente `/convite`, não há `query`, e nem `path` nem `query` contêm o token;
      • o token puro não aparece em nenhuma coluna de `obra_invitations` nem de `obra_admin_events`;
      • 3 gerações para a mesma obra dão 3 URLs e 3 hashes distintos;
      • geração em obra Concluída dá 422, 0 convites e 0 auditorias;
      • revogar pendente preenche `revoked_by`/`revoked_at` com 1 auditoria; revogar convite usado, revogado ou expirado dá 422 com a linha inalterada;
      • gerar + revogar resulta em 2 registros em `obra_admin_events`;
      • ator obra recebe `AuthorizationException` nas duas Actions.
      Testes: os dois arquivos novos.

- [ ] T12 — Seção "Convites" no formulário de edição da obra
      Arquivos: `app/Livewire/Obras/Form.php`, `resources/views/livewire/obras/form.blade.php`, `tests/Feature/Livewire/ObraConvitesSectionTest.php` (novo)
      Mudança: só no modo edição.
      "Gerar convite" fica oculto em obra Concluída; a Action continua impondo a regra. O botão autoriza `create` e guarda a URL (`<APP_URL>/convite#<token>`) em `$generatedLink`, exibida uma vez com botão de copiar (Alpine `navigator.clipboard`) e o texto "Este link vale por 24 horas e não será exibido novamente.". `$generatedLink` é limpo em qualquer outra ação.
      A lista vem ordenada por `created_at desc`, com eager load de `creator`, `revoker` e `user`, e mostra criador, criado em, expira em, rótulo de estado, revogado por/em e utilizado por/em.
      "Revogar" só aparece em Pendente, com confirmação em duas etapas, e autoriza `revoke` antes da Action. O token e o hash nunca são renderizados.
      Cobre: UI-05, RF-26, RF-23, RF-25, RF-07
      Acceptance criteria: gerar exibe o link e o controle de copiar. Um novo `Livewire::test`, que simula o reload, não contém o link e lista o convite como Pendente com "Revogar". Cada estado recebe o rótulo correto. O HTML nunca contém `token_hash`. `generateInvitation` e `revokeInvitation` forjados por usuário obra dão 403.
      Testes: `ObraConvitesSectionTest`.

## Phase 5: Associações usuário × obra

Antes de implementar, leia:
1. `.spec/features/obras-associacoes-cadastro-convites/SPEC.md` — requisitos RIGID que esta fase cobre (RF-08..RF-15, RF-11b, RF-13b, UI-06, UI-10, CT-06, CT-07)
2. `.spec/features/obras-associacoes-cadastro-convites/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T13 — Relaxamento de `obraIdsRules` e regra de troca de papel
      Arquivos: `app/Actions/Usuarios/CreateUserAction.php`, `app/Actions/Usuarios/UpdateUserAction.php`, `tests/Feature/Actions/Usuarios/CreateUserActionTest.php`, `tests/Feature/Actions/Usuarios/UpdateUserActionTest.php`, `tests/Feature/Actions/Usuarios/UserAdminAuditTest.php`, `tests/Feature/Security/Adversarial/AuditTrailTest.php`
      Mudança: `obraIdsRules`:
      • obra e suprimentos: `obra_ids` `sometimes|array` e cada item `integer|distinct|exists:obras,id`, ou seja, 0..N;
      • gestao e demais: `prohibited` com "O perfil Gestão não pode ser associado a obras.".
      `UpdateUserAction`:
      • papel novo gestao: `detach()` total;
      • demais papéis: `sync` só quando a chave `obra_ids` vier no payload; se não vier, as associações ficam intactas (obra↔suprimentos mantém).
      O diff de snapshot existente continua gerando `obra_access_changed`. Os testes que codificam a regra removida são atualizados, nunca apagados; em `AuditTrailTest`, obra→suprimentos não gera mais `obra_access_changed` e a contagem esperada passa a 6.
      Cobre: RF-11, RF-11b, RF-12, RF-13b, CT-06
      Acceptance criteria:
      • Gestão cria usuário obra com `obra_ids = []` e 0 linhas em `obra_profile`, e usuário suprimentos com 2 obras e 2 linhas;
      • gestao com `[1]` dá 422 em `obra_ids` sem escrita;
      • obra com [A, B] mudado para papel gestao fica com 0 linhas e 1 `obra_access_changed` (antes [A, B], depois []);
      • suprimentos com [A] mudado para papel obra mantém [A] sem `obra_access_changed`;
      • contagens de `pedidos` e `pedido_events` inalteradas.
      Testes: os arquivos listados acima, mais a reexecução sem edição de `SessionAfterAdministrativeChangesTest` e `SessionAndRoleTest`.

- [ ] T14 — Formulário de Usuários da Gestão aceita 0..N obras para Obra e Suprimentos
      Arquivos: `app/Livewire/Gestao/Usuarios/Form.php`, `resources/views/livewire/gestao/usuarios/form.blade.php`, `tests/Feature/Livewire/UsuariosFormTest.php`
      Mudança: `selectedRoleIsObra()` vira `selectedRoleAcceptsObras()`, verdadeiro para obra e suprimentos. O seletor aparece para os dois, e `save()` envia `obra_ids` (inclusive `[]`) para os dois; para gestao não envia. Os textos do seletor e o subtítulo passam a citar Obra e Suprimentos. `manage-users` e `UserPolicy` ficam intocados.
      Cobre: UI-10, RF-13b
      Acceptance criteria: selecionar Suprimentos exibe `[data-obra-selector]`. Salvar usuário obra sem obra funciona. Salvar suprimentos com 2 obras cria 2 linhas. Selecionar Gestão oculta o seletor.
      Testes: `UsuariosFormTest` (acréscimos e atualização do caso "exige ≥1 obra").

- [ ] T15 — `AttachUserObrasAction` e `DetachUserObraAction`
      Arquivos: `app/Actions/Usuarios/AttachUserObrasAction.php`, `app/Actions/Usuarios/DetachUserObraAction.php` (novos), `tests/Feature/Actions/Usuarios/AttachUserObrasActionTest.php`, `tests/Feature/Actions/Usuarios/DetachUserObraActionTest.php` (novos)
      Mudança: as duas Actions usam `GuardsObraAdministration`, não `manage-users`.
      Alvo que não seja obra nem suprimentos dá 422 em `user_id`.
      Attach:
      • `obra_ids` obrigatório, com pelo menos 1 item; obra em qualquer status é aceita, inclusive Concluída;
      • obra duplicada no input ou já associada dá 422 nomeando a obra;
      • a transação grava o `attach()` (nunca `syncWithoutDetaching`) e a auditoria `obra_access_changed` com antes e depois;
      • violação da PK composta é capturada fora da closure e vira 422.
      Detach: obra não associada dá 422. Senão, `detach($obraId)` e a auditoria vão na mesma transação, sem tocar em `pedidos` nem em `pedido_events`.
      Autoassociação é intencional (RF-11 v1.3, F-13): nenhuma das Actions bloqueia `ator == alvo` nem alvo Suprimentos; os docblocks registram isso (é o que dá elegibilidade de criação na fatia 2).
      Cobre: RF-09, RF-10, RF-11, RF-12, RF-13, RF-15, CT-06, CT-07
      Acceptance criteria:
      • [A, B, C] cria exatamente 3 linhas, e falha forçada no 3º insert deixa 0 linhas e 0 auditorias;
      • [A] já associada dá 422 e 0 linhas; a corrida simulada termina com exatamente 1 linha e 422, nunca 500;
      • Suprimentos adicionando [A, B] gera 1 registro com ator Suprimentos, antes [] e depois [A, B];
      • após a remoção, `pedidos` e `pedido_events` ficam inalterados, o usuário removido recebe 403 no pedido da obra e Suprimentos e Gestão ainda recebem 200;
      • alvo gestao dá 422, e ator obra recebe `AuthorizationException`;
      • suprimentos com 0 e com 2 obras veem o mesmo conjunto de pedidos, e `PedidoVisibleToScopeTest` passa sem edição;
      • Suprimentos associa a obra A à **própria** conta: +1 linha, exatamente 1 `obra_access_changed` com ator = alvo, sem erro; idem para outro usuário Suprimentos.
      Testes: os dois arquivos novos.

- [ ] T16 — Tela Associações usuário × obra
      Arquivos: `app/Livewire/Associacoes/Index.php`, `resources/views/livewire/associacoes/index.blade.php` (novos), `routes/web.php`, `tests/Feature/Livewire/AssociacoesIndexTest.php` (novo)
      Mudança: rota `GET /associacoes` (`associacoes.index`) no grupo `can:manage-obras`; `mount()` autoriza `manageAssociations`.
      A busca por `lower(name)`/`lower(email)` reinicia a página. A consulta lista apenas usuários de papel obra ou suprimentos, com `with(['role','obras'])`, paginada por 15.
      Cada linha mostra papel, Ativo/Inativo e as obras (nome e rótulo de status), cada uma com "Remover" em duas etapas.
      Cada linha tem também um multi-select das obras ainda não associadas e o botão "Adicionar". A lista completa de obras é carregada uma vez por render e o diff é feito em PHP.
      Cada método mutante autoriza antes das Actions de T15.
      Aviso de autoedição (UI-06 v1.3, F-13): na linha em que o usuário é o autenticado, `alert-info` com `data-self-association-notice` e o texto "Você está editando as suas próprias associações."; o aviso nunca desabilita nem oculta "Adicionar"/"Remover".
      Cobre: RF-08, RF-11, UI-06, CT-03, RF-07
      Acceptance criteria:
      • a busca "maria" encontra "Maria" e "MARIA@x.com", e usuário Gestão que casa com o termo não é listado;
      • cada linha mostra todas as obras associadas, e adicionar 2 obras numa ação faz as duas aparecerem;
      • o primeiro clique em "Remover" não chama a Action;
      • usuário obra recebe 403 no GET e em `attach`/`confirmRemoval` forjados;
      • usuário Suprimentos vê `data-self-association-notice` só na própria linha (exatamente 1 ocorrência) e ainda consegue adicionar e remover as próprias obras (+1 / −1 linha).
      Testes: `AssociacoesIndexTest`.

## Phase 6: Novo Cadastro público

Antes de implementar, leia:
1. `.spec/features/obras-associacoes-cadastro-convites/SPEC.md` — requisitos RIGID que esta fase cobre (RF-14, RF-16..RF-22, RF-19b, UI-01, UI-02, CT-04, CT-07)
2. `.spec/features/obras-associacoes-cadastro-convites/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T17 — Limitadores `register`, `register-ip` e `invite-ip`
      Arquivos: `app/Providers/AppServiceProvider.php` (só `configureRateLimiting()`), `app/Services/AuthenticationRateLimiter.php`, `tests/Unit/Services/AuthenticationRateLimiterTest.php`
      Mudança: limitadores literais `register` (`Limit::perMinutes(10, 3)`), `register-ip` (`Limit::perHour(10)`) e `invite-ip` (`Limit::perMinute(20)`); os 4 existentes ficam idênticos.
      No serviço: as constantes e as chaves `register:<sha256(e-mail normalizado)>:<ip>`, `register-ip:<ip>` e `invite-ip:<ip>` (só o IP, nunca token nem hash).
      Métodos novos: `tooManyRegistrationAttempts`, `hitRegistration` (que incrementa as duas chaves), `tooManyInviteLookups` e `hitInviteLookup`. O par de convite é consumido pelo **POST de lookup** do Livewire em T21, nunca por middleware de GET (o GET do convite não carrega token).
      Cobre: RF-19, RF-19b
      Acceptance criteria: nenhuma chave contém senha, e-mail em claro nem valor de token. Após 3 hits, a chave e-mail+IP acusa excesso. Após 10 hits com e-mails variados, a chave por IP acusa excesso. Após 20 `hitInviteLookup`, `tooManyInviteLookups` acusa excesso. As asserções existentes de login e recuperação passam sem edição.
      Testes: `AuthenticationRateLimiterTest` (acréscimos).

- [ ] T18 — `RegisterObraUserAction` (núcleo de criação de conta)
      Arquivos: `app/Actions/Usuarios/RegisterObraUserAction.php` (novo), `tests/Feature/Actions/Usuarios/RegisterObraUserActionTest.php` (novo)
      Mudança: métodos `validate(array $data)`, `createInsideTransaction($validated, AccountOrigin, ?ObraInvitation, ?string $ip)` e `execute(array $data, ?string $ip)`.
      `validate` lê apenas name, email, password e password_confirmation via `Arr::only`, e normaliza o e-mail com `EmailNormalizer` antes de validar. Regras:
      • nome até 255;
      • e-mail válido, até 255 e `unique:users,email`, com a mensagem exata "Já existe uma conta com este e-mail. Entre ou use Esqueci minha senha.";
      • senha com `confirmed` e `PasswordRule::defaults()`.
      A criação busca o papel obra pelo slug, grava `is_active = true` e `is_demo = false`, não cria nenhuma associação e grava `AccountRegistrationEvent` (origem, convite e IP) na mesma transação.
      `execute` captura `UniqueConstraintViolationException` fora da closure e devolve 422 em `email` com o mesmo texto. A Action não faz login.
      Cobre: RF-16, RF-17, RF-18, RF-20, RF-22, CT-07
      Acceptance criteria:
      • cadastro válido cria 1 usuário com papel obra, 0 associações, ativo, não demo e senha com hash;
      • "  Ana@X.com " é gravado como "ana@x.com", e "ANA@x.com" depois dá 422 com o texto exato sem criar usuário;
      • `role_id` gestao, `obra_ids`, `is_active = false` ou `is_demo` forjados são ignorados;
      • a corrida no índice termina com exatamente 1 usuário e `ValidationException`;
      • 1 cadastro gera exatamente 1 `account_registration_events` com origem `novo_cadastro` e sem senha;
      • falha forçada na auditoria desfaz o usuário.
      Testes: `RegisterObraUserActionTest`.

- [ ] T19 — Tela "Novo Cadastro" e botão no login
      Arquivos: `app/Livewire/Auth/Register.php`, `resources/views/livewire/auth/register.blade.php` (novos), `resources/views/livewire/auth/login-form.blade.php`, `routes/web.php`, `app/Livewire/Obra/Acompanhamento.php` (só o aviso de sessão), `resources/views/livewire/obra/acompanhamento.blade.php`, `tests/Feature/Livewire/RegisterTest.php`, `tests/Feature/Auth/ZeroObraUserTest.php` (novos)
      Mudança: `GET /cadastro` (`register`) no grupo `guest`, com layout `auth.login` e exatamente 4 propriedades e 4 inputs com label, sem papel nem obra.
      `register()` segue o padrão do `LoginForm`:
      • normaliza o e-mail e checa o limitador (excesso dá 422 com `LoginForm::THROTTLED_MESSAGE`);
      • chama `hitRegistration` em toda submissão e depois a Action de T18 com `request()->ip()`.
      Em caso de sucesso:
      • `Auth::login`, `loginSucceeded`, `Session::regenerate` e `session()->put('obra.registration_notice', true)`;
      • redirect para `home`;
      • `Acompanhamento::mount()` faz `pull` da chave e exibe "Conta criada. O acesso às obras depende de associação feita pela Gestão ou por Suprimentos.".
      O login ganha o link-botão "Novo Cadastro" (`btn-secondary`) abaixo de "Entrar".
      Cobre: UI-01, UI-02, CT-04, RF-14, RF-16, RF-17, RF-19, RF-20, RF-21
      Acceptance criteria:
      • `/login` mostra "Novo Cadastro" apontando para `/cadastro`, e o DOM do cadastro tem exatamente 4 inputs, sem select nem radio;
      • após o cadastro:
        ◦ o usuário está autenticado e o id de sessão mudou;
        ◦ existe exatamente 1 `login_success` do usuário;
        ◦ o redirect vai para `/home`;
        ◦ o aviso aparece em `/obra/pedidos`;
      • 4ª submissão do mesmo e-mail+IP em 10 min é recusada sem usuário e aceita após a janela, e a 11ª do mesmo IP em 1 h é recusada;
      • usuário autenticado não acessa `/cadastro`;
      • usuário obra com 0 obras:
        ◦ faz login;
        ◦ recebe 200 em `/obra/pedidos` com 0 linhas;
        ◦ vê o estado vazio da Nova Solicitação, verificado só por `GET route('obra.nova-solicitacao')` com `assertOk()` e `assertSee` do texto UI-09 (N-04); `ZeroObraUserTest` nunca referencia `App\Livewire\Obra\NovaSolicitacao` (sem `use`, `::class` nem `Livewire::test`), pois a fatia 2 remove essa classe;
        ◦ recebe 403 em qualquer pedido e 422 em `obra_id` na criação;
      • `LoginScreenIdentityTest` e `LoginFormTest` passam sem edição.
      Testes: `RegisterTest` e `ZeroObraUserTest`.

## Phase 7: Aceite do convite

Antes de implementar, leia:
1. `.spec/features/obras-associacoes-cadastro-convites/SPEC.md` — requisitos RIGID que esta fase cobre (RF-17, RF-19, RF-19b, RF-21, RF-27..RF-34, RF-38, UI-07, CT-05, RNF-01, RNF-02, RNF-08)
2. `.spec/features/obras-associacoes-cadastro-convites/PLAN.md` — decomposição completa, dependências e riscos

A atomicidade depende de uma ordem fixa: o UPDATE condicional de consumo é a **primeira** instrução da transação, e 0 linhas afetadas lançam exceção e fazem rollback.

Sigilo do token (RF-38/NC-08, decisão FINAL, não alterável nesta fase): o token viaja só no fragmento `/convite#<token>`; a rota `/convite` não tem parâmetro; um script inline lê `location.hash`, limpa com `history.replaceState` e envia o token no corpo do POST do Livewire (`lookup`); depois do lookup só o id do convite (`#[Locked]`) é mantido, inclusive na sessão. Nunca token em path, query, `#[Url]`, snapshot, sessão, log, auditoria ou exceção; todo parâmetro que recebe o token usa `#[\SensitiveParameter]`.

- [ ] T20 — `AcceptObraInvitationAction` e páginas fixas do convite (indisponível / limite)
      Arquivos: `app/Actions/Obras/AcceptObraInvitationAction.php` (novo), `app/Exceptions/ObraInvitations/ObraInvitationUnavailableException.php` (novo), `resources/views/obra-invitations/unavailable.blade.php`, `resources/views/obra-invitations/throttled.blade.php` (novos), `routes/web.php` (2 rotas fixas sem token), `tests/Feature/Actions/Obras/AcceptObraInvitationActionTest.php`, `tests/Feature/ObraInvitations/ObraInvitationOutcomePagesTest.php` (novos)
      Mudança: depois da resolução, a Action só trabalha com o **id** do convite.
      • `resolveByToken(#[\SensitiveParameter] mixed $token)`: não-string, vazio, fora do formato de 64 hex minúsculos, hash desconhecido ou convite não consumível lançam a **mesma** `ObraInvitationUnavailableException`, com mensagem fixa e sem contexto (sem token, hash, id nem obra);
      • `resolveById(int $invitationId)`: mesma exceção para id desconhecido ou não consumível (retorno pós-login);
      • a exceção estende `RuntimeException` no padrão de `PedidoTerminalStateException`: `render()` de fallback devolve a view `unavailable` com 404, e `report()` devolve `true` (nunca vai para o log);
      • rotas fixas, sem parâmetro e fora de `guest`/`auth`, como closures: `GET /convite/indisponivel` (`obra-invitation.unavailable`, view com 404) e `GET /convite/limite` (`obra-invitation.throttled`, view com 429 e `LoginForm::THROTTLED_MESSAGE`);
      • a view `unavailable` usa o shell de auth e traz exatamente "Este convite é inválido, expirou ou já foi utilizado. Peça um novo convite à Gestão ou a Suprimentos." e um link para o login, sem nome da obra, criador, e-mail nem formulário.
      `acceptAsNewAccount(int $invitationId, array $data, ?string $ip)`:
      • valida antes de escrever;
      • na transação, o UPDATE condicional `whereKey($invitationId)->consumable()` vem primeiro, e 0 linhas dão a exceção;
      • depois cria a conta via `createInsideTransaction` (origem convite) e grava `used_by`;
      • associa **a obra do convite** e grava `obra_access_changed` e `invitation_used`, com o novo usuário como ator.
      `acceptAsExistingAccount(User $user, int $invitationId)`:
      • papel diferente de obra dá 422 "Convites de obra só se aplicam a contas do perfil Obra." antes de consumir;
      • consumo condicional por id e associação só se ainda não existir (`associated = false` caso contrário);
      • grava `invitation_used`.
      Cobre: RF-27, RF-28, RF-29, RF-30, RF-31, RF-32, RF-33, RF-34, RF-19b, RF-38, RNF-02, RNF-08, CT-05, CT-07
      Acceptance criteria:
      • sucesso cria 1 usuário obra, exatamente 1 `obra_profile` da obra do convite e marca o convite como usado por ele; `obra_id` forjado é ignorado;
      • senha fraca ou e-mail duplicado não cria usuário nem associação, e o convite continua pendente;
      • conta obra existente ganha +1 associação e o convite usado; já associada ganha +0 com aviso;
      • Suprimentos recebe o erro com papel inalterado, 0 associações e convite pendente;
      • obra passada a Concluída dá a exceção sem consumo, e voltar a Em andamento antes de `expires_at` revalida o convite;
      • consumo desfeito deixa 0 registros nas 3 trilhas;
      • as 6 causas inválidas, mais vazio e não-string, lançam a mesma exceção com a mesma mensagem, e `(string) $e` nunca contém o token;
      • `GET /convite/indisponivel` dá 404 com o texto genérico e sem nome de obra; `GET /convite/limite` dá 429 com a mensagem PT-BR; nenhuma das duas rotas tem parâmetro.
      Testes: `AcceptObraInvitationActionTest` e `ObraInvitationOutcomePagesTest`.

- [ ] T21 — Página do convite sem token na rota, lookup por POST e retorno pós-login
      Arquivos: `app/Livewire/Auth/ObraInvitationPage.php`, `resources/views/livewire/auth/obra-invitation-page.blade.php` (novos), `routes/web.php`, `app/Livewire/Auth/LoginForm.php`, `tests/Feature/Livewire/ObraInvitationPageTest.php`, `tests/Feature/ObraInvitations/ObraInvitationTokenTransportTest.php` (novos)
      Mudança:
      • rota `GET /convite` (`obra-invitation.show`) **sem parâmetro de rota nem de query**, com `->middleware('active')`, fora dos grupos `guest` e `auth`;
      • propriedades: `#[Locked] ?int $invitationId`, `#[Locked] ?string $obraName`, campos de cadastro e aviso; **nenhum `#[Url]` e nenhuma propriedade com o token**; `RETURN_SESSION_KEY = 'obra_invitation.return_id'`;
      • `mount()` sem token: usuário autenticado com id inteiro na sessão (`pull`) retoma o convite por `resolveById`, e inválido redireciona para `obra-invitation.unavailable`; senão, estado pendente;
      • estado pendente renderiza "Verificando convite…" e um `@script` inline: lê `location.hash.slice(1)`, chama `history.replaceState(null, '', location.pathname)` e depois `$wire.lookup(token)`; os demais estados não renderizam o script;
      • `lookup(#[\SensitiveParameter] mixed $token)`: no-op se já resolvido; antes de qualquer hash ou consulta, `tooManyInviteLookups` (excesso redireciona para `obra-invitation.throttled`) e `hitInviteLookup` (uma vez por chamada); depois `resolveByToken`; exceção redireciona para `obra-invitation.unavailable` (mesmo efeito para as 6 causas e fragmento vazio); sucesso guarda só id e nome da obra.
      A página tem 4 estados renderizados:
      • visitante: formulário Nome/E-mail/Senha/Confirmação mais "Já tenho conta";
      • usuário obra: "Aceitar convite como <nome>" com botão de confirmação e "Sair";
      • Gestão/Suprimentos: mensagem de RF-31;
      • convite inválido: redirect para a página fixa 404.
      Métodos:
      • `register()`: exige visitante (senão 403) e id resolvido, usa os limitadores `register`/`register-ip` como T19, chama `acceptAsNewAccount($invitationId, …)`; exceção de indisponível redireciona para a página 404; sucesso faz login, grava `login_success`, regenera a sessão e redireciona para `home`; e-mail duplicado mostra a mensagem com a ação "Já tenho conta";
      • `useExistingAccount()`: guarda **só o id inteiro** em `RETURN_SESSION_KEY` (nunca o token) e redireciona ao login;
      • `confirm()`: chama `acceptAsExistingAccount($user, $invitationId)` e mostra o aviso.
      `LoginForm`, depois de `Session::regenerate()`: se `RETURN_SESSION_KEY` tiver um inteiro, redireciona para `route('obra-invitation.show')` (URL sem token); senão mantém exatamente o redirect atual para `home`.
      Cobre: CT-05, UI-07, RF-19, RF-19b, RF-21, RF-28, RF-29, RF-30, RF-31, RF-38, RNF-08
      Acceptance criteria:
      • `route('obra-invitation.show').'#'.$token` é igual à URL devolvida por T11;
      • cada um dos 4 estados renderiza só os seus controles;
      • as 6 causas inválidas e o fragmento vazio produzem o mesmo efeito de `lookup`: redirect para `obra-invitation.unavailable`, com `invitationId` e `obraName` nulos;
      • o 21º `lookup` do mesmo IP em 1 min redireciona para `obra-invitation.throttled`, com token válido e inválido, sem nenhuma consulta a `obra_invitations` nessa chamada; após a janela volta a funcionar;
      • fluxo RF-30: "Já tenho conta" leva ao login, que volta a `route('obra-invitation.show')` sem token; a página retoma o mesmo convite pelo id da sessão; confirmar gera +1 associação e o convite usado, e sem confirmar nada muda;
      • Suprimentos vê o erro, e o convite continua pendente; `register` forjado por usuário autenticado dá 403; `set('invitationId', …)` forjado é recusado por `#[Locked]`;
      • `route:list`: as rotas `/convite*` não têm parâmetro; nenhuma URI (path + query) nem `Location` de redirect dos fluxos gerar → abrir → cadastrar e gerar → abrir → login → retorno → confirmar contém o token;
      • após o `lookup`, o HTML e o snapshot do componente não contêm o token, e o componente não declara `#[Url]`;
      • após "Já tenho conta", `serialize(session()->all())` não contém o token e o valor guardado é o id inteiro;
      • o middleware da rota contém `active` e não contém `guest` nem `auth`; `LoginTest` e `LoginFormTest` passam sem edição.
      Testes: `ObraInvitationPageTest` e `ObraInvitationTokenTransportTest`.

- [ ] T22 — Testes adversariais e de concorrência do convite e das Actions novas
      Arquivos: `tests/Feature/Security/Adversarial/ObraInvitationConcurrencyTest.php`, `tests/Feature/Security/Adversarial/ObrasAuthorizationTest.php` (novos), `tests/Feature/Authorization/BypassUiAuthorizationTest.php` (só acréscimos)
      Mudança: somente testes.
      Concorrência (simulação no mesmo processo, decidida pelo desenvolvedor; sem nova suíte nem mudança em `phpunit.xml`):
      • 10 instâncias obsoletas do mesmo convite, resolvidas antes do primeiro consumo, e 10 tentativas sequenciais por id de cada caminho, nova conta e conta existente;
      • revogação competindo com consumo, nas duas ordens.
      Autorização: as 6 Actions novas chamadas diretamente com ator obra, e cada método Livewire mutante novo chamado por usuário obra via `/livewire/update`. Também payload forjado com `role_id`/`obra_ids` em `Register` e `ObraInvitationPage`, e atualização forjada de `invitationId`/`obraName` recusada por `#[Locked]`.
      Cobre: RF-07, RF-17, RF-32, RNF-02
      Acceptance criteria:
      • concorrência de consumo: exatamente 1 sucesso, 9 `ObraInvitationUnavailableException`, exatamente 1 associação nova, no máximo 1 usuário novo e 0 auditorias órfãs;
      • revogação × consumo: exatamente um vence;
      • ator obra recebe `AuthorizationException` com 0 linhas, e as chamadas Livewire forjadas recebem 403;
      • campos forjados nunca são aplicados, e `invitationId`/`obraName` forjados são recusados.
      Testes: os arquivos listados acima.

## Phase 8: Navegação, não-regressão e gates

Antes de implementar, leia:
1. `.spec/features/obras-associacoes-cadastro-convites/SPEC.md` — requisitos RIGID que esta fase cobre (UI-08, RF-03, RF-06, RF-36, RF-37, RF-38, RNF-01, RNF-03, RNF-04, RNF-05, RNF-08)
2. `.spec/features/obras-associacoes-cadastro-convites/PLAN.md` — decomposição completa, dependências e riscos

Esta fase não toca `docs/agents/*.md` nem `CLAUDE.md`: a documentação (T27) é a Phase 9, depois de T28 (F-05).

- [ ] T23 — Entradas "Obras" e "Associações" na toolbar
      Arquivos: `resources/views/layouts/app.blade.php`, `tests/Feature/Livewire/LayoutIdentityTest.php`, `tests/Feature/Livewire/UsuariosIndexTest.php`
      Mudança: acrescentar `Obras` (`obras.index`, ativo em `obras.*`) e `Associações` (`associacoes.index`, ativo em `associacoes.*`):
      • em Suprimentos, depois de "Todos os Pedidos";
      • em Gestão, depois de "Usuários", que continua sendo o 4º item.
      O braço obra fica intocado. Os dois testes que fixavam 4 itens na Gestão passam para a regra de UI-08 (6 itens) e são atualizados, não apagados.
      Navegação provisória (UI-08 v1.3, F-10): as duas entradas serão removidas pela sidebar da fatia 3 (RF-08), que reescreve esses testes; um comentário Blade `{{-- … --}}` acima das entradas registra isso. Nada além das duas entradas.
      Cobre: UI-08
      Acceptance criteria: a navegação da Gestão é Dashboard, Kanban, Todos os Pedidos, Usuários, Obras, Associações. A de Suprimentos é Visão Geral, Kanban, Todos os Pedidos, Obras, Associações. A de Obra fica idêntica à atual. "Obras" só fica ativo em `obras.*`. O layout contém o comentário Blade que marca as entradas como provisórias até a fatia 3 (RF-08).
      Testes: `LayoutIdentityTest` e `UsuariosIndexTest`.

- [ ] T24 — Orçamento de consultas das três listagens novas
      Arquivos: `tests/Feature/Performance/QueryCountTest.php` (só acréscimos)
      Mudança: três casos com `measureQueryCount`:
      • `Obras\Index` com 5 e com 15 obras;
      • `Associacoes\Index` com 5 e com 15 usuários, cada um com 3 obras;
      • lista de convites de `Obras\Form` com 5 e com 30 convites em estados mistos.
      Se a contagem crescer, corrigir o eager load no componente, nunca afrouxar o teste.
      Cobre: RNF-04
      Acceptance criteria: nos três casos, a contagem de consultas com a massa pequena é igual à da massa grande.
      Testes: os três acréscimos.

- [ ] T25 — Responsividade e fluxo de convite em navegador
      Arquivos: `tests/Browser/ResponsiveIdentityTest.php` (só acréscimos), `tests/Browser/ObraInvitationFlowTest.php` (novo)
      Mudança: estender a auditoria de viewport (1440×900, 820×1180, 390×844) às telas:
      • `/login`, `/cadastro` e o convite válido aberto como visitante por `/convite#<token>` (depois do lookup);
      • `/obras`, `/obras/nova`, `/obras/{obra}/editar` e `/associacoes`.
      Fluxo de navegador (RF-38 AC-b e RF-30):
      • abrir a URL absoluta `<APP_URL>/convite#<token>` como visitante, esperar o estado válido e verificar `window.location.hash === ''` e que `window.location.href` não contém o token;
      • clicar "Já tenho conta", entrar como usuário obra existente, voltar à página `/convite` sem token com a mesma obra, confirmar e ver o aviso;
      • abrir `/convite#<lixo>` termina em `/convite/indisponivel` com o texto genérico.
      Cobre: RNF-03, RF-30, RF-38, UI-01, UI-07
      Acceptance criteria: nenhuma das 7 telas tem overflow horizontal. O controle primário fica dentro do viewport. Todos os inputs têm label, e o anel de foco é visível nos 3 viewports. Depois de abrir o link, o fragmento some da barra de endereço e a URL não contém o token. O fluxo RF-30 termina na página `/convite` sem token com o aviso de sucesso. Token inválido termina em `/convite/indisponivel`.
      Testes: os acréscimos e `ObraInvitationFlowTest`. Exigem `npm run build` e Chromium.

- [ ] T26 — Varreduras de conformidade: "obra ativa" única, sem exclusão de obra, sigilo do token
      Arquivos: `tests/Feature/Compliance/ObraActivityDefinitionTest.php`, `tests/Feature/Compliance/ObraInvitationTokenLeakTest.php` (novos)
      Mudança: somente testes, com cinco varreduras:
      (a) nenhuma referência a `is_active` em contexto de obra em `app/` e `database/` (fora das migrations anteriores a T01), e uma única decisão de atividade baseada em `ObraStatus::Concluido`;
      (b) nenhuma rota, método Livewire ou Action exclui obra, exceto `ResetDemoData`;
      (c) com `Log::spy()` e `Exceptions::fake()`, os ciclos gerar → lookup → aceitar nos dois caminhos (nova conta; conta existente via login e retorno) e um lookup inválido: o token puro não aparece em nenhuma chamada de log, em nenhuma exceção reportada, nas 5 tabelas de convites e auditoria, na sessão serializada nem na tela da obra recarregada;
      (d) varredura estática: nenhuma rota `/convite*` com parâmetro, nenhum `url('convite/'` nem `route('obra-invitation.show', …)` com argumento em `app/`, nenhum `#[Url]` em `ObraInvitationPage`, e todo parâmetro `$token` em `app/Actions/Obras/`, `ObraInvitation` e `ObraInvitationPage` com `#[\SensitiveParameter]`;
      (e) a página do convite nunca exibe o nome nem o e-mail do criador.
      Cobre: RF-03, RF-06, RF-36, RF-38, RNF-01, RNF-08
      Acceptance criteria: as cinco varreduras passam, e `BrandIdentityComplianceTest` continua verde (sem `password` renderizado).
      Testes: os dois arquivos novos.

- [ ] T28 — Gates finais: Pint, build, suíte completa e dependências
      Arquivos: nenhum (somente verificação)
      Mudança: nesta ordem:
      • `vendor/bin/pint --dirty --format agent`;
      • `npm run build`;
      • suítes Unit e Feature (`php artisan test --compact --testsuite=Feature` e Unit);
      • `vendor/bin/pest tests/Browser`, separadamente e com um processo Pest por vez.
      `git diff --stat composer.json composer.lock package.json package-lock.json` deve estar vazio. Revisar com `git diff` que só as asserções previstas em T03, T04, T13 e T23 foram reescritas.
      Cobre: RNF-05, RF-37
      Acceptance criteria: 0 falhas em Unit, Feature e Browser (único ponto da fatia 1 em que `tests/Browser` é garantido, N-03), Pint limpo e build com saída 0. `grep -n 'NovaSolicitacao' tests/Feature/Auth/ZeroObraUserTest.php` não retorna nada (N-04). Nenhuma dependência nova. `tests/Feature/{Auth,Authorization,Security,Compliance}` verdes. `PedidoVisibleToScopeTest` alterado apenas na chamada de factory.
      Testes: a própria execução dos gates.

## Phase 9: Documentação (commit somente de documentação)

Antes de implementar, leia:
1. `.spec/features/obras-associacoes-cadastro-convites/SPEC.md` — requisitos RIGID que esta fase cobre (RNF-07, RF-37)
2. `.spec/features/obras-associacoes-cadastro-convites/PLAN.md` — decomposição completa, dependências e riscos

Pré-condição: Phase 8 concluída e verde (T28). Esta fase não altera código nem testes; o commit resultante é somente de documentação (F-05). A invocação de `/ai-context` por uma sessão headless do Ralph **não está verificada**: se não for possível, a regeneração é passo do desenvolvedor após o Ralph, em commit somente de documentação.

- [ ] T27 — Documentação: `/ai-context` e `CLAUDE.md`
      Arquivos: `docs/agents/*.md` (regenerados), `CLAUDE.md` (edição manual), `README.md` (só o runbook, se citar `obras.is_active`)
      Mudança: as mudanças anteriores de `/ai-context` em `docs/agents/*.md` já foram commitadas pelo gate de pré-execução (commit só de documentação); antes de regenerar, `git status --short docs/agents` deve estar vazio. `/ai-context` deve registrar:
      • o gate `manage-obras` e as 6 rotas novas;
      • as 3 tabelas novas e `obras.status`;
      • os 3 limitadores e as Actions novas;
      • a rota `/convite` sem token (fragmento, lookup por POST do Livewire, `invite-ip` no lookup) e as 2 rotas fixas de desfecho.
      Esses arquivos nunca são editados à mão. Em `CLAUDE.md`, editar à mão:
      • §4: "Cadastro de obras" passa a ✅, e entram as associações 0..N, o Novo Cadastro e os convites de obra;
      • §5: `manage-obras`, a rota do convite fora de `guest`/`auth` e a regra de que o token só viaja no fragmento (RF-38);
      • §6: as tabelas novas e o índice `obras_name_normalized_unique`;
      • §7: corrigir a linha desatualizada de rate limiting.
      Não criar `AI_CONTEXT.md`.
      Execução headless não verificada (F-05): se esta sessão do Ralph não conseguir invocar `/ai-context`, **não** editar `docs/agents/*.md` à mão; fazer só a edição de `CLAUDE.md`, deixar este checkbox desmarcado e reportar a regeneração como passo do desenvolvedor (rodar `/ai-context` interativamente e commitar só documentação).
      Cobre: RNF-07, RF-37
      Acceptance criteria: o diff do commit desta fase contém somente `docs/agents/*.md`, `CLAUDE.md` e, opcionalmente, `README.md` (nada em `app/`, `database/`, `routes/`, `resources/`, `tests/`, `.spec/`, `composer.*`, `package*.json`). Toda `tests/Feature/Compliance` passa após as edições (N-05), incluindo `DocumentationParityTest` (banner presente em `docs/agents/*`, ausente em `CLAUDE.md`, nenhum `AI_CONTEXT.md`), `EnvExampleTest` e `NoCommittedSecretsTest` (leem `README.md`). `CLAUDE.md` não afirma mais que o cadastro de obras está ausente nem que não há rate limiting.
      Testes: `php artisan test --compact tests/Feature/Compliance` (toda a pasta, reexecutada após as edições, pois T28 rodou antes — N-05). O commit da fase lista apenas `docs/agents/*.md`, `CLAUDE.md` e, se preciso, `README.md`.
