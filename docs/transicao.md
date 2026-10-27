# Transição: PHP atual → Laravel + Next.js

Guia de operação da migração em fatias ([ADR 0008](decisoes/0008-laravel-e-nextjs-em-fatias.md)). Público: quem desenvolve e quem administra o servidor. Plano de fases: [roadmap.md](roadmap.md).

> **Estado:** desenho aprovado em 26/10/2026; nada disto está implantado. Os trechos de configuração são **exemplos não validados** neste ambiente (a máquina de desenvolvimento é Windows; a produção será Linux). Valide no servidor de homologação antes de usar.

## 1. Como as duas pilhas convivem

```
Navegador ── HTTPS ──► Nginx ou Apache (um só domínio)
                       ├─ /                → PHP atual (legado, congelado)     public/
                       ├─ /novo/…          → Next.js estático (arquivos)       web/out/
                       └─ /api/…, /sanctum/… → Laravel 13 (php-fpm 8.3)       api/public/

Compartilhado pelas duas pilhas
  storage/database.db     SQLite (usuários, logs, metadados, regras de pasta, grupos)
  storage/uploads/        arquivos
  storage/trash/          lixeira
Separado
  Cookie de sessão do legado (PHPSESSID) × cookie de sessão do Laravel + XSRF-TOKEN
```

Uma pessoa usa **um lado de cada vez**: quem entra por `/novo` autentica no Laravel; quem entra por `/` autentica no legado. Não há login compartilhado entre os dois, de propósito (evita uma ponte de sessão frágil).

### Regras de coexistência

| # | Regra | Por quê |
|---|---|---|
| 1 | O Laravel **adota** o esquema existente (migration *baseline* que não recria tabelas) e só faz mudanças **aditivas** | O legado roda `CREATE TABLE IF NOT EXISTS` em todo boot e ignora colunas que não conhece |
| 2 | **Nada de renomear ou remover** coluna, tabela ou índice que o legado usa até a troca | O legado quebraria |
| 3 | SQLite em **modo WAL** e `busy_timeout` alto nos dois lados; banco **nunca** em SMB ou NFS | Dois processos PHP escrevendo; leitores não bloqueiam o escritor |
| 4 | Hashes de senha são os mesmos: bcrypt `$2y$` do `password_hash` é aceito pelo Laravel; no primeiro login nele o hash é refeito no custo do Laravel e o legado continua verificando | Ninguém precisa trocar a senha por causa da migração |
| 5 | As tabelas dos módulos novos (avisos, versões, links, índice de busca) **só são criadas depois da troca** | O legado não sabe atualizá-las ao renomear, mover ou apagar |
| 6 | O legado fica **congelado**: só correções de segurança | Recursos novos seriam feitos duas vezes |
| 7 | A auditoria (`logs`) é escrita pelos dois, no mesmo formato de ação (`LOGIN`, `UPLOAD`, `MOVER`…) | O Painel Master do legado e o novo mostram o mesmo histórico |
| 8 | A caixa do disco manda: o Laravel compara caminhos como o legado (`resolve()` devolve o nome real do disco) | Já houve contorno de regra por `RH.`, `RH~1` e caixa; os testes estão na [matriz de paridade](matriz-de-paridade.md) |

## 2. Roteamento (exemplo)

Nginx, domínio único. **Exemplo não validado.**

```nginx
server {
    listen 443 ssl http2;
    server_name intranet.exemplo.org.br;

    # Legado: docroot em public/
    root /srv/intranet/public;
    index index.php;

    # Arquivos estáticos do Next (exportação estática, basePath /novo)
    location /novo/ {
        alias /srv/intranet/web/out/;
        try_files $uri $uri.html $uri/ /novo/index.html;
        add_header Cache-Control "no-cache";
    }
    location /novo/_next/static/ {
        alias /srv/intranet/web/out/_next/static/;
        add_header Cache-Control "public, max-age=31536000, immutable";
    }

    # Laravel
    location ~ ^/(api|sanctum)/ {
        root /srv/intranet/api/public;
        try_files /index.php =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /srv/intranet/api/public/index.php;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        client_max_body_size 2100M;
    }

    # Legado
    location / {
        try_files $uri /index.php?$query_string;
    }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        client_max_body_size 2100M;
    }

    # Nada interno pelo web
    location ~ /\.(?!well-known) { deny all; }
}
```

Apache: use `Alias /novo /srv/intranet/web/out`, `ProxyPassMatch`/`SetHandler proxy:fcgi` para `/api` e `/sanctum`, e mantenha o docroot do legado em `public/`. O `bin/smoke.php` do legado continua valendo para conferir o docroot.

## 3. Configuração do Laravel para SPA no mesmo domínio

Variáveis em `api/.env` (nomes do Laravel; valores de exemplo):

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://intranet.exemplo.org.br

DB_CONNECTION=sqlite
DB_DATABASE=/srv/intranet/storage/database.db

