# Desenvolvimento local

A fundação e o incremento de acesso/consulta estão implementados. Veja as evidências da [Sprint 1](sprint-1.md), [Sprint 2](sprint-2.md) e o [inventário](inventario.md). Use Docker Desktop/WSL2 em modo Linux; PHP, Composer e Node são executados nos containers.

## Preparação reproduzível

Na raiz, execute no PowerShell:

```powershell
./scripts/dev.ps1 prepare
./scripts/dev.ps1 build
./scripts/dev.ps1 install
./scripts/dev.ps1 assets
./scripts/dev.ps1 migrate
./scripts/dev.ps1 up
./scripts/dev.ps1 test
```

O script encontra o Docker instalado por usuário ou em Program Files. `prepare` gera APP_KEY e senhas aleatórias locais, usando as imagens fixadas em `docker/images.env`; não sobrescreve `.env`. Composer usa `composer.lock` e npm usa `package-lock.json`. Alterações de dependências devem atualizar e revisar esses arquivos.

A aplicação está em http://127.0.0.1:8080, com login, troca de senha e consulta privada. `/up` retorna 200 quando banco e storage estão disponíveis e 500 em falha. Debug está desativado. Administração e ZIP seguem as próximas sprints.

Para demonstração opt-in em um banco local novo, execute `./scripts/dev.ps1 demo` depois de `up`. O comando usa `www-data`, gera senhas aleatórias e salva credenciais em `artifacts/sprint-2/demo-access.json`. Não sobrescreve contas existentes e não importa usuários/documentos do legado. O roteiro e as pendências do aceite estão em [Sprint 2](sprint-2.md).

## Composição e dados

Sempre carregar os dois arquivos explicitamente. Equivalente ao script:

```powershell
docker compose -f compose.yaml -f compose.development.yaml up -d --wait web
docker compose -f compose.yaml -f compose.development.yaml run --rm app php artisan migrate --database=pgsql_migrate --force
docker compose -f compose.yaml -f compose.development.yaml run --rm test
```

`web` usa Nginx e publica apenas loopback:8080. `app` usa PHP-FPM; `db` PostgreSQL 18.6 sem porta no host. `scheduler` reutiliza a imagem da aplicação, está em perfil opcional e permanece desligado até T-20. `node` é ferramenta temporária de build. O complemento monta somente caminhos Laravel, nunca a raiz completa ou o legado. A configuração no container vem de variáveis de ambiente; `.env.example` é montado somente para leitura.

O banco de desenvolvimento, storage privado, vendor e cache têm volumes nomeados. Os testes usam `db-test/intranet_test`, rede própria, banco em tmpfs e volumes de storage/cache separados. O provider recusa APP_ENV=testing apontado ao banco de desenvolvimento. Factories e fixture são sintéticas; não importam SQLite ou uploads.

Migrations usam `intranet_migrator`; requisições e testes de negócio usam `intranet_app`, sem privilégio DDL. Credenciais de administração só são usadas na inicialização do PostgreSQL. Mudanças de senha posteriores exigem alteração coordenada no banco; editar `.env` não reinicializa um volume existente.

## Operação cotidiana

```powershell
./scripts/dev.ps1 check
./scripts/dev.ps1 logs
./scripts/dev.ps1 down
./scripts/dev.ps1 up
```

`down` preserva volumes. Preserve também `.env` e APP_KEY. Recriar containers não substitui backup. Não executar remoção de volumes nem rollback de migrations em bancos com dados reais. Build e teste de imagens de produção sem mounts, exportação/importação `.tar`, checksum e manifesto pertencem à Sprint 6.

O trabalho parte de `origin/dev`, conforme [contribuição](../CONTRIBUTING.md). Segredos, certificados, backups e relatórios locais ficam ignorados. Os arquivos legados continuam nos caminhos originais e não são servidos pelo Nginx novo.
