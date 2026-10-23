# Pendências e itens não verificados

## Verificado (23/10/2026, PHP 8.3.36 em `D:\php`, servidor embutido, cópia do banco)
`php -l` limpo nos 22 arquivos PHP; `node --check` nos 3 JS. Roteiro de fumaça executado por HTTP (curl) com **dados de teste isolados**, passou em: login anônimo → tela de login; `database.db`, `src/`, `config/`, `storage/`, `uploads/` → 404; POST sem CSRF → 419; bloqueio após 5 falhas (inclusive com a senha certa); download inline só de txt/pdf/imagem, SVG sempre como `attachment`; `Range` 206; traversal (`../`, `....//`, `%2f`) cai na raiz; mkdir, upload (`.exe` bloqueado), renomear pasta com metadados dos filhos, mover para lixeira, restaurar **na pasta original**, restaurar na raiz quando a original sumiu, purge, esvaziar; ZIP; criar usuário (senha curta e role inválida recusadas); usuário comum sem botões de admin e com POSTs de admin negados; troca de senha; logout; usuário apagado perde a sessão na hora.

## Não verificado
- **PHP 7.4** (versão mínima declarada): só foi executado no 8.3. Confirmar a versão real do servidor.
- **Navegador:** o JavaScript (`app.js`) só passou em `node --check`; modais, lightbox, barra de progresso do upload e o tema escuro não foram clicados num navegador real.
- **Apache/IIS reais:** `.htaccess`, `web.config` e o redirecionamento de docroot não foram testados (não há servidor web nesta máquina).
- **Uploads grandes** (centenas de MB) e vídeo com `Range` num player real.

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
