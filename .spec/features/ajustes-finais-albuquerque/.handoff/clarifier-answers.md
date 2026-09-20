# Clarifier answers — ajustes-finais-albuquerque

Answered by the developer on 2026-09-20. Apply each to the SPEC in-place; remove the corresponding [NEEDS CLARIFICATION] marker.

## Q-01 — Bootstrap do primeiro Gestão real em produção
Decision: **Artisan command idempotente** (ex. `users:create-gestao`) que cria/atualiza um usuário Gestão real.
- Conta do proprietário do sistema (o desenvolvedor): nome livre, e-mail `leo.olivbernardo@gmail.com`, perfil `gestao`, `is_active=true`, `is_demo=false`.
- Senha inicial: **simples, definida pelo desenvolvedor no momento da execução** (via opção `--password` ou env var lida só em runtime), informada a ele na sessão de execução; ele a trocará depois (via "Esqueci minha senha" ou fluxo de reset). A senha NUNCA é commitada no Git (§40) nem hardcoded; o comando não deve ter senha default no código.
- O desenvolvedor criará depois o usuário Gestão do cliente (Albuquerque) pela área Usuários.
- Contas demo (`*.demo@example.com`, `is_demo=true`): desativar/remover em produção na Etapa 10 (runbook), após o Gestão real existir. (Default assumido; não contradito pelo desenvolvedor.)
- Adicionar teste do comando (idempotência, não duplica, não sobrescreve senha de usuário já existente sem flag explícita — implementer's choice on flag name).

## Q-02 — Token de convite (primeiro acesso)
Decision: **segundo broker `passwords.invites`** em `config/auth.php`, apontando para a MESMA tabela `password_reset_tokens`, `expire = 4320` (72 h), `throttle` como o padrão. Notification dedicada (ex. `App\Notifications\FirstAccessInvite`) com copy de convite; rotas `invite.*` (ou reuso da página de reset com copy de convite — FLEXIBLE). Sem migration, sem pacote. Token single-use + hash garantidos pelo framework.

## Q-03 — Transporte de e-mail em produção
Decision: **Resend**. Usar o transporte `resend` nativo do Laravel (`MAIL_MAILER=resend`, `RESEND_KEY` em `config/services.php` lido de env). Dependência Composer **aprovada pelo desenvolvedor**: SDK `resend/resend-php` (exigido pelo transporte nativo; planner deve confirmar versão compatível com Laravel 13 via search-docs/composer show antes de instalar). Credenciais (`RESEND_KEY`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`) somente em Railway Variables — ponto de intervenção humana IH-01 permanece para: criar conta Resend, verificar domínio remetente, gerar API key, preencher variáveis. Default commitado em `.env.example`: `MAIL_MAILER=log`; testes: `array`.

## Q-04 — Falha de envio ao criar usuário
Decision: **commit primeiro, enviar após commit, feedback honesto**. A Action cria o usuário em `DB::transaction`; o envio do convite ocorre após o commit (`afterCommit` / fora da transação). Se o envio lançar exceção: usuário permanece criado, exceção é logada, e a Gestão vê mensagem do tipo "Usuário criado, mas o convite não pôde ser enviado — use Reenviar convite". RF-14 (reenviar) é o caminho de recuperação. Cobrir com teste (TC-04/TC-16) usando `Mail::fake()`/`Notification::fake()` + exceção simulada.

## Q-05 — Throttle do broker vs anti-enumeração
Decision: **formulário público "Esqueci minha senha" SEMPRE renderiza a mesma confirmação genérica** independentemente do status do broker (`sent`, `user`, `throttled`); throttle continua aplicado server-side. No lado da Gestão (autenticado, "Reenviar convite"/"Enviar link de redefinição"), o estado `throttled` É exibido explicitamente (ex. "link já enviado há menos de 1 min").

## Q-06 — Sessão ativa ao desativar usuário
Decision: **middleware `EnsureUserIsActive`** aplicado ao grupo `auth` (alias em `bootstrap/app.php`): qualquer request de usuário autenticado com `is_active=false` faz logout, invalida sessão e redireciona para `/login` com mensagem. Não apagar linhas da tabela `sessions`. 1 teste de feature.

## Q-07 — Guarda de auto-lockout da Gestão
Decision: **ambas as guardas**: (a) recusar desativar ou alterar o perfil da PRÓPRIA conta (`$actor->is($target)`); (b) recusar qualquer alteração (desativação ou troca de perfil) que deixaria ZERO usuários `gestao` ativos. Implementar na Action/Policy (não só na UI). 2 testes.

## Q-08 — Sidebar
Decision: **manter navegação topbar-only**. UI-07 (§19) passa a ser N/A nesta entrega e deve ser registrada como tal na revisão visual (Etapa 7); §19 só se aplicaria se uma sidebar existisse. Item "Usuários" entra na topbar da Gestão (4 itens).

## Q-09a — Texto da marca na topbar autenticada e `<title>`
Decision: **manter `config('app.name')`** no layout/`<title>`/remetente; definir `APP_NAME="Albuquerque Engenharia"` nas Railway Variables (Etapa 10) e documentar no README. Sem hardcode. `.env.example` pode ser atualizado para o novo nome (não é secret).

## Q-09b — Assinatura "Tecnologia por MC Inteligência" + logo MC
Decision: **somente telas de autenticação** (login, esqueci-senha, reset de senha, convite/primeiro acesso). Não adicionar rodapé nas páginas autenticadas nem nos e-mails nesta entrega.

## Q-10 — Regras de borda em criar/editar usuário
Decision: aceitar os 3 defaults:
1. Usuário de perfil Obra exige **≥ 1 obra** associada (create e edit) — validação.
2. Ao trocar o perfil de Obra para Suprimentos/Gestão, **desvincular todas as obras** (`obra_profile`) na mesma transação.
3. Ao alterar o e-mail de um usuário, **apenas atualizar**; tokens pendentes do e-mail antigo expiram naturalmente (não enviar convite automático, não apagar tokens).
