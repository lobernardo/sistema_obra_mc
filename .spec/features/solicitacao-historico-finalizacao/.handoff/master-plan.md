# PLANO COMPLETO — EVOLUÇÃO DO SISTEMA ALBUQUERQUE (plano mestre, texto do desenvolvedor)

Fatiamento decidido pelo desenvolvedor (mesmo incremento funcional, 3 fatias executadas em sequência):
- Fatia 1 `obras-associacoes-cadastro-convites`: §1–9, §45–46 (+ §41/§42 parciais, §43, §44 como restrições)
- Fatia 2 `solicitacao-historico-finalizacao`: §10–15, §19–21, §29–35, §42, §47–48
- Fatia 3 `navegacao-sidebar-listagens`: §16–18, §22–28, §36–41
§43 e §44 valem como restrições para as três.

## 1. Usuários e Obras
Alterar o relacionamento entre usuários e obras para permitir múltiplas obras por usuário. Um usuário poderá ter zero, uma ou várias obras associadas. Gestão e Suprimentos poderão associar e desassociar usuários de obras. Impedir associação duplicada do mesmo usuário com a mesma obra. Remover uma associação não deve apagar pedidos nem histórico já existentes. Usuários do perfil Obra só poderão operar sobre obras às quais possuem acesso, além do fluxo específico de Outra. Suprimentos também poderá possuir múltiplas obras associadas. As autorizações devem ser validadas no backend, não apenas pela interface.

## 2. Cadastro e Gerenciamento de Obras
Criar uma área de Obras acessível para: Gestão; Suprimentos. Permitir cadastrar e editar obras. Cada obra deverá possuir: Obra; Responsável; Status. Status disponíveis: A iniciar; Em andamento; Concluído. Considerar como obra ativa: qualquer obra cujo status seja diferente de Concluído. Ao marcar uma obra como Concluído: ela deixa de aparecer para novas solicitações; continua existindo; pedidos antigos permanecem acessíveis; histórico permanece disponível; nenhuma informação histórica deve ser apagada.

## 3. Gestão das Associações Usuário × Obra
Gestão e Suprimentos deverão possuir uma interface para administrar as obras de cada usuário. Deve ser possível: localizar um usuário; visualizar suas obras associadas; adicionar uma associação; adicionar várias obras ao mesmo usuário; remover associação; impedir duplicidade. Essa funcionalidade será também o mecanismo utilizado para vincular posteriormente usuários que fizeram Novo Cadastro público.

## 4. Novo Cadastro na Tela de Login
Adicionar na tela de login um segundo botão: Novo Cadastro. Esse é um cadastro público simplificado. Campos: Nome; E-mail; Senha; confirmação de senha, caso o padrão atual do sistema utilize confirmação. Não deverá existir nesse fluxo: campo Obra; Nome da Obra; seleção de obra; seleção de perfil; opção Gestão; opção Suprimentos. Todo cadastro realizado dessa forma será automaticamente: Perfil Obra. O usuário será criado: sem nenhuma obra associada. Depois, Gestão ou Suprimentos será responsável por associá-lo a uma ou mais obras. O backend também deve impedir que alguém manipule a requisição para se cadastrar como Gestão ou Suprimentos.

## 5. Usuário Obra sem Associação
Usuário Obra sem nenhuma obra associada é um estado válido. O sistema não deve considerar essa situação erro de cadastro. Esse usuário: pode autenticar; não recebe automaticamente acesso a nenhuma obra cadastrada; não pode forjar um ID de obra para obter acesso; poderá receber associações posteriormente por Gestão/Suprimentos; também poderá receber uma associação através de convite válido.

## 6. Convites de Obra
Gestão e Suprimentos poderão gerar um convite a partir de uma obra. O convite deverá estar vinculado internamente àquela obra. Cada convite: pertence a uma única obra; utiliza token seguro e não previsível; possui validade de 24 horas; é de uso único; pode ser revogado antes de ser utilizado; somente é consumido após cadastro/associação concluído com sucesso. Se três pessoas forem convidadas para a mesma obra: devem ser gerados três links diferentes.

## 7. Cadastro por Convite — Novo Usuário
Quando alguém sem conta abrir um convite válido: informa Nome; informa E-mail; informa Senha; o sistema cria a conta; perfil criado = Obra; o sistema associa automaticamente o usuário à obra do convite; o convite é marcado como utilizado. A pessoa não poderá escolher ou alterar a obra definida pelo convite.

## 8. Convite — Usuário Existente
Se a pessoa já possuir conta Obra: não criar conta duplicada; utilizar a conta existente; exigir autenticação/confirmação adequada; adicionar a nova associação; impedir associação duplicada; consumir o convite somente após sucesso. Isso permitirá que um usuário Obra adquira novas obras através de convites diferentes. Se a conta existente for Gestão ou Suprimentos, não converter silenciosamente seu perfil para Obra.

