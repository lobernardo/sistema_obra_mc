# AJUSTES FINAIS — SISTEMA DE SOLICITAÇÕES E COMPRAS

## 1. OBJETIVO

Evoluir a aplicação Laravel atualmente validada e em produção para a versão final destinada à Albuquerque Engenharia, adicionando:

1. administração de usuários pela Gestão;
2. fluxo seguro de primeiro acesso;
3. recuperação de senha pela tela de login;
4. infraestrutura necessária para envio dos e-mails de autenticação;
5. identidade visual global da Albuquerque;
6. personalização da tela de login;
7. identificação discreta da MC Inteligência como fornecedora da tecnologia;
8. preparação para utilização de domínio da MC Inteligência;
9. manutenção da arquitetura, regras de negócio, segurança e fluxos já validados;
10. aplicação das logos oficiais somente na etapa final.

Esta evolução deve partir do sistema Laravel existente.

Não reconstruir a aplicação.

Não substituir a arquitetura atual.

---

# 2. ESTADO ATUAL VALIDADO

Projeto local:

`/home/leonardool/sistema_obra_mc`

GitHub:

`https://github.com/lobernardo/sistema_obra_mc`

Branch atualmente utilizada:

`build/v0-demo-laravel`

Baseline de produção validada:

`82e4d48`

Produção Railway:

`https://laravel-app-production-16ed.up.railway.app`

Estado validado antes desta evolução:

* Laravel em produção;
* Blade + Livewire funcionando;
* PostgreSQL Railway funcionando;
* HTTPS funcionando;
* `/up` retorna HTTP 200;
* `APP_ENV=production`;
* `APP_DEBUG=false`;
* 14/14 migrations aplicadas;
* autenticação funcionando;
* autorização por perfil funcionando;
* isolamento entre obras funcionando;
* perfil Obra funcionando;
* perfil Suprimentos funcionando;
* perfil Gestão funcionando;
* Kanban funcionando;
* histórico funcionando;
* dashboard funcionando;
* persistência real em PostgreSQL validada;
* 296 testes;
* 854 assertions;
* 0 falhas;
* build Vite validado;
* nenhum erro crítico conhecido em produção.

Essa baseline deve ser preservada.

---

# 3. STACK A PRESERVAR

Manter:

* Laravel;
* PHP;
* Blade;
* Livewire;
* Tailwind CSS;
* Vite;
* PostgreSQL;
* Eloquent ORM;
* Laravel Auth/session;
* Policies/Gates;
* Actions/Services/Enums existentes;
* Pest;
* Pest Browser/Playwright;
* Composer;
* npm;
* Laravel Boost;
* Git/GitHub;
* Railway.

Não reintroduzir:

* Next.js;
* Supabase;
* React como aplicação separada.

Não adicionar sem necessidade comprovada:

* Redis;
* microserviços;
* workers;
* arquitetura distribuída;
* serviços adicionais.

---

# 4. REGRA PRINCIPAL DE PRESERVAÇÃO

As alterações visuais desta evolução NÃO devem alterar regras de negócio ou fluxos já validados.

Preservar integralmente:

* criação de solicitações;
* visualização por obra;
* responsáveis;
* prioridades;
* datas necessárias;
* previsão de entrega;
* cálculo de atraso;
* cancelamento;
* workflow;
* histórico;
* Kanban;
* dashboard;
* autenticação;
* Policies/Gates;
* isolamento por obra;
* Gestão read-only nos fluxos que atualmente são read-only;
* regras de Suprimentos;
* auditoria;
* persistência.

Mudanças funcionais deverão se limitar ao escopo explicitamente definido neste documento.

---

# 5. NOVA ADMINISTRAÇÃO DE USUÁRIOS

## 5.1 Gestão como administrador

Nesta versão haverá apenas um usuário Gestão.

Esse usuário Gestão também exercerá a função de administrador do sistema.

Portanto:

`Gestão = Administrador`

Não criar neste momento um quarto perfil `Admin`.

Entretanto, evitar acoplamento desnecessário que impeça futuramente:

* criar papel `Admin`;
* transferir as permissões administrativas para esse papel;
* retirar administração de usuários do perfil Gestão.

Essa separação futura não faz parte desta entrega.

