# Backlog de tarefas e sprints da Intranet FCECON

**Data:** 14 de outubro de 2026  
**Status:** Sprint 1 implementada e verificada tecnicamente; T-01 a T-05 concluídas no escopo local, com pendências externas da TI registradas no inventário. Demais tarefas em **Backlog**.  
**Equipe de execução:** um desenvolvedor com apoio do Codex.  
**Referências:** [arquitetura e requisitos](arquitetura.md) e [modelagem dos fluxos](modelagem.md).

## 1. Objetivo, capacidade e marcos

Organizar a implementação da Intranet Laravel + PostgreSQL + Docker em seis sprints de duas semanas. A primeira demonstração à TI acontece ao final da Sprint 2, com login, perfis, navegação e download no Docker local.

As 12 semanas são uma hipótese de organização, não uma previsão baseada em velocidade medida ou um prazo garantido. Datas de calendário serão definidas após a confirmação do início. A quantidade de tarefas não representa capacidade igual entre sprints: complexidade, bloqueios e resultados observados devem orientar o replanejamento.

Este documento acompanha o planejamento e a execução. A fundação local está registrada em [Sprint 1](sprint-1.md); não foram criadas issues nem executada implantação na VM. A autorização e o aceite do corte são condições de uma tarefa futura, não ações realizadas por este documento.

| Sprint | Horizonte relativo | Objetivo | Marco e condição de saída | Tarefas |
|---|---|---|---|---|
| 1 — Fundação | Semanas 1–2 | Estrutura técnica e ambiente local. | Laravel inicia no Docker; PostgreSQL e storage de teste isolados; esquema e auditoria básica testáveis. | T-01 a T-05 |
| 2 — Acesso e consulta | Semanas 3–4 | Primeiro incremento funcional. | TI avalia consulta com login, perfis e download; acessos indevidos não entregam documentos. | T-06 a T-10 |
| 3 — Administração | Semanas 5–6 | Publicação e manutenção do acervo. | Admin gerencia usuários, pastas e upload; autoria e auditoria preservadas; resultado parcial explícito. | T-11 a T-15 |
| 4 — Ciclo documental | Semanas 7–8 | Lixeira, ZIP e recuperação de interrupções. | Remoção/recuperação e scheduler passam por testes de falha; descendentes removidos são inacessíveis. | T-16 a T-20 |
| 5 — Migração e recuperação | Semanas 9–10 | Ensaio da migração e do retorno. | Importação repetível e backup restaurado passam por conferência de relações, contagens e hashes. | T-21 a T-25 |
| 6 — Entrega operacional | Semanas 11–12 | Homologação e pacote para a VM Linux. | Imagens importadas sem registry, configuração operacional validada e corte condicionado ao aceite da TI. | T-26 a T-30 |

## 2. Regras do backlog e acompanhamento

### Papéis e estimativas

- **Dev + Codex:** o desenvolvedor responde pela execução e revisão técnica, usando o Codex como apoio. Isso não equivale a dois desenvolvedores independentes.
- **TI:** fornece inventário, acessos, parâmetros de infraestrutura e validações institucionais; acompanha os marcos e aceita a liberação.
- **P0:** fundação, autorização, integridade, recuperação ou condição necessária para liberação.
- **P1:** funcionalidade ou acabamento obrigatório da versão, que pode ser sequenciado após os pré-requisitos P0. P1 não significa fora do escopo.
- **P/M/G:** estimativa relativa de esforço e incerteza: pequena, média ou grande. Não são pontos de velocidade nem equivalências em horas/dias. Refinar tarefas G antes de assumir compromisso na sprint; subtarefas mantêm o ID pai e seus requisitos.

### Estados e critérios

O quadro usa **Backlog → Pronta → Em andamento → Em revisão → Concluída**. Bloqueio é uma sinalização separada, com motivo, impacto, responsável pela resolução e próxima ação; não transforma uma tarefa incompleta em entrega.

**Pronta:** objetivo, entradas, dependências, critérios e evidência estão claros; dependências foram concluídas ou a parte independente foi formalmente separada. Acessos e dados necessários estão disponíveis. Pendências de TI são registradas, não substituídas por suposições silenciosas.

**Concluída:** resultado entregue, testes aplicáveis aprovados, revisão registrada, documentação atualizada e evidência de aceite disponível. Demonstração parcial, código escrito ou container iniciado isoladamente não bastam para concluir o caso de uso.

Testar cada comportamento durante sua implementação. Não reservar autenticação, autorização, integridade ou recuperação apenas para a Sprint 6. Critérios CA amplos podem ter validação parcial antecipada; a matriz da seção 4 indica onde completar sua cobertura. Uma tarefa não é considerada responsável por partes de um CA fora de seu escopo específico.

