# Operação

## Requisitos

- PHP **7.4+** (testado apenas por revisão — ver [pendencias.md](pendencias.md)) com extensões `pdo_sqlite`, `zip` e, de preferência, `mbstring`.
- Servidor web com **docroot = `public/`**. Nada fora de `public/` pode ser acessível pela web.
- Usuário do servidor web com escrita em `storage/`.

## Instalação / migração do sistema antigo

1. Copie o projeto. Aponte o docroot do site para `public/`.
2. Se vier do sistema antigo: `database.db` → `storage/database.db`; pasta `uploads/` → `storage/uploads/`; `uploads/.lixeira/` → `storage/trash/`. (Já feito neste repositório.)
3. Crie/atualize usuários (a senha é pedida no prompt, nunca vai no comando):
   ```bash
   php bin/create-user.php nome admin
   php bin/create-user.php nome --reset
   ```
   Alternativa não interativa: defina `INTRANET_NEW_PASSWORD` antes de rodar.
4. **Troque as senhas antigas** (ver [seguranca.md](seguranca.md)).

## Configuração do servidor

### Limites de upload
PHP só aceita `upload_max_filesize` e `post_max_size` no `php.ini`/`.user.ini`/pool FPM — `ini_set()` não funciona. `public/.user.ini` já traz 2 GB; com **mod_php (Apache)** copie os valores para o `php.ini`:

```ini
upload_max_filesize = 2048M
post_max_size = 2100M
max_execution_time = 1200
memory_limit = 256M
```
No IIS, ajuste também `maxAllowedContentLength` (bytes) no `web.config` do site e o timeout do FastCGI.

### Apache
`public/.htaccess` já bloqueia dotfiles e listagem de diretório (requer `AllowOverride All` ou `Options`/`FileInfo`).

### IIS (exemplo — validar antes de usar)
`public/web.config`:
```xml
<configuration>
  <system.webServer>
    <defaultDocument><files><clear/><add value="index.php"/></files></defaultDocument>
    <security>
      <requestFiltering>
        <requestLimits maxAllowedContentLength="2200000000"/>
        <hiddenSegments><add segment="storage"/></hiddenSegments>
      </requestFiltering>
    </security>
  </system.webServer>
</configuration>
```
O IIS ignora `.htaccess`; confirme que o site aponta para `public/`.

### Variáveis de ambiente (opcionais)
| Variável | Efeito |
|---|---|
| `INTRANET_STORAGE` | pasta de dados (padrão `<projeto>/storage`) — pode ficar em outro disco |
| `INTRANET_DEBUG=1` | mostra erros na tela (**nunca em produção**) |
| `INTRANET_FORCE_HTTPS=1` | use quando um proxy/balanceador termina o HTTPS: força cookie `Secure` e envia HSTS (sem isso o PHP não enxerga o HTTPS) |
| `INTRANET_NEW_PASSWORD` | senha lida por `bin/create-user.php` |

## Backup e restauração

```bash
php bin/backup.php                       # cria <pasta do storage>/../backups/intranet-AAAAMMDD-HHMMSS
php bin/backup.php D:\backups\hoje       # destino explícito (não pode existir)
php bin/backup.php --verify D:\backups\hoje
php bin/restore.php D:\backups\hoje D:\intranet-restaurada   # só em pasta NOVA ou vazia
```

- O banco é copiado **com o sistema ligado** (`VACUUM INTO`; em SQLite antigo, cópia sob trava de escrita). Uploads e lixeira são copiados arquivo a arquivo; `storage/logs` fica de fora.
- Cada backup tem `manifest.json` (tamanho e SHA-256 de tudo). `--verify` e `restore` recusam backup alterado, incompleto ou com banco corrompido.
- Para voltar a produção: pare o sistema e troque a pasta, ou aponte `INTRANET_STORAGE` para a pasta restaurada. O `restore` **nunca sobrescreve** um storage existente.
- Agende (Agendador de Tarefas do Windows ou cron) e **teste a restauração** periodicamente: `restore` numa pasta nova, suba com `INTRANET_STORAGE` e confira o login.

## Verificação do servidor real (smoke)

Depois de publicar ou mudar a configuração, rode de qualquer máquina (só leitura, sem credenciais):

```bash
php bin/smoke.php https://intranet.exemplo.gov.br/
SMOKE_USER=conta SMOKE_PASS=senha php bin/smoke.php https://intranet.exemplo.gov.br/ --login   # opcional: login e logout
```

Confere docroot em `public/`, 20 caminhos internos (`/src/…`, `/database.db`, `/.git/config`…), cabeçalhos de segurança, cookie `HttpOnly/SameSite/Secure`, HSTS, listagem de diretório, erros do PHP na tela e CSRF. **Qualquer `FAIL` bloqueia a publicação**; `WARN` (HTTP puro, `X-Powered-By`, `.htaccess` legível) deve ser revisado.
## Logs e diagnóstico

- Erros PHP: `storage/logs/php-error.log`. Erro 500 genérico na tela = olhe esse arquivo (ou use `INTRANET_DEBUG=1` num ambiente de teste).
- Auditoria de uso: Painel Master → Logs (últimos 50) ou tabela `logs`.

## Teste local rápido
PHP 8.3 está instalado em `D:\php` (no PATH do usuário; abra um terminal novo). `D:\php\php.ini` já habilita `pdo_sqlite`, `sqlite3`, `zip`, `mbstring` e `fileinfo`.
```bash
php -S localhost:8000 -t public
```
Para testar sem mexer nos dados reais, aponte para uma cópia: `INTRANET_STORAGE=C:\caminho\copia php -S localhost:8000 -t public`.

## Testes automatizados

```bash
php tests/all.php                 # 8 suítes, 179 testes, ~95 s
php tests/all.php unit http       # só algumas (login|unit|http|conc|backup|smoke|js|p2|large)
php tests/all.php --list
```

Requisitos: PHP com `pdo_sqlite`, `zip` e `curl` **na linha de comando**; Node ≥ 18 e, uma vez, `cd tests/js && npm install` (jsdom + axe-core, só para desenvolvimento; `tests/js/node_modules` é ignorado pelo git). Usam bancos e pastas temporários e sobem o próprio servidor (`php -S`). Código de saída 1 se algo falhar; os `todo` (testes da Fase 2 da ADR-0005) aparecem como "pend" e não reprovam.

Ferramentas extras (opcionais):

| Ferramenta | Como | Para quê |
|---|---|---|
| Cobertura (PCOV) | `php -d extension=pcov tests/coverage.php --min=90 --lines` | % de linhas de `src/`, piso por arquivo crítico |
| PHPStan | `php phpstan.phar analyse --memory-limit=1G` (config em `phpstan.neon`, nível 5) | tipos e erros prováveis |
| Arquivo grande | `php tests/large_test.php --mb=300` ou `php tests/all.php large` | upload/download com `memory_limit=64M`; lento no `php -S` do Windows |
| PHP 7.4 | `D:\php74\php.exe tests/all.php` | versão mínima declarada |
| CI | `.github/workflows/tests.yml` | PHP 7.4/8.1/8.3/8.4 + Windows, cobertura e PHPStan |

Veja [estrategia-de-testes.md](estrategia-de-testes.md).

Em produção deixe `display_errors = Off` no `php.ini` (já está em `public/.user.ini`): com ele ligado, um upload acima de `post_max_size` devolve o aviso do PHP no corpo da resposta e o status 200.
## Proteção extra na raiz
`.htaccess` e `web.config` na raiz do projeto são só rede de segurança caso o docroot aponte para a pasta errada; não substituem apontar o site para `public/`.
