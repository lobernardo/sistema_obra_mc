# Onboarding — Sistema de Solicitações e Compras

2026-09-23

## O que o sistema faz

O sistema transforma um pedido de material da obra em um registro rastreável, com código único, responsável, prazo e histórico completo. Ele substitui o WhatsApp, a planilha e a ligação como canal de solicitação de compra.

O que ele responde, a qualquer momento e para qualquer pessoa autorizada:

- O que foi pedido, por qual obra e por quem
- Quando foi pedido, para quando a obra precisa (Preciso para) e qual a data prevista
- Em que estágio está e quem de Suprimentos é o responsável
- Qual a previsão de entrega e se o pedido está atrasado
- Tudo o que aconteceu com o pedido, na ordem em que aconteceu — inclusive observações e anexos

**O que está fora do escopo desta versão** — diga isso na primeira reunião, evita frustração depois: não há cotação, fornecedores, catálogo de materiais (SKU), preços, financeiro, aprovação hierárquica, entrega parcial, notificações por e-mail de movimentação (só os e-mails de acesso) e integração com ERP. A descrição do pedido é texto livre, escrita por quem solicita. Anexos e observações existem, em forma restrita: anexos só no envio da solicitação e no romaneio de Suprimentos, e observações que só se acrescentam — nenhum dos dois pode ser editado nem apagado.

O sistema é acessado pelo navegador, sem instalação, e funciona em celular. Endereço de produção: albuquerque.mcinteligencia.com.

## Vocabulário — as palavras do sistema

Alinhe estes termos antes de mostrar qualquer tela; toda a interface usa exatamente estas palavras.

| Termo | O que significa no sistema |
| --- | --- |
| Pedido | A solicitação registrada. Ganha um código único no formato PED-000123 no momento do envio |
| Obra | O canteiro que solicitou. Um usuário de obra só enxerga as obras às quais foi associado. Quando o canteiro não está na lista, a solicitação usa **Outra**, com uma referência livre opcional |
| Solicitante | Quem criou o pedido. Gravado automaticamente, não se escolhe |
| Preciso para | Quando o material precisa estar no canteiro. É ela que define o atraso |
| Data prevista | Calculada sozinha no envio: o 3º dia útil depois da data da solicitação (sem fins de semana e feriados nacionais). Não muda depois |
| Descrição | Texto livre, um item por linha. Não há catálogo nem campo de quantidade separado |
| Status | O estágio do pedido. São sete; Suprimentos conduz o fluxo e a obra só pode marcar Entregue |
| Responsável | A pessoa de Suprimentos que tocou o pedido. Só usuários do perfil Suprimentos podem ser responsáveis |
| Prioridade | Baixa, Normal, Alta ou Urgente. Quem define é Suprimentos, não a obra |
| Previsão de entrega | A data prometida por Suprimentos. Começa vazia e é diferente da Data prevista |
| Histórico | A lista de tudo que mudou no pedido, com autor e data. Não pode ser editada nem apagada por ninguém |

### Os sete status

```mermaid
flowchart LR
  A[Solicitado] --> B[Em análise]
  B --> C[Em compra/<br/>preparação]
  C --> D[Aguardando<br/>entrega]
  D --> E[Entregue]
  E --> F[Finalizado]
  A -.-> X[Cancelado]
```

A linha cheia é o caminho normal. Na prática Suprimentos pode mover o pedido para qualquer estágio ativo, inclusive voltar, e pode marcar **Entregue** a partir de qualquer um deles; a obra também pode marcar o próprio pedido como Entregue. **Cancelado** nunca aparece no Kanban e só é alcançado pelo botão Cancelar pedido.

**Finalizado** vem depois de Entregue e fecha o pedido operacionalmente. Só Suprimentos finaliza, pelo botão **Finalizar pedido**, e só depois de anexar o **romaneio** — sem romaneio o sistema recusa. É possível finalizar a partir de Entregue ou de qualquer estágio ativo.

