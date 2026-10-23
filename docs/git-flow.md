# Fluxo de branches (Git Flow)

O projeto segue o Git Flow. Os comandos abaixo são `git` puro; funcionam sem instalar o `git-flow`. A configuração `gitflow.*` já está no `.git/config` local, então o `git-flow-avdh` (se instalado) usa os mesmos nomes.

## Branches

| Branch | Papel | Sai de | Volta para | Vida |
|---|---|---|---|---|
| `main` | Produção. Cada commit nela é um release com tag `vX.Y.Z` | — | — | permanente |
| `develop` | Integração. Próximo release em construção | — | — | permanente |
| `feature/<tema>` | Funcionalidade ou correção não urgente | `develop` | `develop` | curta |
| `release/X.Y.Z` | Estabilização de um release (só correções, versão, docs) | `develop` | `main` e `develop` | curta |
| `hotfix/X.Y.Z` | Correção urgente em produção | `main` | `main` e `develop` | curta |

Regras:

- Nunca commitar direto em `main` nem em `develop`; use branch e Pull Request.
- Todo merge de feature, release e hotfix usa `--no-ff` (preserva o histórico da branch).
- Tags anotadas, prefixo `v`, [SemVer](https://semver.org/lang/pt-BR/): `v0.1.0`, `v0.1.1`, `v0.2.0`.
- Mensagens no padrão Conventional Commits, como no histórico: `feat:`, `fix:`, `test:`, `docs:`, `refactor:`.
- O CI (`.github/workflows/tests.yml`) roda em push para `develop`, `main`, `release/**`, `hotfix/**` e em todo Pull Request.

## Feature

```bash
git checkout develop && git pull
git checkout -b feature/nome-curto

# ... commits ...

git push -u origin feature/nome-curto     # abre o PR contra develop
```

Após o PR aprovado e o CI verde, faça o merge no GitHub com "Create a merge commit" (equivale ao `--no-ff`) e apague a branch. Sem PR, localmente:

```bash
git checkout develop && git pull
git merge --no-ff feature/nome-curto
git push origin develop
git branch -d feature/nome-curto
```

## Release

```bash
git checkout develop && git pull
git checkout -b release/X.Y.Z
php tests/all.php                          # tem de passar; só correções entram aqui

git checkout main && git pull
git merge --no-ff release/X.Y.Z -m "release: X.Y.Z"
git tag -a vX.Y.Z -m "Release X.Y.Z"

git checkout develop
git merge --no-ff release/X.Y.Z
git branch -d release/X.Y.Z

git push origin main develop vX.Y.Z
```

## Hotfix

```bash
git checkout main && git pull
git checkout -b hotfix/X.Y.Z               # X.Y.Z = versão corrigida (patch + 1)

# ... correção + teste que reproduz o defeito ...
php tests/all.php

git checkout main
git merge --no-ff hotfix/X.Y.Z -m "hotfix: X.Y.Z"
git tag -a vX.Y.Z -m "Hotfix X.Y.Z"

git checkout develop
git merge --no-ff hotfix/X.Y.Z             # se houver release/* aberta, mergear nela em vez de develop
git branch -d hotfix/X.Y.Z

git push origin main develop vX.Y.Z
```

## Checklist de release

- [ ] `php tests/all.php` verde
- [ ] `php tests/coverage.php --min=90` (cobertura mínima por arquivo crítico)
- [ ] `docs/pendencias.md` revisado (nada bloqueante aberto)
- [ ] Migrações de banco documentadas em [banco-de-dados.md](banco-de-dados.md)
- [ ] Backup de `storage/` feito antes de publicar ([operacao.md](operacao.md))
- [ ] Tag `vX.Y.Z` criada e enviada ao origin
