# Preparação do desenvolvimento

## Estado atual

O checkout reúne o código PHP/SQLite legado, seu histórico Git e a documentação da nova arquitetura. O legado mantém os arquivos na raiz e em `uploads`, sem mudança de caminhos relativos.

A aplicação Laravel, o Dockerfile e as configurações Compose ainda não foram criados. Não executar `docker compose up` neste repositório esperando a nova aplicação: a tarefa T-04 fornecerá a configuração e os comandos correspondentes.

## Verificar Docker no Windows

Após instalar o Docker Desktop, iniciar o aplicativo, usar o backend WSL2 e containers Linux. Se um terminal já estava aberto durante a instalação, abrir um novo terminal antes de verificar o comando. Referência: [Docker Desktop com WSL2](https://docs.docker.com/desktop/features/wsl/).

Executar no PowerShell:

```powershell
docker version
docker compose version
docker info --format '{{.OSType}}'
```

O resultado esperado é cliente e servidor Docker acessíveis, Compose disponível e tipo de containers `linux`. Se somente o cliente responder ou o servidor estiver indisponível, verificar se o Docker Desktop terminou a inicialização. Esses comandos verificam a instalação, não iniciam o sistema.

Para inspecionar WSL quando necessário:

```powershell
wsl --version
wsl --list --verbose
```

Docker Desktop permite usar o CLI no terminal Windows com seu backend WSL2; uma distribuição Linux de desenvolvimento separada não é obrigatória para esse uso. Integração com uma distribuição específica pode ser habilitada se o trabalho for feito dentro dela. Fonte: [backend WSL2 do Docker Desktop](https://docs.docker.com/desktop/features/wsl/).

## Próximas tarefas

| Tarefa | Entrega |
|---|---|
| T-01 | Inventário de VM, acervo, horários, recursos e limites com a TI. |
| T-02 | Estrutura Laravel 13/PHP 8.4 e build de assets locais, preservando o legado. |
| T-04 | Docker local com web/app/db, rede privada e volumes isolados. |
| T-03 | Esquema PostgreSQL e migrations, usando o ambiente de T-04. |
| T-05 | Auditoria básica, fixtures sintéticas e base de testes. |

A ordem acima respeita as dependências: IDs de tarefas não obrigam execução numérica. T-01 e T-02 podem ser tratadas independentemente enquanto se resolvem os parâmetros externos. Consulte o [planejamento completo](planejamento.md) para os critérios de cada tarefa.

## Organização prevista da nova aplicação

Laravel será estruturado conforme suas convenções, com controllers, serviços, policies, models e views separados. Os futuros arquivos Compose terão base e complementos de desenvolvimento/produção carregados explicitamente. A composição final terá `web`, `app`, `db` e `scheduler`; este último reutiliza a imagem da aplicação.

O ambiente local poderá montar o código para edição. Imagens de produção incluirão código, dependências e assets e serão testadas sem montagem antes de exportação. Banco e documentos ficam em volumes separados. Fonte: [modelo de aplicação Compose](https://docs.docker.com/compose/intro/compose-application-model/).

Os nomes dos futuros diretórios de runtime não são uma justificativa para mover o legado nesta etapa. A introdução do Laravel deve ser revisada com o histórico e os dados existentes; migração e corte seguem tarefas específicas posteriores.

## Git e arquivos locais

Verificar o checkout com:

```sh
git status --short
git branch --show-current
git remote -v
```

O remoto `origin` aponta para o repositório da Fundação Cecon. A branch de organização é `chore/organizar-repositorio`; iniciar tarefas futuras conforme [CONTRIBUTING.md](../CONTRIBUTING.md).

`database.db` e PDFs legados já são rastreados pelo Git. As regras novas de ignore impedem inclusão automática de novos dados nesses caminhos, mas não retiram os arquivos antigos do índice/histórico. Não usar banco ou storage de produção para validar o desenvolvimento.

Backups locais ficam em `backups/`; arquivos de imagens e relatórios gerados podem ficar em `artifacts/`. Os diretórios são ignorados e não precisam ser criados antes de seu uso. Configurações reais e certificados são locais; exemplos versionados não conterão credenciais.