## 9. Segurança e Controle dos Convites
Tratar adequadamente: convite válido; convite expirado; convite utilizado; convite revogado; token inexistente/inválido; tentativa de reutilização; tentativas simultâneas de consumir o mesmo convite. Um convite não poderá ser utilizado novamente depois de consumido. O sistema deverá registrar adequadamente: obra; quem gerou; quando gerou; validade; quem revogou, quando aplicável; quando foi utilizado; usuário que utilizou.

## 10. Nova Solicitação
A funcionalidade Nova Solicitação ficará disponível para: Obra; Suprimentos. Criar uma ação destacada: "+ Nova Solicitação". Ela deverá permanecer fácil de acessar durante a navegação para esses perfis. Gestão não recebe automaticamente essa permissão.

## 11. Seleção de Obra — Perfil Obra
Ao criar uma solicitação, o usuário Obra poderá selecionar entre suas obras elegíveis. Não apresentar obras concluídas para novas solicitações. O backend deve validar a associação. Alterar manualmente o ID da obra na requisição não pode permitir criar uma solicitação para obra não autorizada.

## 12. Seleção de Obra — Suprimentos
Suprimentos também poderá criar solicitações. Nesse fluxo, deverá visualizar: suas obras associadas que não estejam Concluído. A mesma regra deve existir no backend.

## 13. Opção "Outra"
No seletor de obra da Nova Solicitação, adicionar: Outra. Ao selecionar Outra, abrir um campo de texto livre. Esse campo será: opcional. A informação digitada será uma referência daquele pedido. Selecionar Outra: não cria uma Obra; não associa usuário a uma Obra; não concede acesso a uma obra existente; não deve ser interpretado como cadastro de obra. Se o usuário não preencher o texto, o pedido deverá continuar podendo ser representado simplesmente como Outra.

## 14. Datas da Solicitação
A solicitação deverá possuir três conceitos distintos: Data da solicitação — data em que o pedido foi registrado. Preciso para — data de necessidade informada pelo solicitante. Data prevista — calculada automaticamente. Regra: Data prevista = Data da solicitação + 3 dias úteis. Implementar três dias úteis, não 72 horas nem três dias corridos. A possibilidade futura de apresentar simplesmente "3 dias" em vez de uma data calculada deve ser considerada na estrutura, mas não deve ser implementada agora.

## 15. Anexos na Solicitação
Adicionar upload de: imagens; documentos. Os anexos deverão ficar relacionados ao pedido. Implementar validações adequadas para: tipos permitidos; tamanho; armazenamento; nome seguro; acesso; download; autorização; arquivos inválidos. Um usuário não autorizado a visualizar determinado pedido também não deverá conseguir acessar seus anexos por URL direta.

## 16. Identificação do Solicitante
Nas listagens de pedidos, adicionar informação clara sobre: quem solicitou; para qual obra/referência. Apresentação conceitual: "João Silva / Residencial Aurora". A informação deverá funcionar tanto na tabela desktop quanto nos cards mobile.

## 17. Acompanhamento — "Itens" para "Descrição"
Na área de Acompanhamento, alterar a nomenclatura: Itens → Descrição. Aplicar de forma consistente nas versões desktop e mobile aplicáveis.

## 18. Previsão no Acompanhamento
O campo/coluna: Previsão deverá apresentar a nova Data prevista calculada pela regra dos 3 dias úteis.

## 19. Histórico do Pedido
Padronizar a apresentação do histórico. Modelo: "Histórico / Pedido criado / Solicitação registrada para Residencial Aurora. / 16/09/2026 · João Silva". Cada evento deverá apresentar claramente: ação; descrição/contexto; data/hora; autor. Eventos anteriores não devem ser destruídos quando novos eventos forem registrados.

## 20. Observações no Histórico
Adicionar dentro do pedido um campo de observação livre. Disponível para: Obra; Suprimentos. Ao enviar uma observação, criar um novo evento no Histórico. Exemplo: "Observação adicionada / Material será recebido pelo encarregado no portão 2. / 22/09/2026 · João Silva". Cada observação deverá preservar: conteúdo; autor; data/hora; pedido. Uma nova observação não substitui a anterior.

## 21. Obra — Marcar como Entregue
O usuário Obra autorizado poderá marcar um pedido como: Entregue. Essa ação deverá aparecer somente: dentro do detalhe/Histórico do pedido. Não adicionar Entregue como ação rápida na listagem. A autorização precisa existir no backend. Registrar a alteração no Histórico com autor e data/hora.

## 22. Suprimentos — Home
A página inicial do perfil Suprimentos passa a ser: Pedidos. A Visão Geral existente poderá continuar disponível no sistema, mas deixa de ser a landing page.