**Entregue**, **Cancelado** e **Finalizado** são estados finais: responsável, prioridade, previsão e status não aceitam mais alteração. As duas exceções: um pedido Entregue ainda pode receber o romaneio e ser Finalizado, e qualquer pedido, em qualquer status, aceita observações. Cancelado e Finalizado não têm volta.

### Atraso e prazo

Um pedido está **Atrasado** quando a data em Preciso para já passou e ele ainda não foi entregue, finalizado nem cancelado. O dia vira à meia-noite de Brasília. Entregue com atraso não conta como atrasado — o indicador mostra o que ainda precisa de ação, não o histórico de pontualidade.

No dashboard, os pedidos pendentes aparecem em três faixas: **Dentro do prazo**, **Vencendo em breve** (faltam até 3 dias) e **Atrasado**.

## Os três perfis

Cada usuário tem um único perfil, e a barra lateral (a sidebar, à esquerda; no celular, atrás do botão **Menu**) muda conforme ele. Não existe usuário com dois perfis.

Uma conta nasce de três jeitos: a Gestão cadastra em **Usuários**; a própria pessoa usa o **Novo Cadastro** (link na tela de login, endereço `/cadastro`), que sempre cria uma conta de perfil Obra **sem nenhuma obra**; ou a pessoa aceita um **convite de obra**, que cria a conta Obra já associada àquela obra. Quem entra pelo Novo Cadastro só enxerga pedidos depois que a Gestão ou Suprimentos a associar a uma obra em **Associações**.

| Perfil | Itens da barra lateral | Alcance dos dados | Pode alterar |
| --- | --- | --- | --- |
| Obra | + Nova Solicitação, Acompanhamento | Pedidos das obras às quais está associado, mais os pedidos "Outra" que ela mesma criou | Cria solicitações; depois do envio só acrescenta observações e marca o pedido como Entregue |
| Suprimentos | + Nova Solicitação; Operação: Pedidos, Visão Geral, Kanban; Cadastros: Obras, Associações | Todos os pedidos de todas as obras | Cria solicitações; status, responsável, prioridade, previsão, observações, romaneio, finalização e cancelamento; cadastra obras, convites e associações |
| Gestão | Operação: Pedidos, Dashboard, Kanban; Administração: Obras, Associações, Usuários | Todos os pedidos de todas as obras | Nada nos pedidos — cadastra usuários, obras, convites e associações |

O botão **Sair** fica no fim da barra lateral, para todos.

Três consequências que valem explicar na reunião:

1. **Gestão não opera pedidos.** O dashboard, a lista de Pedidos e o Kanban da Gestão são somente leitura; a Gestão também não cria solicitações. Quem precisa mexer em pedido precisa do perfil Suprimentos.
2. **A obra não define prioridade.** Ela informa a data em Preciso para; a prioridade é uma leitura de Suprimentos sobre a fila.
3. **A obra não vê o que não é dela.** Duas obras diferentes não enxergam os pedidos uma da outra, nem pelo link direto.

Ao entrar, cada pessoa cai direto na sua página inicial: a obra em Acompanhamento, e Suprimentos e Gestão em Pedidos. Visão Geral, Kanban e Dashboard continuam a um clique, na barra lateral.

## Acesso: convite, senha e desativação

Ninguém recebe senha por WhatsApp nem senha provisória compartilhada. Há três portas de entrada, e em todas a própria pessoa define a senha: o cadastro feito pela Gestão (com e-mail de primeiro acesso), o Novo Cadastro e o convite de obra.

### Primeiro acesso

1. Gestão cria o usuário em **Usuários → Novo usuário** (nome, e-mail, perfil e, para os perfis Obra e Suprimentos, as obras — nenhuma, uma ou várias).
2. O sistema envia o e-mail de convite automaticamente, assim que o usuário é salvo.
3. A pessoa clica no link, cai na tela **Defina sua senha**, digita a senha duas vezes e já entra.

O link do convite vale **72 horas**. Depois disso ele para de funcionar e a Gestão precisa reenviar pelo botão **Reenviar convite / Enviar link de redefinição** na lista de usuários. A senha precisa ter no mínimo 8 caracteres.

