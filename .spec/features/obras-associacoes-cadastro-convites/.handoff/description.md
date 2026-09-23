# Fatia 1 de 3 — obras-associacoes-cadastro-convites

Origem: "PLANO COMPLETO — EVOLUÇÃO DO SISTEMA ALBUQUERQUE" (49 seções), fatiado pelo desenvolvedor em 3 features.
Esta fatia cobre §1–§9, §45–§46 e aplica §43/§44 como restrições. Fora desta fatia (NÃO especificar aqui):
- Fatia 2 `solicitacao-historico-finalizacao`: §10–15 (Nova Solicitação para Obra+Suprimentos, opção "Outra", 3 datas com +3 dias úteis, anexos), §19–21 (histórico padronizado, observações, Obra marca Entregue), §29–35 (romaneio, status Finalizado), §42, §47–48.
- Fatia 3 `navegacao-sidebar-listagens`: §16–18, §22–28 (colunas, homes = Pedidos, ordenação, filtros compactos, "Somente obras ativas"), §36–41 (sidebar).
Consequência: nesta fatia, o acesso às novas telas (Obras, associações, convites) entra na navegação ATUAL (toolbar); a sidebar é da fatia 3. A regra "obra Concluída não recebe novas solicitações" é desta fatia e deve valer para o fluxo de criação existente (hoje só perfil obra cria).

## Critérios de aceite confirmados (fonte da verdade)
1. Gestão e Suprimentos cadastram e editam obras com Nome, Responsável e Status ∈ {A iniciar, Em andamento, Concluído}; obra ativa = status ≠ Concluído; concluir não apaga pedidos, eventos nem associações.
2. Obra Concluída não aparece nem é aceita (backend) para novas solicitações; pedidos antigos continuam visíveis.
3. Gestão e Suprimentos localizam um usuário, veem suas obras e adicionam uma ou várias / removem associações; duplicidade é rejeitada no banco e na Action; remover associação não apaga pedidos nem histórico.
4. Usuários `obra` e `suprimentos` podem ter 0..N obras; `obra` sem obra autentica normalmente e não vê pedido algum; `obra_id` forjado não concede acesso (backend).
5. Tela de login tem botão "Novo Cadastro" → Nome, E-mail, Senha (+ confirmação); cria sempre perfil `obra`, zero obras; campos de papel/obra injetados são ignorados/rejeitados; e-mail normalizado, único case-insensitive, sujeito a rate limit.
6. Gestão/Suprimentos geram convite a partir de uma obra: token aleatório (armazenado com hash), validade 24h, uso único, revogável; 3 convites = 3 links distintos; registra obra, criador, criação, expiração, revogador/data, data de uso e usuário.
7. Convite válido + pessoa sem conta → cria conta `obra` associada à obra do convite; obra não é alterável; convite consumido atomicamente só após sucesso.
8. Convite válido + conta `obra` existente → exige login com essa conta, adiciona associação (sem duplicar) e consome; conta Gestão/Suprimentos não é convertida (erro claro).
9. Convite expirado/usado/revogado/inexistente mostra erro sem vazar dados; consumo concorrente → só um vence (lock/condição atômica).
10. Nenhuma regressão: RateLimit, AuthenticateSession, EnsureUserIsActive, EmailNormalizer + índice lower(email), `visibleTo`, policies, `pedido_events`/`user_admin_events`/`authentication_events` (associações, obras e convites auditados); sem limpeza destrutiva em migration.

## Texto original das seções desta fatia (verbatim, condensado em formatação)

### 1. Usuários e Obras
Alterar o relacionamento entre usuários e obras para permitir múltiplas obras por usuário. Um usuário poderá ter zero, uma ou várias obras associadas. Gestão e Suprimentos poderão associar e desassociar usuários de obras. Impedir associação duplicada do mesmo usuário com a mesma obra. Remover uma associação não deve apagar pedidos nem histórico já existentes. Usuários do perfil Obra só poderão operar sobre obras às quais possuem acesso, além do fluxo específico de Outra. Suprimentos também poderá possuir múltiplas obras associadas. As autorizações devem ser validadas no backend, não apenas pela interface.

### 2. Cadastro e Gerenciamento de Obras
Criar uma área de Obras acessível para: Gestão; Suprimentos. Permitir cadastrar e editar obras. Cada obra deverá possuir: Obra; Responsável; Status. Status disponíveis: A iniciar; Em andamento; Concluído. Considerar como obra ativa: qualquer obra cujo status seja diferente de Concluído. Ao marcar uma obra como Concluído: ela deixa de aparecer para novas solicitações; continua existindo; pedidos antigos permanecem acessíveis; histórico permanece disponível; nenhuma informação histórica deve ser apagada.