---

# 6. ÁREA “USUÁRIOS”

Adicionar à área de Gestão uma seção:

`Usuários`

Ela deverá permitir ao usuário Gestão administrar os acessos da aplicação.

Funcionalidades mínimas:

* listar usuários;
* pesquisar usuários;
* visualizar nome;
* visualizar e-mail;
* visualizar perfil;
* visualizar status;
* visualizar obras associadas quando aplicável;
* criar usuário;
* editar usuário;
* alterar perfil;
* associar obras;
* alterar associações de obras;
* ativar acesso;
* desativar acesso;
* iniciar/reemitir fluxo de definição ou recuperação de senha quando apropriado.

A interface deverá seguir a identidade visual definida neste documento.

---

# 7. PERFIS DISPONÍVEIS

Continuam existindo:

* Obra;
* Suprimentos;
* Gestão.

Ao cadastrar usuário do tipo Obra, permitir associação a uma ou mais obras conforme as regras atuais do sistema.

Preservar a autorização existente.

A criação de usuários não pode permitir bypass das Policies/Gates.

---

# 8. REMOÇÃO DE ACESSOS

A ação administrativa normal para remover acesso deve ser:

`Desativar usuário`

Não apagar fisicamente usuários que já possuam relação com:

* pedidos;
* eventos;
* histórico;
* auditoria;
* outros dados relevantes.

Objetivo:

preservar rastreabilidade histórica.

Usuário desativado:

* não pode autenticar;
* permanece registrado;
* continua aparecendo corretamente no histórico onde participou.

Se exclusão física for considerada, ela só poderá ocorrer quando houver garantia de ausência de dependências e histórico.

Preferir desativação.

---

# 9. PRIMEIRO ACESSO DE NOVO USUÁRIO

Adotar fluxo seguro de convite.

Fluxo esperado:

Gestão cadastra:

* nome;
* e-mail;
* perfil;
* obra(s), quando aplicável.

Depois:

1. sistema cria o usuário;
2. sistema envia e-mail de convite;
3. usuário recebe link seguro;
4. usuário acessa o link;
5. usuário define sua própria senha;
6. usuário entra no sistema.

A Gestão não precisa conhecer a senha do usuário.

Não utilizar senhas padrão compartilhadas.

Não enviar senha em texto puro por e-mail.

Não armazenar senha reversível.

Utilizar os mecanismos seguros disponibilizados pelo Laravel sempre que apropriado.

---

# 10. RECUPERAÇÃO DE SENHA

Adicionar à tela de login:

`Esqueci minha senha`

Qualquer usuário cadastrado e ativo deverá conseguir recuperar o próprio acesso.

Fluxo:

1. usuário acessa login;
2. seleciona “Esqueci minha senha”;
3. informa e-mail;
4. sistema processa a solicitação;
5. usuário recebe link seguro por e-mail;
6. link possui token temporário;
7. usuário abre página de redefinição;
8. informa nova senha;
9. senha é atualizada com segurança;
10. usuário consegue autenticar com a nova senha.

Aplicável a:

* Obra;
* Suprimentos;
* Gestão.

---

# 11. SEGURANÇA DO RESET DE SENHA

Utilizar mecanismo seguro do Laravel.

Garantir:

* token temporário;
* expiração;
* hash seguro de senha;
* invalidação apropriada;
* proteção CSRF;
* rate limiting quando aplicável;
* validação de senha;
* nenhuma exposição da senha existente.

A aplicação não deve permitir consultar senha atual.

Evitar enumeração de usuários.

A resposta ao pedido de recuperação não deve revelar de forma insegura se determinado endereço possui conta.

---

# 12. AJUDA ADMINISTRATIVA PARA SENHA

Gestão poderá ajudar um usuário acionando/reemitindo o fluxo seguro de definição/redefinição de senha.

Gestão NÃO poderá:

* visualizar senha atual;
* recuperar senha antiga;
* receber senha de outro usuário em texto puro.

Preferir envio de link seguro para o próprio usuário.

---

# 13. INFRAESTRUTURA DE E-MAIL

O primeiro acesso e recuperação de senha exigem envio real de e-mail em produção.

Avaliar a configuração atual.