### Novo Cadastro

Na tela de login, **Novo Cadastro** leva ao formulário com nome, e-mail, senha e confirmação. A conta nasce ativa, com perfil Obra e **zero obras**: a pessoa entra, mas o Acompanhamento avisa que o acesso às obras depende de associação feita pela Gestão ou por Suprimentos. Até ser associada a uma obra ativa, ela também não consegue enviar solicitação. E-mail já cadastrado é recusado com a orientação de entrar ou usar Esqueci minha senha.

### Convite de obra

Gestão e Suprimentos geram o convite em **Obras → Editar → Convites → Gerar convite**. O link vale **24 horas**, serve uma única vez e aparece **uma única vez** na tela — copie e envie na hora. Quem abre o link sem conta cria uma conta Obra já associada àquela obra; quem já tem conta de perfil Obra entra e confirma; conta de outro perfil é recusada. Obra Concluída não gera convite, e um convite pendente pode ser revogado na mesma lista, que mostra o estado de cada um (Pendente, Expirado, Revogado, Utilizado).

### Esqueci minha senha

O próprio usuário resolve, pelo link **Esqueci minha senha** na tela de login. O link enviado por e-mail vale **60 minutos** — bem menos que o convite, e essa diferença costuma gerar chamado; vale avisar.

Três comportamentos que confundem o suporte:

- A tela sempre responde a mesma mensagem, exista o e-mail ou não. É proposital, para não revelar quem tem conta.
- Conta desativada não recebe link nenhum. Se a pessoa jura que pediu e não chegou, a primeira verificação é o status dela na lista de usuários.
- Só existe um link válido por e-mail. Se a Gestão reenviar o convite enquanto a pessoa tinha um link de redefinição aberto, o antigo deixa de valer.

### Desativar em vez de excluir

Usuário não se apaga, se desativa — o histórico dos pedidos precisa continuar mostrando quem fez o quê. Ao desativar: o login para de funcionar na hora, a sessão aberta cai na próxima tela e a pessoa some da lista de possíveis responsáveis. Os pedidos dela continuam intactos.

Duas travas de segurança: ninguém desativa nem muda o próprio perfil, e o sistema não deixa desativar o último usuário ativo de Gestão. Por isso o cliente precisa de **pelo menos dois usuários de Gestão** desde o primeiro dia.

## Guia do perfil Obra

A obra tem duas telas: registrar a necessidade e acompanhar. O treinamento dela cabe em 20 minutos.

### Criar uma solicitação

1. Clicar em **+ Nova Solicitação**, o primeiro item da barra lateral (no celular, o botão fica na barra superior, ao lado de **Menu**).
2. **Obra** — selecionar. A lista mostra só as obras ativas às quais a pessoa está associada, mais a opção **Outra**; com Outra, o campo **Referência** (opcional) guarda um nome livre, como `Galpão provisório`. Outra não cria obra nem dá acesso a obra de mesmo nome.
3. **Preciso para** — quando o material precisa estar no canteiro.
4. **Descrição** — campo livre, **um item por linha**, com quantidade e unidade no mesmo texto. Ex.: `20 sacos de cimento CP-II`.
5. **Anexos** (opcional) — até 10 arquivos, de até 10 MB cada: JPG, PNG, WEBP, PDF, DOCX ou XLSX.
6. **Enviar solicitação**. A tela devolve o código do pedido, por exemplo PED-000123.

A Data da solicitação e a Data prevista aparecem no formulário e são preenchidas pelo sistema. Ensine a anotar ou fotografar o código: é por ele que a obra cobra Suprimentos e que Suprimentos localiza o pedido.

### Acompanhar

**Acompanhamento** lista os pedidos das obras da pessoa, do mais recente para o mais antigo, com as colunas Código, Solicitante / Obra, Descrição, Solicitado em, Preciso para, Status, Prioridade, Responsável, Previsão (a Data prevista) e Atraso. Os filtros são busca, obra, status, **Somente com atraso** e **Solicitado** (Hoje, Últimos 3 dias, Últimos 7 dias, Último mês ou Personalizado, com De e Até). Clicando no código, abre o pedido com todos os dados, anexos e o histórico completo.