Planejar e revisar cada sprint com a TI. Ao final, demonstrar o incremento, registrar resultado de aceite, revisar bloqueios e ajustar a distribuição usando capacidade observada. Tarefas não concluídas retornam ao planejamento; não se aumenta artificialmente a capacidade para manter as datas iniciais.

## 3. Tarefas por sprint

### Sprint 1 — Fundação

#### T-01 — Inventariar infraestrutura e legado

**Responsável:** Dev + Codex, com fornecimento e validação da TI.  
**Prioridade:** P0; **estimativa:** M; **status:** Concluída (escopo local; evidência em [Sprint 1](sprint-1.md)).  
**Dependências:** nenhuma.

**Descrição:** inventariar VM Linux, CPU, recursos, Docker/Compose, domínio e certificados; quantidade/tamanho do acervo, permissões, horários e condições de acesso ao SQLite. Definir com a TI limites operacionais e requisitos de backup/recuperação. Separar parâmetros essenciais para desenvolvimento dos necessários ao corte.

**Requisitos:** RNF-06, RNF-07, RNF-08, RNF-10.  
**Aceite:** inventário validado ou lacunas identificadas como bloqueios das tarefas afetadas; pré-condições de CA-14, CA-15, CA-17 e CA-19 registradas, sem declarar esses testes executados.  
**Evidência:** inventário, parâmetros confirmados e lista de pendências com responsáveis da TI.

#### T-02 — Estruturar Laravel e build de assets

**Responsável:** Dev + Codex.  
**Prioridade:** P0; **estimativa:** M; **status:** Concluída (escopo local; evidência em [Sprint 1](sprint-1.md)).  
**Dependências:** nenhuma; usar a arquitetura aprovada como entrada.

**Descrição:** preparar Laravel 13/PHP 8.4 com separação de controllers, serviços, policies, models e Blade. Organizar Composer, assets locais, configuração de exemplo sem segredos e preservação do código/dados legados. Não substituir banco ou documentos do sistema existente.

**Requisitos:** RNF-05, RNF-07, RF-13.  
**Aceite:** estrutura e build reproduzíveis; assets locais e configurações de exemplo sem dados reais; preparar base para CA-15 e CA-16.  
**Evidência:** revisão da estrutura, instruções de preparação e registro de build dos assets.

#### T-03 — Implementar esquema PostgreSQL e migrations

**Responsável:** Dev + Codex.  
**Prioridade:** P0; **estimativa:** G; **status:** Concluída (escopo local; evidência em [Sprint 1](sprint-1.md)).  
**Dependências:** T-02, T-04.

**Descrição:** detalhar e implementar o esquema de usuários, itens e auditoria conforme a modelagem lógica. Definir constraints, unicidade incluindo a raiz, chaves estáveis e referências opcionais/snapshots históricos. Testar PostgreSQL real isolado; não importar produção nesta tarefa.

**Requisitos:** RF-03, RF-11, RF-12, RNF-04, RNF-05, RNF-09.  
**Aceite:** migrations executáveis em banco vazio e constraints verificadas; excluir usuário ou item não apaga o histórico indevidamente; validar fundamentos de CA-03, CA-08, CA-11 e CA-12.  
**Evidência:** migrations versionadas, modelo físico documentado e resultados de testes de integridade.

#### T-04 — Preparar Docker local, rede e volumes

**Responsável:** Dev + Codex; TI apoia pré-requisitos do computador.  
**Prioridade:** P0; **estimativa:** M; **status:** Concluída (escopo local; evidência em [Sprint 1](sprint-1.md)).  
**Dependências:** T-02.

**Descrição:** criar Dockerfile/base Compose e configuração de desenvolvimento para web, app e db, com volumes privados e isolados. Preparar o serviço scheduler para ativação funcional em T-20. Permitir edição local do código, mantendo dependências acessíveis e segredos fora das imagens.

**Requisitos:** RNF-02, RNF-05, RNF-07, RNF-10.  
**Aceite:** CA-16; Laravel inicia, resolve `db` internamente e grava no storage privado; dados de teste não compartilham volumes de produção.  
**Evidência:** comandos documentados, configuração revisada e registro de inicialização/recriação local.

#### T-05 — Preparar auditoria e base de testes

**Responsável:** Dev + Codex.  
**Prioridade:** P0; **estimativa:** M; **status:** Concluída (escopo local; evidência em [Sprint 1](sprint-1.md)).  
**Dependências:** T-03, T-04.