Não presumir que já existe serviço SMTP/transacional configurado.

Preparar a aplicação para utilizar um provedor de e-mail transacional adequado ao Laravel.

A escolha/configuração final do provedor poderá exigir credenciais fornecidas pelo proprietário do sistema.

Nunca:

* colocar credenciais no Git;
* hardcodar API keys;
* hardcodar senhas SMTP.

Credenciais de produção devem ficar em variáveis de ambiente do Railway.

Caso seja necessária decisão humana sobre provedor/conta/credenciais, registrar claramente como ponto de intervenção.

---

# 14. IDENTIDADE DAS MARCAS

## Cliente

Albuquerque Engenharia em Construção a Seco.

## Tecnologia / fornecedor

MC Inteligência.

## Hierarquia visual

A Albuquerque deve ser a marca principal da experiência do usuário.

A MC Inteligência deve aparecer discretamente como empresa responsável pela tecnologia.

Princípio:

`Albuquerque como marca principal da interface + MC Inteligência discretamente como tecnologia/fornecedor.`

Não criar uma disputa visual 50/50 entre as duas marcas.

---

# 15. DIRETRIZ VISUAL DA ALBUQUERQUE

Ajustar globalmente a interface para seguir a identidade visual da Albuquerque.

O sistema deve permanecer predominantemente LIGHT.

Não reproduzir literalmente as grandes superfícies vermelhas do site institucional.

O vermelho/vinho deve funcionar como assinatura visual da marca.

Proporção visual aproximada:

* 75–80% branco/neutros;
* 15–20% cinzas;
* 5–10% vermelho/vinho institucional.

---

# 16. PALETA DE REFERÊNCIA

Utilizar como referência:

Cor primária / vermelho institucional:

`#9E0128`

Vinho secundário:

`#802036`

Vinho escuro:

`#661F35`

Bordô profundo:

`#520C1F`

Branco:

`#FFFFFF`

Fundo secundário:

`#F7F7F8`

Bordas/divisores:

`#E5E7EB`

Texto principal:

`#202124`

Texto secundário:

`#6B7280`

---

# 17. DESIGN TOKENS

Criar/utilizar definição global centralizada para o tema.

Evitar cores hardcoded espalhadas pelas views/componentes.

Os componentes devem consumir a mesma definição central para:

* primary;
* primary-hover;
* primary-active;
* secondary;
* background;
* surface;
* border;
* text;
* muted text;
* focus;
* estados semânticos.

Preservar compatibilidade com Tailwind e a arquitetura frontend atual.

---

# 18. APLICAÇÃO DAS CORES

## Background geral

Branco ou cinza muito claro.

## Cards

Brancos, bordas discretas e sombra mínima.

## Tabelas

Claras, legíveis, com headers discretos.

## Formulários

Superfícies claras.

## Modais

Brancos/claros.

## Primária `#9E0128`

Utilizar principalmente em:

* botões primários;
* item ativo;
* links importantes;
* seleção;
* indicadores ativos;
* ícones de destaque;
* pequenos elementos institucionais.

## Vinhos escuros

Utilizar em:

* hover;
* active;
* detalhes;
* headers específicos;
* elementos de contraste controlado.

---

# 19. SIDEBAR

Preferencialmente:

* branca;
* cinza extremamente claro;
* visual limpo.

Vermelho/vinho somente para:

* item ativo;
* ícones selecionados;
* indicadores;
* detalhes.

Evitar sidebar inteira vermelha.

---

# 20. TOPBAR

Topbar:

* branca;
* limpa;
* corporativa;
* organizada;
* sem excesso de elementos.

---

# 21. BOTÕES

## Primário

* fundo `#9E0128`;
* texto branco;
* hover em vinho mais escuro.

## Secundário

* fundo branco;
* borda neutra ou vinho;
* texto vinho/escuro.

Evitar arredondamento excessivo.

---

# 22. INPUTS

* fundo branco;
* borda neutra;
* boa legibilidade;
* focus/ring na cor institucional;
* estados de erro acessíveis;
* estados disabled claros.

---

# 23. BADGES E STATUS

Não utilizar vermelho institucional para todos os estados.

Manter cores semânticas apropriadas para:

* sucesso;
* alerta;
* erro;
* informação;
* atraso;
* concluído.

A identidade visual não deve destruir a comunicação semântica.

---

# 24. GRÁFICOS E DASHBOARD

Utilizar vermelho/vinho institucional como série principal quando apropriado.

Utilizar:

* neutros;
* tons auxiliares coerentes;
* cores semânticas

para séries secundárias.

Preservar:

* contraste;
* leitura;
* acessibilidade;
* interpretação rápida.

---

# 25. LINGUAGEM VISUAL

O sistema deve transmitir:

* engenharia;
* construção;
* gestão profissional;
* organização;
* solidez;
* precisão;
* modernidade;
* aparência corporativa;
* aparência técnica;
* premium sem excessos.

---

# 26. EVITAR

Evitar:

* grandes áreas inteiramente vermelhas;
* excesso de gradientes;
* sombras pesadas;
* interfaces escuras;
* excesso de cores;
* aparência genérica de template SaaS;
* componentes excessivamente arredondados;
* aparência infantil;
* efeitos visuais gratuitos;
* excesso de animação;
* excesso de decoração.

---

# 27. CONSISTÊNCIA GLOBAL

Aplicar identidade em:

* autenticação;
* recuperação de senha;
* definição inicial de senha;
* sidebar;
* topbar;
* dashboards;
* cards;
* Kanban;
* tabelas;
* filtros;
* formulários;
* modais;
* menus;
* botões;
* paginação;
* badges;
* notificações;
* gráficos;
* histórico;
* detalhes do pedido;
* área de usuários;
* estados hover;
* estados focus;
* estados active;
* estados disabled;
* telas vazias;
* mensagens de erro/sucesso.

Não reformular apenas a home.

---

# 28. NOVA TELA DE LOGIN

A tela atual deve ser reformulada visualmente.

Substituir o título atual:

`Sistema de Solicitações e Compras`

por:

`Albuquerque Engenharia`

Adicionar identidade visual Albuquerque.

Estrutura conceitual:

* logo Albuquerque;
* nome “Albuquerque Engenharia”;
* card de login;
* título “Entrar no sistema”;
* e-mail;
* senha;
* “Esqueci minha senha”;
* botão “Entrar”;
* assinatura discreta MC Inteligência.

O botão azul atual deve deixar de existir.

Utilizar vermelho institucional como ação primária.

---

# 29. ASSINATURA MC INTELIGÊNCIA

Onde houver a assinatura:

`Tecnologia por MC Inteligência`

deverá existir também a logo oficial da MC Inteligência.

A logo deve ficar:

* alinhada com o texto;
* proporcional;
* pequena;
* delicada;
* visualmente discreta;
* sem competir com a marca Albuquerque.

Exemplo conceitual:

`[logo MC] Tecnologia por MC Inteligência`

ou composição equivalente visualmente melhor.

A prioridade visual continua sendo Albuquerque.

---

# 30. LOGO ALBUQUERQUE

Adicionar a logo oficial Albuquerque à experiência de autenticação.

A logo deve:

* manter proporção original;
* não ser distorcida;
* possuir apresentação com bordas arredondadas quando visualmente apropriado;
* respeitar área de respiro;
* possuir tamanho responsivo;
* não dominar excessivamente a tela.

Não redesenhar a logo.

Não gerar uma nova logo por IA.

Não buscar logo alternativa na internet.

Usar exclusivamente o asset fornecido pelo proprietário.

---

# 31. LOGO MC INTELIGÊNCIA

Utilizar exclusivamente a logo fornecida.

Não redesenhar.

Não gerar nova versão.

Não buscar alternativa na internet.

Aplicar de maneira discreta ao lado da identificação da MC Inteligência.

---

# 32. LOCALIZAÇÃO DOS ASSETS ORIGINAIS

Os arquivos originais estão atualmente no Windows:

Albuquerque:

`C:\Users\leool\OneDrive\Documentos\Projetos\MC-Inteligência_Albuquerque - Sistema de Solicitações e Compras\logo_Albuquerque.png`

MC Inteligência:

`C:\Users\leool\OneDrive\Documentos\Projetos\MC-Inteligência_Albuquerque - Sistema de Solicitações e Compras\logo_MC.png`