No detalhe, a obra pode **Adicionar observação** (até 2000 caracteres, fica no histórico e não se altera) e, quando o material chega, **Marcar como entregue**, com confirmação em duas etapas.

### Os três pontos que geram reclamação

- **O pedido não pode ser editado depois de enviado.** Nem pela obra, nem por Suprimentos. Errou o item ou a data? Dá para registrar uma observação, mas a correção de verdade é pedir o cancelamento a Suprimentos e criar um novo. Por isso vale reforçar a conferência antes do botão Enviar.
- **Uma solicitação por necessidade, não por dia.** Se a obra junta materiais de frentes diferentes num pedido só, tudo anda no mesmo status e a entrega parcial não existe no sistema.
- **Não há aviso por e-mail quando o status muda.** A obra precisa entrar no sistema para saber. Isso é combinado de processo, não falha.

## Guia do perfil Suprimentos

Suprimentos é o único perfil que conduz pedidos pelo fluxo. A página inicial é **Pedidos**; a rotina diária acontece no **Kanban**, para enxergar a fila, e no **detalhe do pedido**, para tomar decisões — com a **Visão Geral** como resumo do dia. Suprimentos também cria solicitações e cuida de **Obras** e **Associações**.

### Visão Geral — o resumo do dia

Três números no topo: **Total de pedidos**, **Atrasados** e **Entregues hoje**. Abaixo, a contagem de pedidos em cada status do fluxo, inclusive Finalizado (cancelados ficam de fora), um atalho para o Kanban e as cinco solicitações mais recentes.

É a mesma matemática do dashboard da Gestão: atraso, pendência e entrega saem das mesmas regras usadas no Kanban e nas listagens, então os números nunca divergem entre as telas.

**Entregues hoje** conta a entrega registrada no dia — o momento em que alguém marcou o pedido como Entregue, não a previsão. Na demonstração com dados de exemplo, esse número só aparece preenchido no mesmo dia em que os dados foram carregados.

### Kanban — a fila do dia

Seis colunas, na ordem do fluxo: Solicitado, Em análise, Em compra/preparação, Aguardando entrega, Entregue e Finalizado. Cada coluna mostra a contagem de pedidos, e cada card traz código, obra, descrição, Preciso para, responsável, previsão de entrega, prioridade e o selo de atraso. Pedidos cancelados não aparecem aqui.

Para mover: arrastar o card para outra coluna. Quem prefere não arrastar — ou está no celular — usa o seletor **Mover para** dentro do próprio card. As duas formas fazem exatamente a mesma coisa e registram o mesmo evento no histórico. Cards em Entregue e Finalizado não têm seletor, e nenhum card vai para Finalizado pelo Kanban: finalizar é só pelo detalhe do pedido, com romaneio.

Reordenar cards dentro da mesma coluna não faz nada: a ordem é fixa e não é uma fila de prioridade.

### Detalhe do pedido — onde se decide

Abre clicando no código do pedido, no Kanban ou em Pedidos. Do lado esquerdo ficam os dados e o histórico; do lado direito, o bloco **Operação**, com quatro controles independentes:

| Controle | O que fazer | Regra |
| --- | --- | --- |
| Status → **Mover status** | Avançar, voltar ou marcar Entregue | Pode ir de qualquer estágio ativo para qualquer outro, e para Entregue de onde estiver |
| Responsável → **Salvar responsável** | Assumir o pedido ou passar a um colega | Só aparecem usuários ativos do perfil Suprimentos |
| Prioridade → **Salvar prioridade** | Classificar a fila | Baixa, Normal, Alta, Urgente |
| Previsão de entrega → **Salvar previsão** | Dar a data prometida à obra | Pode ser alterada quantas vezes for preciso; cada mudança fica no histórico |

Cada botão salva o seu campo isoladamente — não existe um “salvar tudo” no fim da tela. O bloco Operação some quando o pedido chega a um estado final.

