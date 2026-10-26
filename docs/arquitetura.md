# Arquitetura

## Visão geral

Aplicação PHP sem framework, em camadas finas. Um único ponto de entrada web (`public/index.php`) delega a `App\Application`, que faz sessão, headers de segurança, CSRF, autenticação e roteamento.

```
public/index.php
   └─ src/bootstrap.php          constantes, autoload PSR-4 (App\ => src/), helpers, config
   └─ App\Application            sessão + headers + CSRF + login obrigatório + rotas
        ├─ App\Context           agrupa serviços (config, db, auth, logger, storage, meta)
        ├─ Controllers/          uma classe por assunto (Auth, Files, Trash, Users)
        │    └─ Controller       base: flash, redirect, JSON, resolução da pasta atual
        ├─ Services/
        │    ├─ FileStorage      TODO acesso ao disco: caminhos seguros, nomes, listagem
        │    └─ FileMeta         quem criou/editou; origem dos itens da lixeira
        ├─ Auth, Csrf, Logger, Database
        ├─ Backup                backup/restauração do storage (usada por bin/backup.php e bin/restore.php)
        └─ Views/                templates PHP (somente apresentação)
```

Regra de dependência: Views não consultam banco nem disco; Controllers não montam SQL (usam Auth/FileMeta/Logger) nem manipulam caminhos "na mão" (usam FileStorage).

## Fluxo de uma requisição

1. `Application::run()` configura erros, inicia sessão (`INTRANETSESSID`, `HttpOnly`, `SameSite=Lax`, `Secure` em HTTPS) e envia headers (`nosniff`, `X-Frame-Options`, ...).
2. `buildContext()` conecta ao SQLite (criando/migrando tabelas) e instancia serviços.
3. `dispatch()`:
   - POST maior que `post_max_size` → erro 413 claro.
   - Resolve `action` (POST: campo `action`; GET: `?action=`).
   - **Todo POST exige token CSRF** válido (campo `_csrf` ou header `X-CSRF-Token`).
   - Sem login, só `login` e `change_password` passam; o resto cai na tela de login.
4. O controller executa, grava flash em sessão e **redireciona** (Post/Redirect/Get). Upload via XHR responde JSON.

## Rotas

| Método | `action` | Controller::método | Quem |
|---|---|---|---|
| GET | *(vazio)* | `FilesController::index` (ou tela de login) | logado |
| GET | `download` | `FilesController::download` | logado (lixeira: admin) |
| GET | `tree` | `FilesController::tree` | logado (JSON com as subpastas diretas de `dir`) |
| GET | `logout` | `AuthController::logout` | logado |
| POST | `login` | `AuthController::login` | público |
| POST | `change_password` | `AuthController::changePassword` | público* / logado |
| POST | `mkdir`, `rename`, `upload`, `trash`, `move` | `FilesController` | admin |
| POST | `zip` | `FilesController::zip` | logado |
| POST | `restore`, `purge`, `empty_trash` | `TrashController` | admin |
| POST | `create_user`, `delete_user` | `UsersController` | admin |
| POST | `set_folder_access` | `FilesController` | admin |
| POST | `create_group`, `delete_group`, `set_group_members` | `GroupsController` | admin |

\* O formulário público exige a senha atual e conta no limite de tentativas.

Contexto de navegação vem da query: `?dir=<subpasta>` e `?mode=trash` (só admin). Ações POST usam a URL atual, então herdam esse contexto.

## Armazenamento

```
storage/               fora do docroot
├─ uploads/            arquivos da intranet
├─ trash/              lixeira
├─ database.db         SQLite
└─ logs/php-error.log
```

Arquivos **nunca** são servidos direto pelo servidor web: passam por `?action=download`, que exige login, valida o caminho, define `Content-Type` por lista branca e suporta `Range` (vídeo/PDF). Só `jpg/png/gif/webp/pdf/mp4/txt` abrem inline; o resto (incluindo SVG/XML) sempre baixa.

## Front-end

`public/assets/{css,js,img}`. JS sem handlers inline: elementos usam atributos `data-*` (`data-modal-open`, `data-post-action`, `data-rename`, `data-lightbox`, `data-theme-toggle`) tratados por delegação em `app.js`. Ações destrutivas são POST com CSRF via `postAction()`.
