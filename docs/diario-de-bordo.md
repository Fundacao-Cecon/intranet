# Diário de bordo — Intranet FCECON

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