### Romaneio e Finalizar pedido

O bloco **Romaneio e finalização** aparece enquanto o pedido está em um estágio ativo ou Entregue. **Anexar romaneio** aceita PDF, JPG ou PNG de até 10 MB. **Finalizar pedido** pede confirmação em duas etapas e só funciona depois que há um romaneio anexado; finalizar é irreversível.

### Observações

Suprimentos e a obra podem **Adicionar observação** em qualquer pedido, em qualquer status — até 2000 caracteres, registrada no histórico e sem edição. A Gestão só lê.

### Nova Solicitação por Suprimentos

Suprimentos também tem **+ Nova Solicitação** no topo da barra lateral, com o mesmo formulário da obra. A regra é a mesma: é preciso estar associado a pelo menos uma obra ativa (em Associações), inclusive para usar Outra.

### Cancelar um pedido

O botão **Cancelar pedido** fica no fim do bloco Operação e pede confirmação em duas etapas. Cancelamento é **irreversível**: não existe reabrir. O caminho de volta é a obra criar um pedido novo.

Use cancelamento para pedido duplicado, pedido criado com erro e necessidade que deixou de existir. O pedido cancelado não some: continua em Pedidos, com o histórico e o autor do cancelamento.

### Pedidos — a lista de trabalho

É a página inicial de Suprimentos e a tela de consulta. A lista vai **do pedido mais antigo para o mais novo** (pela data da solicitação), então o que está esperando há mais tempo aparece primeiro; entregues, finalizados e cancelados ficam misturados na mesma ordem. As colunas são as mesmas do Acompanhamento: Solicitante / Obra, Descrição, Preciso para e Previsão (a Data prevista).

Os filtros ficam num painel compacto (no celular, atrás do botão **Filtros**) e se combinam: **Busca** por código, obra ou descrição; **Obra**; **Status**; **Prioridade**; **Responsável**; **Somente com atraso**; **Solicitado**, com os atalhos Hoje, Últimos 3 dias, Últimos 7 dias e Último mês, ou Personalizado com De e Até; **Preciso para**, com De e Até; e **Somente obras ativas**, que esconde os pedidos de obras Concluídas e mantém os pedidos Outra. Os atalhos contam dias inteiros no horário de Brasília, terminando hoje. No topo, três indicadores mostram Total, Pendentes e Atrasados (Preciso para vencido e não concluídos) **do recorte filtrado**, não do sistema inteiro.

O filtro de **Status** é o caminho para separar cancelados e entregues do resto — escolher `Cancelado` isola exatamente os cancelamentos. O botão **Limpar filtros** devolve a lista completa.

O recorte fica na barra de endereços: qualquer combinação de filtros pode ser copiada e enviada a outra pessoa, que abre a mesma consulta. É por isso que o drill-down do dashboard funciona.

### Obras e Associações

**Obras** (em Cadastros) lista as obras com nome, responsável e status — A iniciar, Em andamento ou Concluído. **Nova obra** e **Editar** cuidam do cadastro; o nome não pode repetir, e obra não se exclui. Obra Concluída deixa de receber solicitações e convites, mas mantém pedidos, histórico e associações. A tela de edição traz também a seção **Convites** (ver Acesso).

**Associações** localiza um usuário de perfil Obra ou Suprimentos e mostra as obras dele: **Adicionar obras** (uma ou várias) e **Remover**, com confirmação. Remover uma associação não mexe em pedido nenhum. Para Suprimentos, a associação não limita o que a pessoa vê — só define por quais obras ela pode abrir solicitação.

### O combinado mínimo de processo

O sistema não obriga ordem nem prazo; quem obriga é o acordo com o cliente. Sugestão de acordo, a ajustar na reunião:

1. Todo pedido novo ganha **responsável** no mesmo dia.
2. Ao sair de **Em análise**, o pedido já tem **previsão de entrega** preenchida.
3. A **prioridade** é definida quando o responsável assume, não depois.
4. **Entregue** só é marcado com a confirmação de recebimento da obra.