**Descrição:** implementar registro de eventos de negócio sem segredos e infraestrutura de testes HTTP, PostgreSQL e filesystem temporário. Criar fixtures sintéticas de usuários, pastas e documentos para a demonstração, sem depender do importador de produção.

**Requisitos:** RF-12, RNF-05, RNF-09.  
**Aceite:** eventos conservam ator/alvo textual e não registram senhas/conteúdo; testes podem ser repetidos sem alterar dados reais; validar a base de CA-12.  
**Evidência:** suíte inicial executada, fixtures e exemplos revisados de eventos.

**Ordem técnica sugerida:** T-01 e T-02 são independentes; executar T-04 antes de T-03 e T-05. O inventário continua acompanhado mesmo se não bloquear a estrutura local.

### Sprint 2 — Acesso e consulta

#### T-06 — Implementar login, logout e troca de senha

**Responsável:** Dev + Codex.  
**Prioridade:** P0; **estimativa:** M; **status:** Backlog.  
**Dependências:** T-05.

**Descrição:** implementar autenticação por sessão, último acesso, regeneração/invalidação de sessão e troca de senha com comprovação da senha atual. Preservar o formulário anterior ao login; não criar recuperação por e-mail ou senhas padrão compartilhadas.

**Requisitos:** RF-01, RF-02, RNF-03, RNF-09.  
**Aceite:** CA-01 e CA-02 nas jornadas de autenticação; entradas inválidas não criam sessão nem alteram hash.  
**Evidência:** testes HTTP, verificação de hash e demonstração dos dois perfis.

#### T-07 — Implementar autorização e proteções HTTP

**Responsável:** Dev + Codex.  
**Prioridade:** P0; **estimativa:** M; **status:** Backlog.  
**Dependências:** T-06.

**Descrição:** implementar policies por ação/perfil, CSRF nos envios e limitação de tentativas de autenticação. Recusar requisições administrativas diretas de `user`, invalidar acesso após logout e limitar GET a consulta/leitura, além de auditoria.

**Requisitos:** RNF-01, RNF-03, RF-01, RF-02.  
**Aceite:** cobertura de autorização de CA-01 e proteções HTTP de CA-13; novos endpoints reutilizam as policies nas sprints seguintes.  
**Evidência:** testes de sessão ausente/expirada, perfil sem permissão, token inválido e tentativas de login.

#### T-08 — Implementar navegação, filtro e metadados

**Responsável:** Dev + Codex.  
**Prioridade:** P0; **estimativa:** M; **status:** Backlog.  
**Dependências:** T-03, T-07.

**Descrição:** listar raiz/pastas por ID, breadcrumbs, pastas antes de arquivos, ordenação e filtro de nome na pasta atual. Exibir autoria, tamanho e datas disponíveis, excluindo itens com ancestral removido. Usar fixtures para consultas iniciais.

**Requisitos:** RF-04, RF-11, RNF-01, RNF-04.  
**Aceite:** CA-04 e leitura de metadados de CA-11; autoria ausente permanece ausente.  
**Evidência:** testes com hierarquia ativa/removida e demonstração da listagem/filtro.

#### T-09 — Implementar visualização e download privados

**Responsável:** Dev + Codex.  
**Prioridade:** P0; **estimativa:** M; **status:** Backlog.  
**Dependências:** T-08, T-05.

**Descrição:** criar rotas de streaming por ID com autorização do item/ancestrais, formatos inline seguros, attachment para os demais e miniaturas autenticadas. Proteger o storage contra URL direta e registrar início da leitura, sem afirmar entrega completa ao navegador.

**Requisitos:** RF-05, RF-06, RNF-01, RNF-02, RNF-08, RNF-09.  
**Aceite:** CA-05 e download individual de CA-06; arquivos removidos/descendentes e visitantes não recebem bytes. ZIP será tratado em T-16.  
**Evidência:** testes de leitura, integridade dos bytes, cabeçalhos e acesso direto ao storage.

#### T-10 — Validar e demonstrar consulta à TI

**Responsável:** Dev + Codex; TI valida o incremento.  
**Prioridade:** P0; **estimativa:** P; **status:** Backlog.  
**Dependências:** T-06, T-07, T-08, T-09.

**Descrição:** executar roteiro de login, senha, consulta, filtro, visualização e download para os dois perfis no Docker local. Usar dados fictícios e registrar observações da TI. A demonstração não inclui administração ainda não implementada.

