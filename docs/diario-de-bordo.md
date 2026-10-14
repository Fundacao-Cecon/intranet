# Diário de bordo — Intranet FCECON

Cada entrada preserva o estado da entrega naquele momento. Os incrementos de acesso/consulta e administração estão descritos na [Sprint 2](sprint-2.md) e [Sprint 3](sprint-3.md).

## 14/10/2026 — Sprint 1: Fundação

**Objetivo:** preparar a base Laravel, PostgreSQL e Docker para iniciar os casos de uso da intranet. Trabalho realizado no computador local, com Docker Desktop/WSL2 e containers Linux. A integração da sprint foi enviada para `dev` no commit `649f314`.

### Execução e decisões

| Etapa | Trabalho realizado | Decisão ou resultado |
|---|---|---|
| Inventário — T-01 | Conferência do Docker, recursos locais e estrutura do legado; leitura apenas de metadados do SQLite | Parâmetros locais confirmados; infraestrutura da VM, domínio, certificados, limites e backup aguardam a TI |
| Aplicação — T-02 | Laravel 13/PHP 8.4 na raiz, página Blade e build Vite com dependências fixadas nos lockfiles | Assets servidos localmente; legado preservado nos caminhos originais |
| Containers — T-04 | Dockerfile, Compose base e complemento explícito de desenvolvimento; web/app/db e ferramentas de build | Só web publica 127.0.0.1:8080; scheduler fica opcional até T-20; configuração e dados separados das imagens |
| Banco — T-03 | Migration de users, items e audit_logs, índices, constraints e proteção da hierarquia | PostgreSQL guarda metadados; storage privado guarda bytes; runtime sem permissão para criar ou alterar tabelas |
| Auditoria e testes — T-05 | Serviço de auditoria com snapshots, contexto restrito, factories e fixture sintéticas | Suíte usa banco, rede e storage próprios; dados reais não entram nos testes |
| Encerramento | Conferência no navegador, falhas controladas, recriação de containers e atualização da documentação | Base local verificada e enviada para dev; implantação e aceite institucional continuam pendentes |

### Ajustes feitos durante a execução

- A suíte inicialmente emitiu avisos pela ausência de um arquivo `.env` no container. O exemplo sem segredos passou a ser montado somente para leitura, mantendo a configuração efetiva nas variáveis de ambiente. A suíte passou sem warnings.
- O ensaio de rollback mostrou que a função de hierarquia poderia não existir em um banco criado antes desse ajuste. A remoção passou a usar `IF EXISTS`; rollback e reaplicação foram conferidos em bancos sintéticos.
- A configuração Nginx passou a resolver o endereço de `app` pelo DNS do Docker, acompanhando a recriação do container.

### Evidências e resultado

| Verificação | Resultado registrado |
|---|---|
| Suíte automatizada | 25 testes e 52 assertions aprovados |
| Build e qualidade | PHP, Composer, assets Vite, validação Composer e Pint aprovados |
| Navegador | Página inicial renderizada no Edge, sem erros JavaScript e sem requisições externas |
| Disponibilidade | `/` e `/up` responderam 200; banco desligado fez `/up` responder 500, com recuperação após reinício |
| Proteção HTTP | SQLite, uploads e storage não foram entregues; acesso a `.env` recusado |
| Isolamento | Inicialização de testes apontada ao banco de desenvolvimento recusada |
| Persistência | Recriação de db/app/web preservou registro sintético, arquivo privado criptografado e APP_KEY; sondas removidas ao terminar |
| Legado | Hashes dos 59 arquivos conferidos e inalterados |

Esses resultados comprovam a fundação técnica local. Não representam conclusão de login, autorização das jornadas, consulta, upload, lixeira, migração do legado ou produção. A descrição técnica e os critérios parcialmente cobertos estão em [Sprint 1](sprint-1.md).

### O que já pode ser visto

Acesse **http://127.0.0.1:8080** no computador de desenvolvimento. A página apresenta a base da Intranet FCECON e um botão para consultar a disponibilidade do ambiente em `/up`. Ainda não há formulário de login nem listagem de documentos.

Se os containers estiverem parados, inicie o Docker Desktop e execute na raiz do projeto:

```powershell
./scripts/dev.ps1 up
```

O endereço funciona neste computador; acesso pela rede e publicação na VM serão configurados na etapa operacional. Para preparar um checkout novo, siga o [guia de desenvolvimento](desenvolvimento.md).

### Próximos passos

A Sprint 2 implementará login/logout/troca de senha, autorização por perfil, navegação, visualização e download, conforme T-06 a T-10 do [planejamento](planejamento.md). A TI precisa fornecer os parâmetros externos listados no [inventário](inventario.md) antes das tarefas de implantação e corte.

Nas próximas sprints, acrescentar entradas datadas com objetivo, decisões, dificuldades resolvidas, testes executados, commit de integração e pendências. Registrar apenas resultados comprovados, sem transformar previsões em entregas.

## 14/10/2026 — Sprint 2: Acesso e consulta