O projeto Laravel oficial está no WSL:

`/home/leonardool/sistema_obra_mc`

Quando necessário, localizar os arquivos Windows através do filesystem montado no WSL, equivalente a `/mnt/c/...`.

Antes de copiar:

* confirmar que os arquivos existem;
* confirmar nomes;
* inspecionar dimensões/formato;
* não sobrescrever os originais.

Copiar os assets necessários para localização adequada dentro do projeto Laravel.

Os assets utilizados em produção deverão fazer parte do código versionado, quando apropriado.

O Railway não deve depender do caminho Windows.

---

# 33. LOGOS SOMENTE NA ÚLTIMA ETAPA

REGRA DE EXECUÇÃO:

A aplicação efetiva das logos deverá acontecer somente na ÚLTIMA ETAPA da implementação desta evolução.

Antes disso:

* implementar funcionalidades;
* implementar administração de usuários;
* implementar autenticação adicional;
* implementar recuperação de senha;
* preparar infraestrutura de e-mail;
* implementar design system;
* aplicar identidade visual;
* validar telas;
* validar regressões.

Somente depois aplicar:

* `logo_Albuquerque.png`;
* `logo_MC.png`.

Objetivo:

reduzir mistura entre alterações funcionais, redesign e assets finais.

---

# 34. RESPONSIVIDADE

Todas as alterações devem funcionar em:

* desktop;
* notebook;
* tablet;
* mobile.

Especial atenção para:

* login;
* sidebar;
* tabelas;
* Kanban;
* dashboard;
* administração de usuários;
* formulários;
* modais.

Não quebrar a responsividade existente.

---

# 35. ACESSIBILIDADE E LEGIBILIDADE

Preservar:

* contraste adequado;
* labels;
* focus visível;
* navegação coerente;
* mensagens de erro compreensíveis;
* tamanho legível;
* hierarquia visual clara.

Não sacrificar acessibilidade para reproduzir identidade visual.

---

# 36. DOMÍNIO

A aplicação será entregue em domínio pertencente à MC Inteligência.

Não utilizar domínio da Albuquerque como requisito.

O domínio Railway atual continuará sendo o endpoint técnico até a configuração definitiva.

Planejar posteriormente um subdomínio da MC Inteligência dedicado ao cliente.

Exemplo conceitual:

`albuquerque.<dominio-mc>`

O domínio exato será definido posteriormente.

Quando definido:

* adicionar custom domain no Railway;
* configurar DNS;
* validar certificado HTTPS;
* atualizar `APP_URL`;
* validar redirects;
* validar cookies;
* validar assets;
* validar Livewire;
* validar recuperação de senha;
* validar links enviados por e-mail.

Não inventar domínio.

---

# 37. BRANDING NO DOMÍNIO

Embora o domínio pertença à MC Inteligência, a interface deve continuar prioritariamente Albuquerque.

O domínio representa hospedagem/operação tecnológica.

A experiência visual representa o cliente Albuquerque.

---

# 38. GIT COMO FONTE DE VERDADE

O GitHub deve continuar sendo a fonte de verdade do código.

Repositório:

`lobernardo/sistema_obra_mc`

Nenhuma alteração permanente deve existir apenas:

* no container Railway;
* em uma máquina local;
* em sessão temporária do Claude;
* no filesystem de produção.

Alterações de aplicação devem ser versionadas.

---

# 39. FLUXO DE DESENVOLVIMENTO FUTURO

O sistema será mantido futuramente utilizando:

* Claude Code;
* Claude Cowork;
* Git;
* GitHub;
* Railway.

Fluxo esperado:

GitHub
↓
máquina local / workspace
↓
alteração
↓
testes
↓
revisão
↓
commit
↓
push
↓
deploy Railway
↓
validação em produção

Antes de trabalhar em outra máquina/workspace, sincronizar o código correto a partir do GitHub.

Não tratar o Railway como editor ou fonte primária do código.

---

# 40. CONTROLE DE VERSÕES

Cada evolução deve possuir commits descritivos.

Evitar:

* alterações diretamente em produção;
* código não versionado;
* force push;
* reescrita destrutiva de histórico;
* secrets em commits.

Antes de novos trabalhos:

* confirmar branch;
* atualizar referência remota;
* verificar `git status`;
* garantir working tree conhecida.

---

# 41. RAILWAY

Preservar o projeto Railway existente:

`sistema-obra-mc`

Não criar novo projeto Railway para esta evolução.

Não modificar outros projetos Railway.

Continuar utilizando:

* serviço Laravel existente;
* PostgreSQL existente;
* environment production existente.

Deploys deverão ocorrer a partir do código versionado no GitHub.

---

# 42. BANCO DE PRODUÇÃO

Nunca utilizar em produção:

`migrate:fresh`

Não apagar banco para implementar funcionalidade.

Novas estruturas necessárias para usuários/auth devem ser implementadas por migrations incrementais e seguras.

Preservar dados existentes.

---

# 43. TESTES OBRIGATÓRIOS

Manter a suíte existente verde.

Adicionar testes para as novas funcionalidades.

Cobrir no mínimo:

* Gestão acessa administração de usuários;
* Obra não acessa administração de usuários;
* Suprimentos não acessa administração de usuários;
* criação de usuário;
* associação de usuário a obra;
* alteração de usuário;
* desativação;
* usuário desativado não autentica;
* histórico não é destruído pela desativação;
* recuperação de senha;
* token inválido;
* token expirado quando aplicável;
* redefinição bem-sucedida;
* login com nova senha;
* proteção contra acesso indevido;
* primeiro acesso/convite;
* permissões existentes continuam funcionando.

---

# 44. REGRESSÃO

Depois das alterações, revalidar fluxos críticos existentes:

* login;
* Obra;
* criação de pedido;
* acompanhamento;
* Suprimentos;
* Kanban;
* mudança de status;
* responsável;
* prioridade;
* previsão;
* histórico;
* Gestão;
* dashboard;
* autorização;
* isolamento entre obras.

---

# 45. BUILD

Executar build de produção.

No mínimo:

`npm run build`

O build deve terminar sem falhas.

Não depender de arquivos locais não versionados para produção, exceto secrets fornecidos via environment.

---

# 46. QUALIDADE VISUAL

Após implementação visual, realizar revisão sistemática das telas.

Verificar:

* consistência de cores;
* spacing;
* tipografia;
* alinhamento;
* bordas;
* radius;
* sombras;
* estados;
* responsividade;
* logos;
* hierarquia visual;
* legibilidade.

Não considerar o redesign concluído apenas porque as classes Tailwind foram alteradas.

---

# 47. ORDEM RECOMENDADA DE IMPLEMENTAÇÃO

Planejar a execução aproximadamente nesta ordem:

## Etapa 1 — Auditoria

* inspecionar autenticação existente;
* usuários/profiles;
* roles;
* Policies/Gates;
* obras;
* relacionamento obra-profile;
* mail config;
* views/layouts;
* Tailwind;
* componentes;
* testes existentes.

## Etapa 2 — Administração de usuários

* autorização;
* migrations necessárias;
* actions/services;
* interface Gestão;
* criação;
* edição;
* associação a obras;
* ativação/desativação.

## Etapa 3 — Primeiro acesso e senha

* convite;
* password reset;
* telas;
* tokens;
* segurança;
* testes.

## Etapa 4 — E-mail

* abstração/configuração;
* templates;
* variáveis necessárias;
* comportamento local/test;
* preparação Railway;
* identificar intervenção humana necessária para credenciais.

## Etapa 5 — Design system

* tokens;
* cores;
* tipografia;
* buttons;
* inputs;
* cards;
* tables;
* badges;
* layouts.

## Etapa 6 — Aplicação global da identidade

* login;
* sidebar;
* topbar;
* Obra;
* Suprimentos;
* Gestão;
* Kanban;
* dashboard;
* histórico;
* administração de usuários;
* password reset.

## Etapa 7 — Responsividade e refinamento

* desktop;
* tablet;
* mobile;
* acessibilidade;
* consistência.

## Etapa 8 — Testes/regressão

* unit/feature;
* browser/E2E;
* build;
* regressão dos fluxos existentes.

## Etapa 9 — LOGOS — ÚLTIMA ETAPA

Somente agora:

* localizar os dois arquivos originais;
* copiar para o projeto;
* aplicar logo Albuquerque;
* aplicar logo MC;
* revisar proporções;
* revisar alinhamentos;
* revisar responsividade;
* versionar assets;
* validar build.

## Etapa 10 — Preparação para deploy

* revisar diff;
* verificar secrets;
* testes finais;
* build final;
* commit(s);
* push;
* deploy Railway;
* migrations incrementais;
* configurar e-mail/variáveis quando credenciais estiverem disponíveis;
* validar produção.

## Etapa 11 — Domínio

Quando o domínio MC definitivo for informado:

* Railway custom domain;
* DNS;
* SSL;
* APP_URL;
* links de e-mail;
* cookies;
* redirects;
* Livewire;
* validação final.

---

# 48. NÃO FAZER DURANTE O PLANEJAMENTO

O objetivo inicial é produzir um PLANO DE IMPLEMENTAÇÃO.

Não implementar ainda durante a etapa de planejamento.

Não alterar código.

Não alterar produção.

Não alterar banco.

Não criar migrations.

Não modificar Railway.

Não copiar logos ainda.

Primeiro produzir plano detalhado, fases, dependências, riscos e critérios de aceite.

---

# 49. CRITÉRIOS DE ACEITE — ADMINISTRAÇÃO

Considerar concluído quando:

* Gestão possui área Usuários;
* Gestão cria usuário;
* Gestão edita usuário;
* Gestão define perfil;
* Gestão associa obras;
* Gestão ativa/desativa;
* usuário desativado não entra;
* histórico é preservado;
* Obra não administra usuários;
* Suprimentos não administra usuários.

---

# 50. CRITÉRIOS DE ACEITE — AUTENTICAÇÃO

Considerar concluído quando:

* novo usuário pode receber convite;
* usuário define própria senha;
* login possui “Esqueci minha senha”;
* recuperação envia link;
* token funciona;
* nova senha funciona;
* senha antiga deixa de funcionar;
* nenhuma senha é exposta;
* usuário desativado não recupera acesso indevidamente.

---

# 51. CRITÉRIOS DE ACEITE — VISUAL

Considerar concluído quando:

* interface é predominantemente light;
* identidade Albuquerque é reconhecível;
* vermelho não domina grandes superfícies;
* azul genérico anterior não é mais a identidade principal;
* componentes usam tokens globais;
* sidebar/topbar estão coerentes;
* dashboards estão coerentes;
* Kanban está coerente;
* formulários estão coerentes;
* autenticação está coerente;
* responsividade está preservada;
* estados semânticos continuam distinguíveis.

---

# 52. CRITÉRIOS DE ACEITE — BRANDING

Considerar concluído quando:

* tela de login mostra “Albuquerque Engenharia”;
* logo Albuquerque está corretamente aplicada;
* logo não está distorcida;
* MC Inteligência aparece discretamente;
* logo MC está alinhada delicadamente com sua assinatura;
* MC não compete visualmente com Albuquerque;
* logos utilizadas são exatamente os arquivos fornecidos;
* aplicação Railway não depende dos caminhos Windows originais.

---

# 53. CRITÉRIOS DE ACEITE — REGRESSÃO

Nenhuma funcionalidade previamente validada pode deixar de funcionar.

Suíte existente deve continuar verde.

Novos testes devem estar verdes.

Build deve estar verde.

E2E crítico deve estar verde.

Produção só deve receber a evolução depois dos gates de qualidade.

---

# 54. ENTREGÁVEL ESPERADO DO BC HARNESS PLAN

A partir deste documento, gerar plano de implementação completo contendo:

* fases;
* subfases;
* tarefas;
* dependências;
* arquivos/áreas provavelmente afetados;
* migrations necessárias;
* autorização;
* arquitetura da administração de usuários;
* arquitetura de convite/password reset;
* infraestrutura de e-mail;
* design system;
* aplicação global da identidade;
* testes;
* E2E;
* tratamento dos assets;
* deploy;
* domínio;
* riscos;
* critérios de aceite por fase.

O plano deve preservar 100% das informações deste documento.

Não simplificar omitindo requisitos.

Não transformar requisitos futuros/opcionais em implementação obrigatória desta versão.

Não alterar código durante a geração do plano.