**Requisitos:** RF-01, RF-02, RF-04, RF-05, RF-06, RF-11, RNF-01, RNF-02, RNF-10.  
**Aceite:** primeiro marco revisado pela TI; CA-01, CA-02, CA-04, CA-05, CA-11, CA-16 e download individual de CA-06 demonstrados no escopo do incremento.  
**Evidência:** roteiro executado, resultados e registro de aceite/pendências, sem marcar funcionalidades futuras como concluídas.

### Sprint 3 — Administração

#### T-11 — Implementar gestão de usuários

**Responsável:** Dev + Codex.  
**Prioridade:** P0; **estimativa:** M; **status:** Backlog.  
**Dependências:** T-07, T-05.

**Descrição:** cadastro, listagem e exclusão por admin; login único, perfis válidos, bloqueio de autoexclusão e preservação de autoria/histórico após excluir outra conta. Cada ação administrativa valida permissões no servidor.

**Requisitos:** RF-03, RNF-01, RNF-09.  
**Aceite:** CA-03; `user` não administra contas, perfil desconhecido/login repetido são recusados e exclusão não remove documentos/logs.  
**Evidência:** testes de cadastro/exclusão e demonstração administrativa.

#### T-12 — Implementar pastas e renomeação

**Responsável:** Dev + Codex.  
**Prioridade:** P0; **estimativa:** M; **status:** Backlog.  
**Dependências:** T-08, T-05.

**Descrição:** criar pastas lógicas e renomear arquivos/pastas preservando IDs, chaves e extensão. Validar nomes, colisões, parent ativo e ausência de ciclos; transacionar metadados e auditoria.

**Requisitos:** RF-08, RF-11, RNF-01, RNF-04.  
**Aceite:** CA-08 e autoria/identidade de CA-11; renomear pasta não perde filhos nem movimenta bytes.  
**Evidência:** testes de integridade da hierarquia, colisões e preservação das chaves.

#### T-13 — Implementar upload múltiplo consistente

**Responsável:** Dev + Codex.  
**Prioridade:** P0; **estimativa:** G; **status:** Backlog.  
**Dependências:** T-12, T-09.

**Descrição:** multipart, staging privado, validações de arquivo, reservas concorrentes de nomes e confirmação por arquivo com autoria/auditoria. Publicar apenas após conclusão; compensar falhas e preservar pendência quando o resultado do commit estiver ambíguo.

**Requisitos:** RF-07, RF-11, RNF-01, RNF-04, RNF-08, RNF-09.  
**Aceite:** CA-07; lote parcial informa sucessos/falhas, colisões não sobrescrevem bytes e destino removido durante envio é recusado. Validar falhas pertinentes de CA-13.  
**Evidência:** testes HTTP, concorrência em PostgreSQL, erros de disco/banco e verificação dos arquivos resultantes.

#### T-14 — Completar interface administrativa

**Responsável:** Dev + Codex; TI avalia usabilidade.  
**Prioridade:** P1; **estimativa:** M; **status:** Backlog.  
**Dependências:** T-11, T-12, T-13.

**Descrição:** integrar formulários, modais, progresso de upload, mensagens em português, resultados parciais, tema persistente e adaptação a telas menores. Diferenciar transmissão dos bytes de processamento concluído; usar assets locais.

**Requisitos:** RF-07, RF-13, RNF-07.  
**Aceite:** CA-15 na interface disponível e experiência de CA-07; erro não é exibido como sucesso e a interface não depende de CDN.  
**Evidência:** revisão em desktop/tela menor e roteiro de upload válido, parcial e inválido.

#### T-15 — Implementar consulta da auditoria e validar administração

**Responsável:** Dev + Codex; TI valida o incremento.  
**Prioridade:** P0; **estimativa:** M; **status:** Backlog.  
**Dependências:** T-14, T-05, T-10.

**Descrição:** disponibilizar os 50 eventos mais recentes para admin, revisar registros de login, senha, usuários, pastas, upload e leitura. Validar administração integrada e proteção das rotas já implementadas; completar auditoria de lixeira/ZIP quando existirem.

**Requisitos:** RF-03, RF-07, RF-08, RF-11, RF-12, RNF-01, RNF-09.  
**Aceite:** CA-12 para operações disponíveis, CA-03, CA-07, CA-08 e CA-11; registros não expõem senhas ou conteúdo documental.  
**Evidência:** suíte integrada e demonstração com aceite/pendências da TI.

### Sprint 4 — Ciclo documental

#### T-16 — Implementar ZIP autorizado

**Responsável:** Dev + Codex.  
**Prioridade:** P1; **estimativa:** M; **status:** Backlog.  
**Dependências:** T-09, T-15.

**Descrição:** gerar ZIP dos arquivos selecionados da pasta atual, com CSRF e autorização individual, sem pastas recursivas. Usar temporários exclusivos, limites de consumo e limpeza após resposta/interrupção, com auditoria.