## 23. Gestão — Home
A página inicial de Gestão também passa a ser: Pedidos. As demais funcionalidades existentes continuam acessíveis pela navegação.

## 24. Ordenação de Pedidos para Suprimentos
Na listagem de Pedidos de Suprimentos, utilizar por padrão: mais antigo → mais novo. A ordenação deverá ser determinística.

## 25. Filtro "Solicitado"
Substituir a exposição permanente do intervalo de datas por um filtro compacto: Hoje; Últimos 3 dias; Últimos 7 dias; Último mês; Personalizado. Ao selecionar qualquer preset, o período correspondente é aplicado automaticamente. Somente ao escolher Personalizado mostrar: De; Até.

## 26. Simplificação dos Filtros
Os painéis atuais de filtros são grandes demais. Simplificar os filtros em todas as telas de listagem aplicáveis. No desktop, utilizar disposição compacta e predominantemente horizontal, seguindo a segunda referência visual fornecida. Exemplo conceitual: [ Obra ▼ ] [ Status ▼ ] [ Prioridade ▼ ] [ Solicitado ▼ ] [ Responsável ▼ ] [ Limpar ]. Não é para remover funcionalidades importantes. O objetivo é: diminuir significativamente o espaço vertical ocupado pelos filtros.

## 27. Filtros no Mobile
Os mesmos filtros deverão funcionar adequadamente em mobile. Devem: reorganizar os controles; evitar overflow; manter legibilidade; continuar fáceis de utilizar; não ocupar desnecessariamente grande parte da tela.

## 28. Filtro "Somente obras ativas"
Adicionar em Todos os Pedidos para: Gestão; Suprimentos. Filtro: Somente obras ativas. Definição: obra com status diferente de Concluído. Ativar esse filtro não deve apagar ou alterar pedidos. Pedidos associados a Outra deverão continuar recebendo tratamento coerente, já que podem não possuir uma obra cadastrada.

## 29. Suprimentos também cria Solicitações
Suprimentos deverá possuir acesso ao mesmo fluxo fundamental de Nova Solicitação. Incluindo: seleção de obra; Outra; descrição; Data da solicitação; Preciso para; Data prevista; anexos; demais campos existentes que continuarem aplicáveis.

## 30. Romaneio
Suprimentos poderá anexar um: Romaneio ao pedido. O sistema deverá identificar tecnicamente que aquele arquivo é um romaneio. Não depender apenas do nome do arquivo. O romaneio deverá aparecer adequadamente no pedido e no Histórico.

## 31. Status "Finalizado"
Adicionar ao fluxo do pedido o status: Finalizado. Suprimentos poderá utilizar esse status para concluir operacionalmente um pedido. Entregue e Finalizado são conceitos distintos.

## 32. Romaneio obrigatório para Finalizar
Regra obrigatória: Suprimentos não pode finalizar um pedido sem romaneio anexado. Quando Suprimentos tentar finalizar: verificar no backend se existe romaneio válido; se existir, permitir a transição; alterar para Finalizado; registrar no Histórico.

## 33. Finalização sem Romaneio
Se não existir romaneio: bloquear a finalização; não alterar o status; não produzir estado parcial; apresentar erro visual. Mensagem conceitual: "Não foi possível finalizar o pedido. Anexe o romaneio antes de finalizar." A validação deverá existir obrigatoriamente no backend. Desabilitar o botão na interface pode ser utilizado como complemento, mas não como única proteção.

## 34. Histórico do Romaneio
Ao anexar romaneio, registrar evento semelhante a: "Romaneio anexado / romaneio-1234.pdf / 22/09/2026 · Maria Souza".

## 35. Histórico da Finalização
Ao finalizar: "Pedido finalizado / Pedido finalizado por Suprimentos. / 22/09/2026 · Maria Souza". Preservar todo o histórico anterior.

## 36. Sidebar
Substituir a navegação principal atual baseada em toolbar por: Sidebar. Aplicar a sidebar ao sistema Albuquerque. Ela deverá: funcionar em desktop; funcionar em mobile; indicar seção atual; respeitar permissões; mostrar somente funcionalidades autorizadas; conter acesso às áreas existentes; incluir Obras quando autorizado; manter logout; acomodar as funcionalidades administrativas existentes.

## 37. Nova Solicitação na Sidebar
Para: Obra; Suprimentos; dar destaque visual a: "+ Nova Solicitação". A ação deverá permanecer fácil de encontrar independentemente da página atual.

## 38. Navegação do Perfil Obra
A sidebar do perfil Obra deverá disponibilizar as áreas às quais esse perfil realmente possui acesso, incluindo: Nova Solicitação; acompanhamento/pedidos; demais funcionalidades já autorizadas. Não apresentar áreas administrativas indevidas.

