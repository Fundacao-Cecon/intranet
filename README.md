# Intranet FCECON

Gerenciador de arquivos da intranet: login, pastas, upload, lixeira, logs de auditoria e metadados de autoria. PHP + SQLite, sem framework.

- **Requisitos:** PHP 7.4+ com `pdo_sqlite` e `zip`; servidor web apontando para `public/`.
- **Primeiro acesso:** `php bin/create-user.php <login> admin` (ver [docs/operacao.md](docs/operacao.md)).
- **Documentação completa:** [docs/README.md](docs/README.md).
- **Fluxo de branches:** Git Flow (`main` = produção, `develop` = integração); ver [docs/git-flow.md](docs/git-flow.md).

```
public/    único diretório exposto na web (index.php + assets)
src/       código (Controllers, Services, Views)
config/    configuração
storage/   dados em runtime: uploads, lixeira, banco, logs  (FORA do docroot)
bin/       scripts de linha de comando
docs/      documentação e decisões de arquitetura
```