### 3. Gestão das Associações Usuário × Obra
Gestão e Suprimentos deverão possuir uma interface para administrar as obras de cada usuário. Deve ser possível: localizar um usuário; visualizar suas obras associadas; adicionar uma associação; adicionar várias obras ao mesmo usuário; remover associação; impedir duplicidade. Essa funcionalidade será também o mecanismo utilizado para vincular posteriormente usuários que fizeram Novo Cadastro público.

### 4. Novo Cadastro na Tela de Login
Adicionar na tela de login um segundo botão: Novo Cadastro. Esse é um cadastro público simplificado. Campos: Nome; E-mail; Senha; confirmação de senha, caso o padrão atual do sistema utilize confirmação. Não deverá existir nesse fluxo: campo Obra; Nome da Obra; seleção de obra; seleção de perfil; opção Gestão; opção Suprimentos. Todo cadastro realizado dessa forma será automaticamente: Perfil Obra. O usuário será criado: sem nenhuma obra associada. Depois, Gestão ou Suprimentos será responsável por associá-lo a uma ou mais obras. O backend também deve impedir que alguém manipule a requisição para se cadastrar como Gestão ou Suprimentos.

### 5. Usuário Obra sem Associação
Usuário Obra sem nenhuma obra associada é um estado válido. O sistema não deve considerar essa situação erro de cadastro. Esse usuário: pode autenticar; não recebe automaticamente acesso a nenhuma obra cadastrada; não pode forjar um ID de obra para obter acesso; poderá receber associações posteriormente por Gestão/Suprimentos; também poderá receber uma associação através de convite válido.

### 6. Convites de Obra
Gestão e Suprimentos poderão gerar um convite a partir de uma obra. O convite deverá estar vinculado internamente àquela obra. Cada convite: pertence a uma única obra; utiliza token seguro e não previsível; possui validade de 24 horas; é de uso único; pode ser revogado antes de ser utilizado; somente é consumido após cadastro/associação concluído com sucesso. Se três pessoas forem convidadas para a mesma obra: devem ser gerados três links diferentes.

### 7. Cadastro por Convite — Novo Usuário
Quando alguém sem conta abrir um convite válido: informa Nome; informa E-mail; informa Senha; o sistema cria a conta; perfil criado = Obra; o sistema associa automaticamente o usuário à obra do convite; o convite é marcado como utilizado. A pessoa não poderá escolher ou alterar a obra definida pelo convite.

### 8. Convite — Usuário Existente
Se a pessoa já possuir conta Obra: não criar conta duplicada; utilizar a conta existente; exigir autenticação/confirmação adequada; adicionar a nova associação; impedir associação duplicada; consumir o convite somente após sucesso. Isso permitirá que um usuário Obra adquira novas obras através de convites diferentes. Se a conta existente for Gestão ou Suprimentos, não converter silenciosamente seu perfil para Obra.

### 9. Segurança e Controle dos Convites
Tratar adequadamente: convite válido; convite expirado; convite utilizado; convite revogado; token inexistente/inválido; tentativa de reutilização; tentativas simultâneas de consumir o mesmo convite. Um convite não poderá ser utilizado novamente depois de consumido. O sistema deverá registrar adequadamente: obra; quem gerou; quando gerou; validade; quem revogou, quando aplicável; quando foi utilizado; usuário que utilizou.

### 41 (parcial, aplicável a esta fatia). Responsividade
Garantir funcionamento mobile de: login; Novo Cadastro; convite; gerenciamento de obras; associação de usuários.

### 42 (parcial). Preservação de Histórico
Não destruir histórico ao: alterar status da obra; concluir obra; associar usuário; desassociar usuário.

### 43. Dados de Demo
Os dados atuais são de teste/demo e podem ser apagados caso mudanças estruturais realmente exijam reset. Porém: não realizar exclusões silenciosas; não colocar limpeza destrutiva escondida em migration; qualquer reset deve ser explícito e controlado; preservar estrutura/configuração que não precise ser descartada.

### 44. Segurança Existente
As alterações não podem regredir: autorização server-side; isolamento de obras; usuário inativo bloqueado; restrição para novas solicitações em obras inativas/concluídas; histórico; rate limiting; AuthenticateSession; normalização de e-mail; unicidade case-insensitive; auditoria; pedido_events; user_admin_events; authentication_events; policies/middlewares/scopes existentes.

### 45. Fluxo Final — Novo Cadastro
Login → Novo Cadastro → Nome + E-mail + Senha → Conta criada como perfil Obra → Zero obras associadas → Gestão/Suprimentos localiza usuário → Associa uma ou mais obras → Usuário passa a operar nas obras autorizadas.

### 46. Fluxo Final — Cadastro por Convite
Gestão/Suprimentos → Obra → Gerar convite → Link único — validade 24h → Usuário acessa → Nome + E-mail + Senha → Conta perfil Obra → Associação automática à obra → Convite consumido.
Se a pessoa já possuir conta Obra: Convite → Autenticação/confirmação → Conta existente → Nova obra associada → Convite consumido.