## 39. Navegação de Suprimentos
A sidebar de Suprimentos deverá contemplar as funcionalidades autorizadas, incluindo: Nova Solicitação; Pedidos; Visão Geral existente; Obras; gerenciamento de associações aplicável; demais funcionalidades existentes autorizadas. Landing page: Pedidos.

## 40. Navegação de Gestão
A sidebar de Gestão deverá contemplar as funcionalidades autorizadas, incluindo: Pedidos; Obras; Usuários; associação usuário × obra; Dashboard/Kanban e demais funcionalidades existentes autorizadas. Landing page: Pedidos.

## 41. Responsividade
Garantir funcionamento mobile das principais alterações: login; Novo Cadastro; convite; sidebar; gerenciamento de obras; associação de usuários; Nova Solicitação; seleção de obra; Outra; upload; listagens; filtros; Histórico; observações; Entregue; romaneio; Finalizado.

## 42. Preservação de Histórico
Nenhuma das seguintes ações deve destruir o histórico anterior: alterar status da obra; concluir obra; associar usuário; desassociar usuário; alterar status do pedido; marcar Entregue; adicionar observação; anexar romaneio; finalizar pedido. O histórico deverá continuar rastreável.

## 43. Dados de Demo
Os dados atuais são de teste/demo e podem ser apagados caso mudanças estruturais realmente exijam reset. Porém: não realizar exclusões silenciosas; não colocar limpeza destrutiva escondida em migration; qualquer reset deve ser explícito e controlado; preservar estrutura/configuração que não precise ser descartada.

## 44. Segurança Existente
As alterações não podem regredir as proteções já existentes, incluindo: autorização server-side; isolamento de obras; usuário inativo bloqueado; restrição para novas solicitações em obras inativas/concluídas; histórico; rate limiting; AuthenticateSession; normalização de e-mail; unicidade case-insensitive; auditoria; pedido_events; user_admin_events; authentication_events; policies/middlewares/scopes existentes.

## 45. Fluxo Final — Novo Cadastro
Login → Novo Cadastro → Nome + E-mail + Senha → Conta criada como perfil Obra → Zero obras associadas → Gestão/Suprimentos localiza usuário → Associa uma ou mais obras → Usuário passa a operar nas obras autorizadas.

## 46. Fluxo Final — Cadastro por Convite
Gestão/Suprimentos → Obra → Gerar convite → Link único — validade 24h → Usuário acessa → Nome + E-mail + Senha → Conta perfil Obra → Associação automática à obra → Convite consumido. Se a pessoa já possuir conta Obra: Convite → Autenticação/confirmação → Conta existente → Nova obra associada → Convite consumido.

## 47. Fluxo Final — Pedido
Obra ou Suprimentos → Nova Solicitação → Obra associada ou Outra → Descrição → Preciso para → Anexos opcionais → Pedido criado → Data da solicitação registrada → Data prevista = +3 dias úteis → Histórico registra criação → Acompanhamento.

## 48. Fluxo Final — Operação
Pedido → Suprimentos acompanha → Pedidos mais antigos primeiro → Obra/Suprimentos adicionam observações → Histórico registra interações → Suprimentos conduz atendimento → Romaneio anexado → Suprimentos seleciona Finalizar → Sistema valida romaneio → Finalizado → Histórico registra finalização. O perfil Obra autorizado também poderá marcar Entregue dentro do detalhe/Histórico.

## 49. Resultado Esperado
Ao final deste incremento, o sistema deverá permitir que Gestão e Suprimentos administrem obras e seus usuários, que novos usuários Obra possam entrar tanto por Novo Cadastro sem associação quanto por convite já associado, e que um mesmo usuário possa participar de várias obras. Obra e Suprimentos poderão criar solicitações, com Outra, anexos, Preciso para e previsão automática de três dias úteis. Os pedidos terão identificação clara do solicitante, Histórico com observações e ações auditáveis. Suprimentos trabalhará a partir de Pedidos, com os mais antigos primeiro, poderá anexar romaneio e somente então finalizar o pedido. Gestão também iniciará em Pedidos. A interface passará a utilizar sidebar responsiva, Nova Solicitação ficará destacada para quem tiver permissão, e os filtros serão significativamente mais compactos em todas as listagens aplicáveis. Esse é o escopo completo validado para implementação.

## Decisões transversais do desenvolvedor (2026-09-22)
- Token de convite é segredo: token em claro não aparece em logs de aplicação nem access logs; solução compatível com a arquitetura atual; decisão imutável na implementação (aplicada na fatia 1).
- Commit documental de docs/agents/*.md (/ai-context) é separado e anterior às execuções Ralph.
- Execução: Ralph 1 → validar → Ralph 2 → validar → Ralph 3 → validar → regressão completa.
- Não reinterpretar requisitos já decididos; não duplicar responsabilidades entre fatias.