**Requisitos:** RF-06, RF-12, RNF-01, RNF-08, RNF-09.  
**Aceite:** completar CA-06; seleção indevida não entrega bytes, downloads simultâneos não compartilham temporários e eventos atendem CA-12.  
**Evidência:** testes de seleção, concorrência, arquivos ZIP e limpeza de temporários.

#### T-17 — Implementar lixeira lógica

**Responsável:** Dev + Codex.  
**Prioridade:** P0; **estimativa:** M; **status:** Backlog.  
**Dependências:** T-12, T-15.

**Descrição:** enviar raízes documentais à lixeira, registrar evento e listar removidos apenas para admin. Manter filhos vinculados e excluir descendentes efetivamente removidos de navegação, leitura e ZIP, sem mover os bytes.

**Requisitos:** RF-09, RF-10, RNF-01, RNF-02, RNF-04.  
**Aceite:** exclusão lógica de CA-09 e bloqueio de leitura de CA-05; testar tentativa direta contra descendente cujo `deleted_at` próprio continua nulo.  
**Evidência:** testes de pasta com filhos e requests de consulta/download/ZIP recusados.

#### T-18 — Implementar restauração para a raiz

**Responsável:** Dev + Codex.  
**Prioridade:** P0; **estimativa:** M; **status:** Backlog.  
**Dependências:** T-17.

**Descrição:** restaurar item removido para a raiz, preservar descendentes e chave física e aplicar sufixo em colisões. Reutilizar nome de lixeira conforme arquitetura e registrar autoria de atualização/evento.

**Requisitos:** RF-10, RF-11, RNF-04.  
**Aceite:** completar restauração de CA-09 e autoria de CA-11; não reconstruir automaticamente a pasta de origem.  
**Evidência:** testes de arquivo/pasta restaurados, colisões na raiz e integridade dos descendentes.

#### T-19 — Implementar remoção permanente e manifestos

**Responsável:** Dev + Codex.  
**Prioridade:** P0; **estimativa:** G; **status:** Backlog.  
**Dependências:** T-18.

**Descrição:** registrar manifesto durável antes de mover bytes para quarentena; remover metadados/auditar em transação; limpar após commit confirmado. Implementar esvaziamento por raiz e resultados parciais. Compensar falhas pré-commit e preservar pendências pós-commit/ambíguas.

**Requisitos:** RF-10, RF-12, RNF-04, RNF-09.  
**Aceite:** caminho normal e falhas síncronas de CA-10; não apagar bytes irreversivelmente quando o resultado SQL estiver inconclusivo. Recuperação após reinício será concluída em T-20.  
**Evidência:** testes por fase de falha, manifestos de teste e conferência de banco/quarentena.

#### T-20 — Implementar scheduler e reconciliação

**Responsável:** Dev + Codex.  
**Prioridade:** P0; **estimativa:** G; **status:** Backlog.  
**Dependências:** T-19, T-16, T-13, T-04.

**Descrição:** ativar scheduler único na imagem app com banco/storage compartilhados; reconciliar staging, ZIP e manifestos interrompidos. Consultar o estado confirmado antes de limpar/restaurar bytes e tornar a recuperação repetível. Registrar última execução e falhas.

**Requisitos:** RF-10, RF-12, RNF-04, RNF-08, RNF-09, RNF-10.  
**Aceite:** completar CA-10 e casos de interrupção/repetição de CA-13; validar scheduler de CA-19 sem declarar a homologação completa da VM.  
**Evidência:** testes de crash/reinício e idempotência, logs da rotina e demonstração de recuperação à TI.

### Sprint 5 — Migração e recuperação

#### T-21 — Importar usuários e validar hashes

**Responsável:** Dev + Codex; TI fornece cópia autorizada do legado.  
**Prioridade:** P0; **estimativa:** M; **status:** Backlog.  
**Dependências:** T-01, T-03, T-06.

**Descrição:** importar cópia isolada dos usuários, mapear IDs, perfis e horários e validar autenticação com hashes compatíveis. Reportar hashes/perfis inválidos para tratamento explícito; não criar senha compartilhada para contornar falhas.

**Requisitos:** RF-01, RF-02, RF-03, RNF-03, RNF-06.  
**Aceite:** parte de usuários de CA-14 e regressão de CA-01/CA-02; nenhum teste altera o banco de produção.  
**Evidência:** mapeamento de IDs, relatório de importação e testes de autenticação com massa controlada.

#### T-22 — Importar hierarquia, documentos e lixeira

