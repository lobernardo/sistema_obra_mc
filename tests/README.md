# Mapa de cobertura de testes (RF-23, brief §30)

Esta tabela mapeia cada um dos 18 temas do brief §30 aos arquivos de teste concretos
que os cobrem (T17–T47). A cobertura é cruzada com a saída completa de
`php artisan test` (T51): a suíte inteira deve passar com exit code 0.

| # | Tema (brief §30) | Arquivos de teste |
|---|---|---|
| 1 | Autenticação | `tests/Feature/Auth/LoginTest.php`, `tests/Feature/Livewire/LoginFormTest.php` |
| 2 | Usuário não autenticado | `tests/Feature/Auth/UnauthenticatedAccessTest.php` |
| 3 | Autorização | `tests/Feature/Authorization/RoleGatesTest.php`, `tests/Feature/Authorization/PedidoPolicyTest.php`, `tests/Feature/Authorization/BypassUiAuthorizationTest.php` |
| 4 | Isolamento por obra | `tests/Feature/Livewire/AcompanhamentoTest.php`, `tests/Feature/Authorization/PedidoPolicyTest.php` |
| 5 | Associação usuário/obra | `tests/Feature/ObraProfileCardinalityTest.php` |
| 6 | Criação de pedido | `tests/Feature/Actions/CreatePedidoActionTest.php`, `tests/Feature/Livewire/NovaSolicitacaoTest.php` |
| 7 | Validação de formulário | `tests/Feature/Livewire/NovaSolicitacaoTest.php`, `tests/Feature/Actions/CreatePedidoActionTest.php` |
| 8 | Workflow | `tests/Feature/Actions/UpdatePedidoStatusActionTest.php`, `tests/Feature/Livewire/KanbanBoardTest.php`, `tests/Feature/Livewire/AccessibleStatusControlTest.php` |
| 9 | Transições válidas/inválidas | `tests/Feature/Actions/UpdatePedidoStatusActionTest.php`, `tests/Feature/Livewire/KanbanForgedMoveTest.php` |
| 10 | Atribuição de responsável | `tests/Feature/Actions/UpdatePedidoResponsavelActionTest.php`, `tests/Feature/Rules/ResponsibleMustBeSuprimentosTest.php`, `tests/Feature/Livewire/PedidoDetalheSuprimentosTest.php` |
| 11 | Prioridade | `tests/Feature/Actions/UpdatePedidoPrioridadeActionTest.php`, `tests/Feature/Livewire/PedidoDetalheSuprimentosTest.php` |
| 12 | Previsão | `tests/Feature/Actions/UpdatePedidoPrevisaoActionTest.php`, `tests/Feature/Livewire/PedidoDetalheSuprimentosTest.php` |
| 13 | Cancelamento | `tests/Feature/Actions/CancelPedidoActionTest.php`, `tests/Feature/Livewire/CancelPedidoControlTest.php` |
| 14 | Cálculo de atraso | `tests/Unit/Domain/AtrasoClassifierTest.php`, `tests/Unit/Domain/PrazoClassifierTest.php`, `tests/Feature/Livewire/PedidoCardRenderTest.php`, `tests/Feature/Livewire/DashboardIndicatorsTest.php` |
| 15 | Histórico | `tests/Unit/Models/PedidoEventImmutabilityTest.php`, `tests/Feature/Livewire/PedidoDetalheObraTest.php`, `tests/Feature/Livewire/PedidoDetalheGestaoTest.php` |
| 16 | Dashboard | `tests/Feature/Livewire/DashboardIndicatorsTest.php`, `tests/Feature/Livewire/DashboardFiltersTest.php`, `tests/Feature/Livewire/DashboardDrillDownTest.php` |
| 17 | Permissões de Suprimentos | `tests/Feature/Livewire/TodosPedidosFiltersTest.php`, `tests/Feature/Livewire/KanbanBoardTest.php`, `tests/Feature/Livewire/PedidoDetalheSuprimentosTest.php`, `tests/Feature/Livewire/SuprimentosScreensRouteTest.php` |
| 18 | Acesso read-only da Gestão | `tests/Feature/Livewire/GestaoKanbanReadOnlyTest.php`, `tests/Feature/Livewire/PedidoDetalheGestaoTest.php` |

## Não-funcionais (RNF-07, RNF-08)

Não fazem parte dos 18 temas do brief §30, mas têm cobertura dedicada exigida por
RF-23/RNF-07/RNF-08:

