# Phases: email-notificacoes-enxutas

Gerado por /plan a partir de PLAN.md — view executável para `./ralph.sh .spec/features/email-notificacoes-enxutas/PHASES.md`.

## Phase 1: Estado ignorado no esquema

Antes de implementar, leia:
1. `.spec/features/email-notificacoes-enxutas/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/email-notificacoes-enxutas/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T01 — Estado `ignorado`: enum + migration da check
      Arquivos: `app/Enums/InternalNotificationEmailStatus.php`, `database/migrations/<timestamp>_allow_ignorado_in_internal_notifications_email_status.php` (novo, `php artisan make:migration allow_ignorado_in_internal_notifications_email_status --no-interaction`), `tests/Feature/Migrations/InternalNotificationsTableTest.php`, `tests/Unit/Models/InternalNotificationImmutabilityTest.php`
      Mudança: adicionar `case Ignorado = 'ignorado'` ao enum; migration recria `internal_notifications_email_status_check` com `pendente, enviado, falhou, ignorado`; `down()` aborta com `RuntimeException` PT-BR antes de escrever se existir linha `ignorado`, senão restaura os 3 valores. Mailer não muda nesta task.
      Cobre: RF-03 (banco), CT-03
      Acceptance criteria: o banco aceita `email_status = 'ignorado'` e rejeita `lido` pela check `internal_notifications_email_status_check`; `down()` com linha `ignorado` lança sem alterar a check; `InternalNotificationEmailStatus::Ignorado` existe; suíte Feature+Unit verde.
      Testes: `tests/Feature/Migrations/InternalNotificationsTableTest.php` — aceita `ignorado`, rejeita valor fora dos 4, guarda do `down()`; `tests/Unit/Models/InternalNotificationImmutabilityTest.php` — `email_status = Ignorado` + `email_status_at` atualizáveis.

## Phase 2: Filtro do e-mail e documentação

Antes de implementar, leia:
1. `.spec/features/email-notificacoes-enxutas/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/email-notificacoes-enxutas/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T02 — Filtro de destinatários e tipos, `ignorado`, log por notificação no Mailer
      Arquivos: `config/mail.php`, `app/Services/InternalNotificationMailer.php`, `tests/Pest.php`, `tests/Feature/Notifications/InternalNotificationMailerTest.php`, `tests/Feature/Notifications/PedidoNotificationRecorderTest.php`, `tests/Feature/Notifications/NotificationGenerationTest.php`, `tests/Feature/Security/Adversarial/InternalNotificationMailFailureTest.php`
      Mudança: `config/mail.php` ganha `notification_email.recipients` (de `NOTIFICATION_EMAIL_RECIPIENTS`, default vazio) e `notification_email.events` (de `NOTIFICATION_EMAIL_EVENTS`, default `criacao_pedido,observacao,cancelamento,entrega`), cada um `explode` + trim + `array_filter`. Em `flush()`: montar os dois conjuntos uma vez (e-mails via `EmailNormalizer::normalize`); `shouldEmail()` privado = e-mail do recipient no conjunto E `event_type_slug` no conjunto; não elegível → `ignorado` + `email_status_at`, sem transporte nem `PedidoDetailRoute`; `Sleep` sob resend só entre envios efetivos; 1 log por notificação com contexto exato CT-04 (`internal_notification_id`, `pedido_event_id`, `event_type_slug`, `recipient_id`, `result` + `exception_class`/`reason` só em `falhou`), `info` para enviado/ignorado, `warning` para falhou. Sem `EventTypeSlug` no Mailer, sem `isNotifiable`/`classification`/`notifiableEventTypes`, sem `env()` fora de `config/`, sem tocar Recorder/Resolver/Actions. Helper `allowNotificationEmailsFor(User ...$users)` em `tests/Pest.php` (recipients = e-mails dados, events = os 10 slugs) chamado como setup nos testes existentes que esperam e-mail; única asserção alterada: contexto exato do log em "the failure log carries ids…" vira CT-04 (contagem filtrada por `warning`).
      Cobre: RF-01, RF-02, RF-03, RF-04, RF-05, CT-01, CT-02, CT-04, RNF-01, RNF-02, RNF-03, RNF-04, RNF-06
      Acceptance criteria: com recipients `" A@X.com , ,b@y.com"` só `a@x.com` recebe e `c@z.com` fica `ignorado` com `email_status_at`; recipients vazio → 0 e-mails; events padrão → só os 4 tipos enviam; events vazio → nada; slug desconhecido não causa erro; `entrega` por `UpdatePedidoStatusAction` e `MarkPedidoEntregueByObraAction` envia com o padrão; N notificações → N logs CT-04 sem PII com `result` igual ao `email_status`; 1 envio + 9 ignorados sob resend não chama `Sleep`; nenhuma linha de `internal_notifications` criada/removida pelo filtro; `NotifiableEventTypesDefinitionTest`, `PedidoNotificationSinglePointTest`, `EmailNormalizationGuardTest`, `QueryCountTest`, testes de convite/redefinição/sino/página verdes; `vendor/bin/pint --dirty --format agent` limpo; suíte Feature verde.
      Testes: `tests/Feature/Notifications/InternalNotificationMailerTest.php` — filtros de destinatário e tipo, `ignorado`, log CT-04, sleep só entre envios, parsing de `config/mail.php` (ausente vs vazio), `entrega` pelas duas Actions.
- [ ] T03 — Documentar as variáveis em `.env.example` e README
      Arquivos: `.env.example`, `README.md`, `tests/Feature/Compliance/EnvExampleTest.php`
      Mudança: `.env.example` declara `NOTIFICATION_EMAIL_RECIPIENTS=` (vazio) e `NOTIFICATION_EMAIL_EVENTS=criacao_pedido,observacao,cancelamento,entrega` junto ao bloco `MAIL_*`; README (variáveis de e-mail e procedimento de deploy) descreve as duas variáveis, semântica de ausente/vazio, estado `ignorado`, log por notificação e que precisam existir no Railway antes do build. Sem valores reais; manter as strings exigidas por `NoCommittedSecretsTest`/`EnvExampleTest`.
      Cobre: RNF-05, CT-01, CT-02
      Acceptance criteria: `.env.example` contém as duas linhas exatas acima; README cita `NOTIFICATION_EMAIL_RECIPIENTS`, `NOTIFICATION_EMAIL_EVENTS` e o aviso "antes do build"; `NoCommittedSecretsTest` e `EnvExampleTest` verdes.
      Testes: `tests/Feature/Compliance/EnvExampleTest.php` — `.env.example` e README documentam as duas variáveis.