**Responsável:** Dev + Codex; TI disponibiliza cópia do acervo.  
**Prioridade:** P0; **estimativa:** G; **status:** Backlog.  
**Dependências:** T-21, T-20, T-13.

**Descrição:** importar hierarquia do disco e metadados por caminho, gerar chaves privadas e preservar nomes/estado de lixeira. Manter autoria ausente ou não resolvida sem atribuição fictícia; registrar conflitos e arquivos/metadados órfãos em relatório.

**Requisitos:** RF-04, RF-05, RF-10, RF-11, RNF-02, RNF-04, RNF-06.  
**Aceite:** parcela documental de CA-14 e regressão de CA-05/CA-09/CA-11; nenhum arquivo de origem é removido durante o ensaio.  
**Evidência:** relatório de importação, hierarquia mapeada e manifesto de caminhos/tamanhos/hashes.

#### T-23 — Importar auditoria e horários históricos

**Responsável:** Dev + Codex; TI confirma a interpretação temporal do legado.  
**Prioridade:** P0; **estimativa:** M; **status:** Backlog.  
**Dependências:** T-21, T-05.

**Descrição:** importar logs preservando textos e nomes conhecidos; mapear ator quando seguro, manter vínculo nulo nos demais casos e registrar ambiguidade de horários/resultado. Não inventar precisão temporal ou resultado ausente.

**Requisitos:** RF-12, RNF-06, RNF-09.  
**Aceite:** histórico atende CA-12 e a parcela de logs de CA-14; excluir contas/itens não torna os eventos sem significado.  
**Evidência:** comparação com cópia de origem e relatório de conversões/ambiguidades validado pela TI.

#### T-24 — Validar importação repetível e integridade

**Responsável:** Dev + Codex; TI valida a reconciliação.  
**Prioridade:** P0; **estimativa:** G; **status:** Backlog.  
**Dependências:** T-22, T-23.

**Descrição:** repetir importações em ambiente de ensaio usando mapeamentos persistentes; conferir contagens, hierarquia, usuários, metadados, logs, tamanhos e hashes. Classificar divergências e resolver as que impedem o corte, sem duplicar registros.

**Requisitos:** RF-11, RF-12, RNF-04, RNF-06.  
**Aceite:** importação repetida de CA-14 não duplica dados; divergências bloqueantes foram resolvidas e exceções documentadas são validadas pela TI.  
**Evidência:** duas execuções comparadas, manifesto verificado e relatório de reconciliação.

#### T-25 — Ensaiar backup, restauração e retorno

**Responsável:** Dev + Codex; TI define destino e valida recuperação.  
**Prioridade:** P0; **estimativa:** G; **status:** Backlog.  
**Dependências:** T-24, T-20.

**Descrição:** automatizar backup coordenado de PostgreSQL e storage, com gravações/scheduler suspensos, e restaurar em ambiente separado. Ensaiar falha antes/depois do corte e preservação de novas gravações; registrar configuração e APP_KEY necessárias à recuperação.

**Requisitos:** RNF-04, RNF-06, RNF-10.  
**Aceite:** completar CA-14 no ensaio e cenários de retorno de CA-19; banco e bytes restaurados são coerentes, e o procedimento não descarta operações recentes silenciosamente.  
**Evidência:** backup restaurado, relatório de integridade e roteiro de retorno com resultados e tempos observados.

### Sprint 6 — Entrega operacional

#### T-26 — Finalizar imagens e Compose de produção

**Responsável:** Dev + Codex.  
**Prioridade:** P0; **estimativa:** G; **status:** Backlog.  
**Dependências:** T-20, T-25.

**Descrição:** finalizar build multi-stage app/web do mesmo commit, bases fixadas e Compose de produção sem montagem do código ou build/pull na VM. Excluir segredos/dados das imagens; preservar volumes e APP_KEY; regenerar cache de configuração no ambiente correto.

**Requisitos:** RNF-02, RNF-05, RNF-07, RNF-10.  
**Aceite:** imagens finais testadas sem código montado; CA-18 verifica recriação/atualização preservando dados e sessões. A importação offline completa de CA-17 ocorre em T-28.  
**Evidência:** build reproduzível documentado, inspeção de contexto/imagens e testes de persistência.

#### T-27 — Exportar pacote de imagens e manifesto

**Responsável:** Dev + Codex.  
**Prioridade:** P0; **estimativa:** M; **status:** Backlog.  
**Dependências:** T-26, T-29.

**Descrição:** exportar via `docker image save` app, web e PostgreSQL, com scheduler reutilizando app. Entregar `.tar`, configuração final de produção, manifesto de commit/versão/plataforma/tags/IDs e checksum; configuração secreta e dados seguem separados.