## Guia do perfil Gestão

A Gestão lê indicadores e administra pessoas e obras. Não muda pedidos — e essa separação é proposital, porque mantém o histórico com um dono único por decisão.

### Dashboard

Indicadores recalculados sobre o mesmo recorte de filtros:

| Indicador | Leitura |
| --- | --- |
| Volume total | Pedidos no escopo filtrado |
| Pendentes | Não entregues nem cancelados — clicável |
| Atrasados | Preciso para vencido e não concluídos — clicável |
| Entregues | Pedidos já entregues no escopo filtrado — clicável |
| Distribuição por status | Quantos pedidos em cada estágio |
| Visão por obra | Quais canteiros concentram a demanda |
| Prazos | Dentro do prazo, Vencendo em breve e Atrasado, somente entre os pendentes — com um gráfico de rosca ao lado da lista |

Os filtros do topo são cinco: **Período (solicitação)** com De e Até, **Obra**, **Status**, **Prioridade** e **Responsável**. O período filtra pela data em que o pedido foi criado, em dias do horário de Brasília — não por Preciso para. Vale dizer isso em voz alta na demonstração: é a confusão mais comum do dashboard.

**Drill-down:** clicar em Pendentes, Atrasados ou Entregues abre Pedidos já filtrado com o mesmo recorte — inclusive obra, status, prioridade, responsável e período (que aparece como Solicitado → Personalizado) —, listando pedido a pedido. É o caminho de “esse número está alto, quais são?”.

### Pedidos e Kanban

As mesmas telas de Suprimentos, sem nenhum controle de alteração. **Pedidos** é a página inicial da Gestão, do mais recente para o mais antigo, com os mesmos filtros compactos, os atalhos de Solicitado e **Somente obras ativas**. Servem para a reunião semanal: o Kanban mostra onde a fila está empoçando, a lista permite buscar um pedido específico por código ou material.

### Obras e Associações

As mesmas telas descritas no guia de Suprimentos, com os mesmos poderes: cadastrar e editar obras, gerar e revogar convites, associar e remover obras de usuários Obra e Suprimentos.

### Usuários

A tela **Usuários** lista nome, e-mail, perfil, obras e status de acesso, com busca por nome ou e-mail. As ações são quatro:

- **Novo usuário** — nome, e-mail, perfil e obras. O convite sai automaticamente.
- **Editar** — muda nome, e-mail, perfil e obras. Trocar o perfil para Gestão remove as obras associadas.
- **Ativar / Desativar** — corta ou devolve o acesso, sem apagar nada.
- **Reenviar convite / Enviar link de redefinição** — o botão para “não recebi o e-mail” ou “o link expirou”.

A regra de obras do formulário: usuários de perfil **Obra** e **Suprimentos** podem ter nenhuma, uma ou várias obras (a escolha é opcional), e usuário de **Gestão** não pode ter obras — enxerga todas por definição. Um usuário Obra sem obras entra, mas não vê pedidos nem consegue solicitar até ser associado.

O e-mail é único e funciona como login. Ele pode ser corrigido em **Editar**, mas a pessoa passa a entrar com o novo endereço — avise antes de trocar.

### O que a Gestão não faz nesta versão

Criar solicitações, registrar observações, anexar romaneio ou mudar qualquer campo de um pedido. Para isso, o perfil é Suprimentos. Também não há exclusão de obra: obra encerrada vira Concluída.

## As regras que travam o usuário

Estas são as regras que geram “não consigo” no primeiro mês. Antecipe todas na reunião — explicadas antes, viram processo; descobertas depois, viram chamado.

