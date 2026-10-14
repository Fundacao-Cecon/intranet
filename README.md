# Intranet FCECON

Portal institucional em evolução para Laravel 13, PHP 8.4, PostgreSQL e Docker. A Sprint 1 entrega a fundação técnica; login, consulta e administração serão implementados nas próximas sprints. O legado permanece intacto.

## Executar no Windows

Inicie Docker Desktop com WSL2 e containers Linux. No PowerShell, na raiz do repositório:

```powershell
./scripts/dev.ps1 prepare
./scripts/dev.ps1 build
./scripts/dev.ps1 install
./scripts/dev.ps1 assets
./scripts/dev.ps1 migrate
./scripts/dev.ps1 up
./scripts/dev.ps1 test
```

Abra http://127.0.0.1:8080. A página inicial apresenta a fundação; `/up` verifica PostgreSQL e storage privado. As migrations são explícitas e não rodam no entrypoint.

`prepare` gera configuração local e segredos apenas se `.env` não existir. Preserve esse arquivo e a APP_KEY nas atualizações. `down` remove containers e rede, preservando volumes; não use `down -v` para atualizar.

## Estrutura

- `app`, `bootstrap`, `config`, `routes`, `resources`, `public`: aplicação Laravel e assets locais.
- `database`: migrations e factories sintéticas; `tests`: testes isolados.
- `docker`, `compose.yaml`, `compose.development.yaml`, `scripts`: ambiente local explícito.
- `docs`: arquitetura, modelagem, backlog, inventário e evidências.
- `index.php`, `database.db`, `uploads` e imagens da raiz: legado preservado, fora dos mounts e do contexto Docker.

Somente `web` publica a porta 8080, vinculada ao loopback. `app` e `db` usam rede privada. `scheduler` está preparado como perfil opcional e sua ativação funcional pertence a T-20. Banco, storage, dependências e caches usam volumes separados; testes usam PostgreSQL e storage próprios. Produção, pacote `.tar`, migração do legado e implantação na VM pertencem às sprints seguintes.

## Documentação

| Documento | Conteúdo |
|---|---|
| [Desenvolvimento](docs/desenvolvimento.md) | Comandos e configuração local |
| [Sprint 1](docs/sprint-1.md) | Entrega, esquema físico e verificação |
| [Diário de bordo](docs/diario-de-bordo.md) | Execução da sprint, decisões e próximos passos |
| [Inventário](docs/inventario.md) | Ambiente confirmado e pendências da TI |
| [Arquitetura](docs/arquitetura.md) | Regras, requisitos e decisões |
| [Modelagem](docs/modelagem.md) | Fluxos da solução completa |
| [Planejamento](docs/planejamento.md) | Tarefas e próximas sprints |

Segredos, dependências, builds, backups e artefatos locais não são versionados. O SQLite e os documentos legados já rastreados continuam no histórico; sua retirada exige o processo de migração. Consulte [CONTRIBUTING.md](CONTRIBUTING.md).