**Requisitos:** RNF-10.  
**Aceite:** parcela de empacotamento de CA-17; nenhuma imagem necessária falta, não há segredos no pacote e a configuração corresponde ao artefato testado.  
**Evidência:** pacote, manifesto, checksum e checklist de conteúdo.

#### T-28 — Homologar importação offline na VM

**Responsável:** Dev + Codex; TI fornece VM Linux de homologação.  
**Prioridade:** P0; **estimativa:** M; **status:** Backlog.  
**Dependências:** T-27, T-29, T-25.

**Descrição:** verificar checksum/plataforma, importar com `docker image load` e executar sem registry/build usando segredos/certificados provisionados. Separar dados da homologação e produção; validar todas as imagens, volumes e principais jornadas na VM.

**Requisitos:** RNF-02, RNF-07, RNF-10.  
**Aceite:** completar CA-17 e confirmar CA-18 na VM; a infraestrutura Docker/Compose é pré-requisito, não download implícito do pacote.  
**Evidência:** registro de importação/execução sem registry, IDs conferidos e roteiro de smoke test.

#### T-29 — Configurar operação, diagnóstico e HTTPS

**Responsável:** Dev + Codex; TI fornece rede, domínio, certificados e parâmetros operacionais.  
**Prioridade:** P0; **estimativa:** M; **status:** Backlog.  
**Dependências:** T-26, T-01.

**Descrição:** configurar entrada HTTPS, portas somente de web, healthchecks de prontidão, permissões de volumes, reinício e logs com rotação. Verificar última execução do scheduler e configurar procedimentos de diagnóstico sem expor detalhes internos aos usuários.

**Requisitos:** RNF-01, RNF-02, RNF-03, RNF-07, RNF-08, RNF-10.  
**Aceite:** operação de CA-19 e homologação dos limites definidos em CA-15; app/db sem portas públicas, debug desabilitado, storage compartilhado acessível aos processos autorizados.  
**Evidência:** checklist de configuração, HTTPS, healthchecks, falhas simuladas e registros da rotina.

#### T-30 — Homologar versão e executar corte após aceite

**Responsável:** Dev + Codex; TI aceita liberação e janela de manutenção.  
**Prioridade:** P0; **estimativa:** G; **status:** Backlog.  
**Dependências:** T-28, T-25, T-29 e todos os critérios aplicáveis sem pendências bloqueantes.

**Descrição:** consolidar resultados de CA-01 a CA-19, documentação de operação e roteiro de corte/retorno. Após aceite registrado da TI, obter backup final, executar migrations explicitamente, importar/conferir o legado quando necessário e liberar somente após verificação. Manter evidências da versão anterior e acompanhamento inicial.

**Requisitos:** RF-01 a RF-13; RNF-01 a RNF-10.  
**Aceite:** todos os CA-01 a CA-19 aprovados no escopo da versão; migração e restauração ensaiadas; corte autorizado, verificações finais de integridade e smoke tests aprovados e suporte operacional definido. Não executar retorno com descarte de novas gravações.
**Evidência:** relatório final, aceite da TI, registro de backup/migrations/corte, manifesto da entrega e manual de operação/retorno.

**Ordem técnica sugerida:** T-26 → T-29 → T-27 → T-28 → T-30. IDs identificam tarefas, não obrigam execução numérica. Configuração operacional final precisa integrar o pacote antes da homologação offline.

## 4. Matriz de cobertura e validação

As tarefas indicadas abaixo cobrem a implementação principal e suas evidências; T-30 consolida a homologação, sem substituir testes das sprints anteriores.

### Requisitos funcionais

| Requisito | Tarefas principais |
|---|---|
| RF-01 | T-06, T-07, T-10, T-21 |
| RF-02 | T-06, T-07, T-10, T-21 |
| RF-03 | T-03, T-11, T-15, T-21 |
| RF-04 | T-08, T-10, T-22 |
| RF-05 | T-09, T-10, T-22 |
| RF-06 | T-09, T-10, T-16 |
| RF-07 | T-13, T-14, T-15 |
| RF-08 | T-12, T-15 |
| RF-09 | T-17 |
| RF-10 | T-17, T-18, T-19, T-20, T-22 |
| RF-11 | T-03, T-08, T-12, T-13, T-18, T-22, T-24 |
| RF-12 | T-05, T-15, T-16, T-19, T-20, T-23 |
| RF-13 | T-02, T-14 |

### Requisitos não funcionais