SESSION_DRIVER=file
SESSION_LIFETIME=480            # ociosidade de 8 h, como hoje (minutos)
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SANCTUM_STATEFUL_DOMAINS=intranet.exemplo.org.br
```

Pontos de atenção:

- **Mesmo domínio** é o que dispensa CORS e cookies de terceiros. Não sirva o Next em outro host.
- **Proxy na frente:** configure `trusted proxies` para o `Secure` e o IP real funcionarem (a regra do legado, `trusted_proxies` vazio por padrão, continua sendo o ponto de partida).
- **Teto absoluto de sessão (12 h):** o Laravel só tem ociosidade; o teto é um middleware próprio (Fase 1).
- **Next:** `output: 'export'`, `basePath: '/novo'`, `trailingSlash: true`; páginas só de cliente; `fetch` com `credentials: 'include'`; antes do primeiro POST, `GET /sanctum/csrf-cookie`.

## 4. Runbook — piloto em `/novo`

**Quando usar:** Fases 2 e 3 concluídas, matriz de paridade em 100 % e homologação verde.

**Pré-requisitos**
- Acesso de administrador ao servidor (ou à TI que o administra).
- Backup recente: `php bin/backup.php` (legado) ou `php artisan intranet:backup` (Laravel). Guarde fora do servidor.
- Grupo piloto definido (3 a 5 pessoas, incluindo um admin e um usuário comum).

**Passos**
1. Publicar `web/out/` em `/srv/intranet/web/out/` e o Laravel em `api/` (sem `--dev`; `php artisan config:cache`).
2. Aplicar o proxy da seção 2 e recarregar o Nginx/Apache (`nginx -t` antes).
3. Rodar o smoke contra a URL pública:
   ```bash
   php artisan intranet:smoke https://intranet.exemplo.org.br --login
   ```
   Todo `FAIL` bloqueia o piloto. `WARN` deve ser lido e justificado.
4. Pedir ao grupo piloto que use **só** `/novo` por 1 a 2 semanas e registre problemas.
5. Acompanhar diariamente: `storage/logs`, tabela `logs` (ações `LOGIN_FALHA`, `LOGIN_BLOQUEADO`, `LOGIN_ALERTA`), erros 5xx do servidor web.

**Critério para seguir:** nenhum incidente de segurança, nenhuma perda de dado, paridade confirmada pelo grupo piloto.

## 5. Runbook — troca do `/` (1.0.0)

**Quando usar:** piloto aprovado e janela combinada (15 a 30 minutos, fora do expediente).

1. Avisar os usuários (o Mural de avisos ainda não existe: use e-mail).
2. Backup completo e conferência: `php artisan intranet:backup` e depois `intranet:restore` em pasta de teste.
3. Em manutenção: `php artisan down` e mensagem estática no proxy para `/`.
4. Trocar o proxy: `/` passa a servir o Next (`basePath` muda de `/novo` para vazio e novo `next build`) e `/api` segue no Laravel. O legado deixa de ser roteado (arquivos preservados).
5. `php artisan up`; rodar o smoke com `--login`.
6. Observar o primeiro dia.

**Volta atrás (até 30 dias):** reapontar `/` para `public/` do legado e desligar `/api`. O banco e os arquivos são os mesmos, então **nada precisa ser restaurado**, desde que só tenham sido aplicadas mudanças aditivas. Sessões do Laravel caem e o pessoal entra de novo pelo legado.

Se o rollback acontecer depois de uma migration destrutiva (não deve haver nenhuma antes de 30 dias), restaure o backup do passo 2 e informe quem perde o que foi gravado desde a troca.

**Depois de 30 dias estáveis:** remover o legado do servidor, apagar `senhas-novas.txt` e `_backup_original/`, marcar a tag `v1.0.0` como estável e abrir a Fase 5.

## 6. Escalonamento

| Situação | Quem | O quê |
|---|---|---|
| Falha de segurança suspeita (acesso indevido a pasta, vazamento) | Responsável pelo projeto, imediato | Derrubar `/novo` ou `/` afetado no proxy; preservar os logs; trocar senhas se for o caso |
| Servidor/proxy | TI da instituição | Reaplicar a configuração da seção 2 |
| Dados | Responsável pelo projeto | Restaurar o backup do passo 2 do runbook |
| Dúvida de requisito | Quem pediu o módulo | Atualizar a ADR do módulo antes de codar |

## 7. Referências

- [roadmap.md](roadmap.md) · [kanban.md](kanban.md) · [matriz-de-paridade.md](matriz-de-paridade.md)
- [operacao.md](operacao.md) (instalação do legado) · [seguranca.md](seguranca.md) · [banco-de-dados.md](banco-de-dados.md)
- ADRs: [0003](decisoes/0003-sem-framework.md), [0004](decisoes/0004-sqlite-mantido.md), [0005](decisoes/0005-endurecimento-do-login.md), [0006](decisoes/0006-permissoes-por-pasta.md), [0007](decisoes/0007-migracao-postgresql.md), [0008](decisoes/0008-laravel-e-nextjs-em-fatias.md)
