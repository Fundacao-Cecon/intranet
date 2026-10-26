# Pendências e itens não verificados

## Verificado (26/10/2026, release 0.2.0)

- **249 testes automatizados** (`php tests/all.php`: 18 login, 57 unitários, 80 HTTP, 6 concorrência, 10 backup, 4 smoke, 74 JS/a11y) verdes em **PHP 8.3.36**; unit, HTTP e concorrência também em **PHP 7.4.33**. Mais 7 testes `todo` da ADR-0005 Fase 2 (pendentes de propósito).
- **GitHub Actions** verde (PHP 7.4, 8.1, 8.3, 8.4, Windows, cobertura de linhas com meta 90 % e PHPStan nível 5) nos PRs #1 a #4. Detalhes dos testes em [estrategia-de-testes.md](estrategia-de-testes.md), que ainda descreve a contagem de 23/10.
- Roteiro manual no navegador (JS, modais, upload, lixeira, perfil comum, celular, contraste claro/escuro) sem erros.
- Backup com o sistema ligado, verificação por SHA-256 e restauração, com o sistema subindo na pasta restaurada.
- `bin/smoke.php` aprova instalação certa e **reprova docroot errado**.

## Não verificado

- **Servidor real (Apache/IIS):** `.htaccess`, `web.config`, docroot em `public/`, `php.ini`, HSTS atrás de proxy. Rode `php bin/smoke.php <url>` lá.
- **Pastas (0.2.0):** permissões por pasta, árvore lateral e mover foram testados por testes automatizados e no navegador embutido (desktop e celular), não num servidor Apache/IIS real nem com o arraste nativo do mouse (a automação só gera os eventos de arrastar).
- **Upload grande fora do `php -S`:** só 40 MB foram medidos (o servidor embutido do Windows recebe a ~1 MB/s); `php tests/large_test.php --mb=500` precisa rodar contra Apache/IIS.
- **Navegador real automatizado (Playwright):** não instalado; o roteiro manual vale por enquanto.
- **Linux:** o teste de symlink é pulado no Windows sem privilégio; roda no CI.

## Roteiro de fumaça (para repetir no servidor real)
1. `php -S localhost:8000 -t public` → tela de login aparece, sem erro no console.
2. Login com usuário existente; senha errada 5× → bloqueio.
3. Navegar `Norma Zero`, buscar, abrir PDF (nova aba), baixar arquivo, ZIP de 2 arquivos.
4. Admin: upload (barra de progresso), nova pasta, renomear pasta com arquivos (metadados "Criado por" seguem), mover para lixeira, restaurar (volta à pasta original), excluir definitivo, esvaziar.
5. Admin: criar usuário, tentar excluir a si mesmo (bloqueado), excluir outro, ver logs.
6. Usuário comum: sem botões de admin; `?mode=trash` não mostra lixeira.
7. `http://host/database.db` e `http://host/storage/` → 404/403.
8. Tema escuro persiste entre login e painel.

## Feito parcialmente / adiado
| Item | Situação |
|---|---|
| Font Awesome local (sem CDN) | ⏳ ainda via cdnjs; baixar `all.min.css` + `webfonts/` para `public/assets/vendor/fontawesome` e trocar o `<link>` |
| Comprimir imagens | ⏳ `fcecon_bg.png` 2,7 MB e `logotipo.png` 1,3 MB → WebP/JPEG |
| Unificar paletas CSS de login e painel | ⏳ `login.css` e `app.css` ainda têm `:root` separados |
| Testes automatizados (PHPUnit) | ⏳ sugeridos: `FileStorage::resolve/cleanName`, `Auth` (rate limit), `FileMeta::renamed` |
| CSP / 2FA / expiração de senha | ⏳ ver [seguranca.md](seguranca.md) |
| `schema_version` para migrações | ⏳ só se o esquema crescer (ADR 0004) |
| Duplicatas | `storage/trash/` repete 7 PDFs de `storage/uploads/Norma Zero/` (~12 MB) — decidir se esvazia |
| `_backup_original/` | contém o `index.php` antigo, `database.db` e cópia dos uploads. Apagar após validar (tem hashes de senha) |
| Docroot | confirmar com quem administra o servidor que passou a apontar para `public/` |