| Requisito | Tarefas principais |
|---|---|
| RNF-01 | T-07, T-09, T-11, T-13, T-16, T-17 |
| RNF-02 | T-04, T-09, T-17, T-22, T-26, T-28, T-29 |
| RNF-03 | T-06, T-07, T-21, T-29 |
| RNF-04 | T-03, T-12, T-13, T-17, T-18, T-19, T-20, T-24, T-25 |
| RNF-05 | T-02, T-03, T-04, T-05, T-26 |
| RNF-06 | T-01, T-21, T-22, T-23, T-24, T-25 |
| RNF-07 | T-01, T-02, T-04, T-14, T-26, T-28, T-29 |
| RNF-08 | T-01, T-09, T-13, T-16, T-20, T-29 |
| RNF-09 | T-03, T-05, T-06, T-11, T-13, T-15, T-16, T-19, T-20, T-23 |
| RNF-10 | T-04, T-20, T-25, T-26, T-27, T-28, T-29 |

### Critérios de aceitação

| Critério | Onde completar a validação |
|---|---|
| CA-01 | T-06, T-07, T-10; revalidar guards nas novas rotas de T-11 a T-20. |
| CA-02 | T-06, T-10, T-21. |
| CA-03 | T-03, T-11, T-15. |
| CA-04 | T-08, T-10. |
| CA-05 | T-09, T-10, T-17, T-22. |
| CA-06 | T-09 e T-10 para download individual; T-16 completa ZIP/concorrência. |
| CA-07 | T-13, T-14, T-15. |
| CA-08 | T-03, T-12, T-15. |
| CA-09 | T-17, T-18, T-22. |
| CA-10 | T-19 e T-20, cobrindo compensação/reconciliação. |
| CA-11 | T-08, T-12, T-13, T-18, T-22 e T-24. |
| CA-12 | T-05 e T-15 para base/consulta; T-16, T-19, T-20 e T-23 completam operações e histórico. |
| CA-13 | T-07 para HTTP; T-09, T-13, T-19 e T-20 completam traversal e falhas de disco/banco/interrupção. |
| CA-14 | T-21 a T-25 para importação, repetição, integridade e restauração/retorno. |
| CA-15 | T-14 para interface; T-29 para rede/limites; T-30 consolida todas as telas e jornadas. |
| CA-16 | T-04, T-10. |
| CA-17 | T-26 e T-27 preparam; T-28 completa teste sem registry na VM. |
| CA-18 | T-26 e T-28, cobrindo volumes, sessões, APP_KEY e imagens. |
| CA-19 | T-20 para scheduler; T-25 para recuperação; T-29 para operação; T-30 para migrations/corte. |

### Dependências e bloqueios

- T-01 fornece dados externos: registrar responsáveis e bloquear somente tarefas que realmente dependem dos parâmetros pendentes. Sem cópia autorizada do legado, T-21/T-22/T-23 não ficam prontas.
- T-03 usa o ambiente de T-04; T-04 não depende das migrations. Isso evita ciclo na fundação.
- A demonstração T-10 usa fixtures de T-05 e não depende do upload/importador futuros.
- A Sprint 4 depende da base administrativa; recuperação pós-crash só está completa após T-20, não apenas após o caminho normal de T-19.
- Migrations de release e corte exigem T-25 validada. Os volumes não são backup e o `.tar` de imagens não transporta o acervo.
- T-29 precede o pacote T-27; homologação T-28 depende de configuração e artefatos finais coerentes.
- T-30 não pode concluir com critérios bloqueantes, falta de aceite da TI ou incompatibilidade não resolvida no retorno.

Antes de aceitar este planejamento como execução comprometida, revisar a capacidade das tarefas G e decompor os riscos maiores de T-13, T-19, T-20, T-22 e T-25. O refinamento não adiciona funcionalidades fora do escopo nem altera IDs/requisitos sem atualizar a rastreabilidade.

## 5. Premissas e limites

Manter Laravel 13, PHP 8.4, PostgreSQL, Docker/Compose, storage privado e VM Linux. As imagens finais são validadas localmente e distribuídas por arquivo, com configuração e dados separados. O scheduler reutiliza a imagem da aplicação.

Manter os dois perfis, filtro na pasta atual, ZIP sem pastas recursivas, restauração para a raiz e tratamento de colisões. Versionamento documental, aprovação, permissões por setor, API externa, SSO e demais exclusões da arquitetura continuam fora da versão.

Infraestrutura, limites, backup e interpretação temporal ainda exigem inventário/decisões da TI. Não inventar datas, capacidade em pontos, nomes de responsáveis ou confirmação de acesso à VM. Todas as tarefas iniciam em Backlog; nenhum critério de aplicação/implantação foi executado nesta entrega documental.