| Situação | Por que o sistema se comporta assim | O que fazer |
| --- | --- | --- |
| O pedido não pode ser editado depois de enviado | O texto original é a prova do que a obra pediu | Registrar uma observação, ou cancelar e criar outro |
| Pedido Entregue, Finalizado ou Cancelado não muda mais de status, responsável, prioridade nem previsão | Estado final congela o registro | Se foi marcado Entregue por engano, registrar uma observação e criar um pedido novo |
| Não dá para finalizar sem romaneio | O romaneio é o comprovante da entrega | Anexar o romaneio no detalhe e então Finalizar pedido |
| Cancelamento não tem volta | O cancelamento é uma decisão registrada, não um rascunho | Confirmar na segunda etapa só com certeza |
| Só Suprimentos pode ser responsável | Responsável é quem executa a compra | Para dar responsabilidade a alguém, mudar o perfil da pessoa |
| A obra não vê pedido de outra obra | Isolamento por obra, inclusive por link direto | Associar a pessoa à obra em Associações; se precisa ver tudo, o perfil é Gestão |
| Conta nova do Novo Cadastro não vê nada | A conta nasce sem obras | Gestão ou Suprimentos associa a pessoa em Associações |
| O histórico não pode ser corrigido nem apagado | É a trilha de auditoria do processo | Registrar a correção como um novo evento (nova mudança de status, nova previsão) |
| Desativar usuário não apaga os pedidos dele | O histórico precisa manter a autoria | Nada — é o comportamento correto |

Duas ausências que valem dizer com todas as letras, porque o cliente vai perguntar:

- **Não há aprovação.** O pedido da obra vai direto para a fila de Suprimentos. Se o cliente quiser um aval do engenheiro antes, isso é combinado fora do sistema nesta versão.
- **Não há entrega parcial.** Um pedido é entregue inteiro ou não é. Se metade chegou, o pedido continua em Aguardando entrega — ou a obra separa as necessidades em dois pedidos desde o início.

## Roteiro de onboarding sugerido

Quatro encontros em duas semanas, do menor público para o maior. A ordem importa: Suprimentos precisa estar treinado antes de a primeira obra enviar pedido, senão a fila nasce parada e o sistema perde credibilidade na primeira semana.

| # | Sessão | Público | Duração | Objetivo |
| --- | --- | --- | --- | --- |
| 1 | Alinhamento e decisões | Gestão (2 a 3 pessoas) | 60 min | Fechar obras, usuários, perfis, associações e o combinado de processo |
| 2 | Operação | Suprimentos | 90 min | Rodar a fila de ponta a ponta com pedidos de teste |
| 3 | Solicitação | Encarregados e engenheiros de obra | 45 min | Criar a primeira solicitação real, cada um no próprio celular |
| 4 | Indicadores | Gestão | 45 min | Ler o dashboard e definir a reunião semanal |

### Sessão 1 — Alinhamento e decisões

Não é demonstração de tela; é a sessão que produz a lista de cadastro. Saia dela com: a lista de obras ativas com o nome exato que aparecerá no sistema, a lista de pessoas com nome, e-mail, perfil e obras, os dois usuários de Gestão e o combinado de processo de Suprimentos.

Perguntas que destravam a lista: quem, por obra, tem autoridade para pedir? O almoxarife pede ou só o engenheiro? Quem de Suprimentos responde por qual frente?

### Sessão 2 — Operação (Suprimentos)

Metade demonstração, metade mão na massa. Exercício em sala, cada participante no próprio acesso:

1. Abrir Pedidos, filtrar por Solicitado → Últimos 7 dias e encontrar um pedido pelo código; depois achá-lo no Kanban.
2. Assumir o pedido como responsável e definir prioridade.
3. Mover para Em compra/preparação pelo arrastar e voltar pelo seletor.
4. Lançar uma previsão de entrega e depois alterá-la.
5. Abrir o histórico e reconhecer as próprias ações.
6. Marcar Entregue e tentar mudar algo depois — ver a trava funcionando.
7. Anexar o romaneio e finalizar o pedido entregue.
8. Adicionar uma observação e cancelar outro pedido de teste, com a confirmação em duas etapas.
9. Cadastrar uma obra de teste, gerar um convite e associar um usuário em Associações.

### Sessão 3 — Solicitação (Obra)