| Requisito | Arquivos de teste |
|---|---|
| RNF-07 — paginação / ausência de N+1 | `tests/Feature/Performance/QueryCountTest.php` |
| RNF-08 — CSRF | `tests/Feature/Security/CsrfProtectionTest.php` |
| RNF-08 — proteção contra mass assignment | `tests/Feature/Security/MassAssignmentTest.php` |
| RNF-08 — escaping padrão do Blade | `tests/Feature/Security/BladeEscapingTest.php` |

## Suporte (infraestrutura, seeders, migrations)

Testes que sustentam os temas acima mas não mapeiam 1:1 a um item do brief §30:
`tests/Feature/BootstrapTest.php`, `tests/Feature/DatabaseConnectionTest.php`,
`tests/Feature/FreshMigrationTest.php`, `tests/Feature/MigrationSchemaTest.php`,
`tests/Feature/Console/ResetDemoDataTest.php`, `tests/Feature/Seeders/DemoSeederIdempotencyTest.php`,
`tests/Unit/Enums/SlugEnumsTest.php`, `tests/Unit/Models/LookupModelsTest.php`,
`tests/Unit/Models/PedidoModelTest.php`, `tests/Unit/Services/PedidoCodeGeneratorTest.php`,
`tests/Feature/Livewire/ObraScreensRouteTest.php`, `tests/Feature/Livewire/SuprimentosScreensRouteTest.php`.

## Solicitação, histórico e finalização

Cobertura da feature `solicitacao-historico-finalizacao` (Nova Solicitação para Obra e
Suprimentos, "Outra", anexos, Data prevista, calendário local, observações, Obra marca
Entregue, romaneio e Finalizado):

| Tema | Arquivos de teste |
|---|---|
| Criação por Obra e Suprimentos, "Outra", snapshot de `criacao_pedido` | `tests/Feature/Actions/CreatePedidoActionTest.php`, `tests/Feature/Livewire/NovaSolicitacaoTest.php` |
| Anexos na criação e limites de upload | `tests/Feature/Actions/CreatePedidoAttachmentsTest.php`, `tests/Feature/Livewire/NovaSolicitacaoAnexosTest.php`, `tests/Feature/Compliance/PedidoAttachmentsComplianceTest.php`, `tests/Feature/Compliance/UploadLimitsConsistencyTest.php`, `tests/Unit/Models/PedidoAttachmentImmutabilityTest.php` |
| Download de anexo | `tests/Feature/Http/PedidoAttachmentDownloadTest.php` |
| Data prevista e feriados nacionais | `tests/Unit/Domain/DataPrevistaCalculatorTest.php`, `tests/Unit/Domain/BrazilianNationalHolidaysTest.php`, `tests/Unit/Models/PedidoDataPrevistaHookTest.php`, `tests/Feature/Compliance/DataPrevistaSingleRuleTest.php`, `tests/Feature/Migrations/PedidoOutraDataPrevistaMigrationTest.php` |
| Calendário local (`LocalTime`, `RequestedPeriodFilter`) | `tests/Unit/Support/LocalTimeTest.php`, `tests/Unit/Domain/RequestedPeriodFilterTest.php`, `tests/Unit/Domain/LocalDayClassifiersTest.php`, `tests/Feature/Compliance/LocalTimeDisplayComplianceTest.php`, `tests/Feature/Compliance/RequestedPeriodSingleDefinitionTest.php` |
| Observações | `tests/Feature/Actions/AddPedidoObservacaoActionTest.php`, `tests/Feature/Livewire/PedidoObservacaoControlTest.php` |
| Obra marca Entregue | `tests/Feature/Actions/MarkPedidoEntregueByObraActionTest.php`, `tests/Feature/Livewire/ObraMarcarEntregueTest.php` |
| Romaneio e Finalizado | `tests/Feature/Actions/AttachRomaneioActionTest.php`, `tests/Feature/Actions/FinalizePedidoActionTest.php`, `tests/Feature/Livewire/RomaneioUploadControlTest.php`, `tests/Feature/Livewire/FinalizarPedidoControlTest.php`, `tests/Feature/Compliance/TerminalStatusDefinitionTest.php`, `tests/Feature/Migrations/HistoryLookupRowsMigrationTest.php` |
| Histórico e resumo | `tests/Feature/Livewire/PedidoHistoryPresentationTest.php`, `tests/Feature/Livewire/PedidoSummaryDatesAndAttachmentsTest.php`, `tests/Unit/Services/PedidoEventValuePresenterTest.php` |
| Fluxo de ponta a ponta (navegador) | `tests/Browser/SolicitacaoFinalizacaoFlowTest.php` |
| Paridade da documentação | `tests/Feature/Compliance/DocumentationParityTest.php` |

## Rodando a suíte

```
php artisan test
```

ou, diretamente pelo runner:

```
vendor/bin/pest
```

Ambos devem retornar exit code 0 (T51).
