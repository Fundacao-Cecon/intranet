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

## Backup

Faça cópia periódica de **`storage/`** inteiro (uploads, lixeira, `database.db`). Com o sistema parado ou fora de horário, copie o `.db` junto com eventuais `-wal`/`-journal`. Teste a restauração.

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
php tests/run.php
```
Sem dependências (não usa Composer/PHPUnit): cria um banco temporário, simula requisições com sessões reais e sai com código 1 se algo falhar. Hoje cobre o fluxo de login (18 testes: expiração de sessão, teto absoluto, tempo de resposta, logs, bloqueio, cookie/`gc_maxlifetime`, tela de login). Rode antes de cada deploy.

Sessões: o PHP apaga arquivos de sessão com `session.gc_maxlifetime`; a aplicação ajusta esse valor para 8 h. Se outro sistema usar a mesma pasta de sessões com um valor menor, ele pode apagar as sessões daqui antes da hora: dê a cada sistema sua própria `session.save_path`.

## Proteção extra na raiz
`.htaccess` e `web.config` na raiz do projeto são só rede de segurança caso o docroot aponte para a pasta errada; não substituem apontar o site para `public/`.