Curta, no celular, de preferência no canteiro. Cada pessoa cria uma solicitação real pelo **+ Nova Solicitação**, anota o código e localiza o pedido em Acompanhamento. Mostre a observação e o Marcar como entregue. Feche com as três frases que a obra precisa levar: confira antes de enviar porque não dá para editar; guarde o código; consulte o sistema em vez de ligar.

### Sessão 4 — Indicadores (Gestão)

Com dados reais da primeira semana, não com dados de demonstração. Percorra os seis indicadores, use o drill-down de Atrasados e combine o ritual: um horário fixo por semana, começando por Atrasados, depois Pendentes por obra.

### Acompanhamento pós-implantação

Nos primeiros 30 dias, dois checkpoints curtos: no dia 7, verificar se há pedidos sem responsável ou sem previsão; no dia 30, revisar o que o cliente sentiu falta e decidir o que entra na próxima versão.

## Checklist antes de liberar o sistema

O que precisa estar pronto antes da Sessão 2, feito pela equipe técnica.

- [ ] Domínio albuquerque.mcinteligencia.com abrindo com HTTPS válido
- [ ] Envio de e-mail real ligado e testado — sem isso, nenhum convite chega
- [ ] Endereço remetente definido e reconhecível pelo cliente
- [ ] Obras reais cadastradas em **Obras**, com o nome exato aprovado na Sessão 1, e usuários associados em **Associações**
- [ ] Dados de demonstração removidos — nada com o prefixo \[DEMO\] pode sobrar
- [ ] Dois usuários de Gestão criados e com senha definida por eles mesmos
- [ ] Um usuário real de cada perfil testado de ponta a ponta: convite recebido, senha criada, login feito
- [ ] Teste do fluxo completo em produção: um pedido criado pela obra, movido por Suprimentos até Entregue, com romaneio anexado e Finalizado, e depois cancelado outro
- [ ] Anexos persistentes em produção: um anexo enviado continua baixando depois de um novo deploy
- [ ] Rotina de backup do banco confirmada
- [ ] Canal de suporte definido: quem o cliente aciona, por onde e em qual prazo

Duas armadilhas conhecidas: os usuários de demonstração têm senha padrão e não podem sobreviver à virada; e quem entra pelo Novo Cadastro não vê nada até ser associado, então as associações precisam estar feitas antes do treinamento da obra.

## Perguntas frequentes e limites desta versão

**“Não recebi o e-mail de acesso.”** Conferir, nesta ordem: caixa de spam, se o e-mail cadastrado está correto na lista de usuários, se a conta está Ativa. Resolvido isso, usar **Reenviar convite / Enviar link de redefinição**.

**“Meu login parou de funcionar de repente.”** Quase sempre é conta desativada — a sessão cai na tela seguinte, com a mensagem de conta desativada. A Gestão reativa.

**“Errei a data do pedido.”** Não há edição. Registrar uma observação ou pedir a Suprimentos o cancelamento e criar outro.

**“O pedido sumiu do Kanban.”** Foi cancelado, ou entregue ou finalizado há tempo suficiente para a coluna encher. Buscar pelo código em Pedidos.

**“Por que o pedido entregue com atraso não aparece em Atrasados?”** O indicador mostra o que ainda exige ação. Entregue sai da conta, mesmo fora do prazo.

**“Criei minha conta e não vejo nenhum pedido.”** Conta do Novo Cadastro nasce sem obras. Gestão ou Suprimentos associa a pessoa em **Associações**.

**“Quero receber e-mail quando o status mudar.”** Não existe nesta versão. E-mail, só para convite e redefinição de senha.

### Limites conhecidos, para registrar como próximos passos

| Limite | Impacto no dia a dia |
| --- | --- |
| Sem edição do pedido original | Correção passa por cancelar e recriar |
| Sem entrega parcial | Pedido misto trava inteiro até o último item |
| Anexos só no envio e no romaneio | Foto tirada depois do envio fica fora do sistema |
| Sem aprovação hierárquica | Qualquer usuário de obra manda direto para a fila |

Leve esta tabela para a Sessão 1. Ela costuma antecipar o pedido de evolução que o cliente faria no dia 30, e transforma limitação em roteiro de próxima versão.
