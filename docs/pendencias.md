# Pendências e itens não verificados

## Verificado (23/10/2026)

- **179 testes automatizados** (`php tests/all.php`) verdes em **PHP 8.3.36 e 7.4.33**; cobertura de linhas **96,0 %**; PHPStan nível 5 sem erros. Detalhes em [estrategia-de-testes.md](estrategia-de-testes.md).
- Roteiro manual no navegador (JS, modais, upload, lixeira, perfil comum, celular, contraste claro/escuro) sem erros.
- Backup com o sistema ligado, verificação por SHA-256 e restauração, com o sistema subindo na pasta restaurada.
- `bin/smoke.php` aprova instalação certa e **reprova docroot errado**.

## Não verificado

- **Servidor real (Apache/IIS):** `.htaccess`, `web.config`, docroot em `public/`, `php.ini`, HSTS atrás de proxy. Rode `php bin/smoke.php <url>` lá.
- **GitHub Actions:** o workflow foi validado como YAML e reproduzido localmente, mas nunca executou no GitHub.
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