**Objetivo:** entregar a primeira consulta autenticada local, mantendo o legado intacto. T-06 a T-09 implementadas e verificadas; T-10 em revisão, aguardando a demonstração acompanhada e o aceite da TI.

| Frente | Resultado |
|---|---|
| Acesso | Login, último acesso, sessão regenerada, logout e troca de senha pública/autenticada com senha atual |
| Proteção | CSRF obrigatório, rate limit e sessões antigas encerradas após mudança da credencial |
| Permissões | Policies para consulta e ações futuras de admin; bloqueio de autoexclusão preparado |
| Navegação | Raiz/pastas por ID, breadcrumbs, filtro de nomes, pastas primeiro e metadados disponíveis |
| Leitura | Visualização/download privados, MIME verificado, miniaturas raster autenticadas e ranges |
| Demonstração | Dois perfis e acervo fictício com credenciais aleatórias em arquivo local ignorado |

**Ajustes encontrados e resolvidos:** a demonstração criada por root gerou diretório privado inacessível ao PHP-FPM; o comando passou a exigir www-data e a propriedade da pasta fictícia foi corrigida. A política de conteúdo dos arquivos foi ajustada para preservar o visualizador nativo de PDF e mídia. A listagem em tela menor passou de tabela com rolagem a cartões com metadados e ações visíveis.

**Validação:** 52 testes e 259 assertions aprovados no ambiente isolado. No Edge, os dois perfis completaram login, filtro, navegação, TXT/PDF, miniatura, download e logout, sem erros JavaScript ou requisições externas. Tema persistente e telas desktop/mobile conferidos. Troca pública/autenticada e revogação de uma sessão anterior verificadas no navegador; a senha fictícia original foi restaurada ao terminar. Build de assets e Pint aprovados.

**O que já pode ser visto:** http://127.0.0.1:8080 abre a tela de login. Credenciais de demonstração deste computador estão em artifacts/sprint-2/demo-access.json; para preparar um ambiente novo, usar ./scripts/dev.ps1 demo depois da inicialização. Login não usa contas do SQLite legado.

**Pendências:** TI acompanhar o [roteiro](sprint-2.md) e registrar observações/aceite de T-10. Administração, upload e manutenção de pastas seguem T-11 a T-15; ZIP e lixeira permanecem nas tarefas posteriores. Migração e implantação na VM não foram realizadas.

## 14/10/2026 — Sprint 3: Administração

**Objetivo:** permitir administração de usuários, pastas e publicação do acervo, com rastreabilidade e tratamento de falhas. T-11 a T-14 concluídas tecnicamente; T-15 implementada e em revisão, com aceite da TI/T-10 pendente.

| Frente | Resultado |
|---|---|
| Usuários | Cadastro/listagem/exclusão por admin, senha confirmada, login único e autoexclusão bloqueada; histórico preservado |
| Pastas e nomes | Pastas lógicas, extensão preservada na renomeação, IDs/chaves/filhos estáveis e colisões recusadas |
| Upload | Staging privado, validação por arquivo, sufixos concorrentes, publicação sem sobrescrever e resultado parcial |
| Recuperação | Manifesto privado, compensação de falhas conhecidas e preservação em commit ambíguo; inspeção somente leitura |
| Interface | Formulários administrativos, confirmação de exclusão, progresso de transmissão separado do resultado, tema e tela menor |
| Auditoria | 50 eventos mais recentes acessíveis apenas a admin; senhas e conteúdo fora dos registros |

**Decisões e ajustes:** trava documental global obtida antes de locks de linhas, também na exclusão de usuário que altera FKs documentais. Hard link atômico no mesmo volume evita substituir destino físico. Manifesto precede publicação/commit. A regra de nomes foi alinhada a traços Unicode e parênteses dos sufixos, preservando nomes fictícios já existentes. Limites locais definidos em 10 arquivos/20 MiB por arquivo/64 MiB por lote, ainda sujeitos à TI. A barra de progresso ganhou texto explícito sobre transmissão, para não sugerir sucesso de publicação ao atingir 100%.

**Verificação:** suíte integrada, concorrência em conexões PostgreSQL reais, erro após commit real, invisibilidade antes do commit e falhas de disco/auditoria/limpeza. No Edge: cadastro/login/exclusão de conta fictícia e revogação da sessão, pastas, renomeação, upload válido/parcial/inválido, colisão, CSRF, recusa de user e auditoria. Nenhum erro JavaScript ou requisição externa. Desktop/tela menor conferidos, imagem PHP reconstruída e assets compilados. Contagens finais e detalhes estão em [Sprint 3](sprint-3.md).

**Demonstração:** entrar como demo.admin em http://127.0.0.1:8080/login com as credenciais locais existentes. A pasta de demonstração da Sprint 3 contém apenas arquivos fictícios. O usuário temporário criado para o roteiro foi excluído ao terminar; contas originais de demonstração foram preservadas.

**Pendências:** TI registrar avaliação/aceite de T-10 e T-15. ZIP, lixeira e reconciliação automática seguem T-16 a T-20. Migração e implantação continuam futuras. Não remover volumes ou apagar bytes para resolver um upload pendente.
