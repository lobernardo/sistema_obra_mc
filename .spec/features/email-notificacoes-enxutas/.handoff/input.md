# Input confirmado — email-notificacoes-enxutas (v2, ESCOPO MÍNIMO)

Substitui integralmente a versão anterior. Tier: standard. Limite rígido do desenvolvedor: PLAN com no máximo **3 tarefas** e **2 fases**.

## Resumo
Restringir o e-mail das notificações internas de pedido (InternalNotificationMailer) a uma lista de destinatários e a uma lista de tipos de evento, ambas por env, marcando `ignorado` quem não recebe — sem mudar a notificação interna.

## Critérios de aceite

1. **Destinatários.** E-mail só para quem recebeu a notificação interna (resolvedor atual, sem mudança) **E** tem e-mail em `NOTIFICATION_EMAIL_RECIPIENTS` (lista por vírgula; comparação via `App\Support\EmailNormalizer` nos dois lados, ignorando itens vazios/espaços). Lista vazia ou ausente = ninguém recebe e-mail.
2. **Eventos.** Só enviam e-mail os tipos em `NOTIFICATION_EMAIL_EVENTS` (slugs por vírgula; padrão `criacao_pedido,observacao,cancelamento,entrega`). Sem regra condicional por status nem por papel do ator: marcar Entregue gera sempre `entrega`, que está no padrão.
3. **`ignorado`.** Notificação que não gera e-mail recebe `email_status = ignorado` (e `email_status_at`). Migration pequena recriando a check `internal_notifications_email_status_check`; enum `InternalNotificationEmailStatus` e testes de esquema/imutabilidade acompanham.
4. **Log.** Um log por notificação processada: `internal_notification_id`, `pedido_event_id`, `event_type_slug`, `recipient_id`, resultado (`enviado`/`falhou`/`ignorado`). Nunca endereço de e-mail nem conteúdo do evento (na falha, só a classe da exceção, como hoje).
5. **Intactos.** Convite de primeiro acesso, redefinição de senha e a notificação interna (linhas, sino, página, regra de destinatários) não mudam.
6. Ajustar testes Pest existentes e novos, `.env.example` e README (incluindo que as variáveis precisam existir no Railway antes do build, por causa do `config:cache`).

## FORA DE ESCOPO (não especificar, não planejar)
Contador diário, aviso de limite/80%, `MAIL_DAILY_LIMIT`, parada por cota, classificação de causa de falha (`daily_quota`/`rate_limit`), enum de causas.

## Restrições do projeto
- Envio continua via `defer()` (decisão travada); sem fila/worker/scheduler.
- `PedidoNotificationRecorder::record()` continua o único ponto que insere em `internal_notifications`.
- Sem dependências novas; PHP 8.4; Pint; Pest.
