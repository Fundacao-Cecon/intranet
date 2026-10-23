# Mapa da refatoração: `index.php` antigo → estrutura nova

O original (934 linhas, uma só página PHP+HTML+CSS+JS) está em `_backup_original/index.php`. Números de linha abaixo referem-se a ele.

## Código PHP

| Antigo (linhas) | Novo | Mudança |
|---|---|---|
| 7-15 `ini_set` de upload/memória + checagem de `post_max_size` | `public/.user.ini`; `Application::dispatch` (erro 413) | `ini_set` não tinha efeito para esses limites; agora é config real |
| 17 `session_start` | `Application::startSession` | cookie `HttpOnly`/`SameSite`/`Secure`, modo estrito |
| 23-31 caminhos, `mkdir`, `uploads/.htaccess` | `config/config.php`, `FileStorage::__construct`, `storage/.htaccess` | dados fora do docroot |
| 34-85 conexão PDO, `CREATE TABLE`, **usuários padrão** | `Database::connect/migrate`; seed **removido** → `bin/create-user.php` | sem senhas no código |
| 87 `$isAdmin` (da sessão) | `Auth::isAdmin` (lê do banco a cada request) | rebaixar/apagar usuário vale na hora |
| 89-95 `$extensoesPermitidas` | `config['allowed_extensions']` | |
| 101 `h()` | `src/helpers.php` | igual |
| 103 `limparNome` | `FileStorage::cleanName` | igual |
| 108 `registrarLog` | `Logger::log` | data local explícita; texto limitado a 500 chars |
| 116 `formatarTamanho` | `src/helpers.php` | igual |
| 123 `renomearSeExistir` | `FileStorage::uniqueName` | igual |
| 134 `rrmdir` | `FileStorage::delete` | igual (não segue symlink) |
| 149-200 `getRelPath`, `setFileMeta`, `renameFileMeta`, `deleteFileMeta` | `FileStorage::metaPath`, `FileMeta::created/renamed/removed` | `LIKE` com escape; filhos renomeados em 1 `UPDATE` |
| 206 logout | `AuthController::logout` | |
| 210-227 login | `Auth::attempt`, `AuthController::login` | rate limit, `session_regenerate_id`, rehash |
| 230-243 troca de senha | `Auth::changePassword`, `AuthController::changePassword` | rate limit, mín. 8 caracteres, logado usa só o próprio usuário |
| 341-349 resolução de `?dir=` | `FileStorage::resolve`, `Controller::location` | rejeita `..` por segmento; compara prefixo com `/` final |
| 352-385 ações da lixeira | `TrashController::restore/purge/emptyTrash` | restaura na **pasta original** |
| 389-400 excluir usuário (GET) | `UsersController::delete` (POST) | não apaga o último admin |
| 402-412 mover p/ lixeira (GET) | `FilesController::trash` (POST) | grava origem em `trash_origin` |
| 415-423 nova pasta | `FilesController::mkdir` | modo `0775` |
| 425-437 criar usuário | `UsersController::create`, `Auth::createUser` | valida login, role e senha |
| 439-455 renomear | `FilesController::rename` | |
| 457-476 upload | `FilesController::upload` | contagem de erros; JSON só em XHR |
| 480 download | `FilesController::download` + `stream()` | login obrigatório, `Range`, `nosniff`, whitelist inline |
| 481 ZIP | `FilesController::zip` (POST) | `tempnam`, limpa temporário |
| 483-492 listagem + breadcrumbs | `FileStorage::listDir`, `FilesController::index` | metadados em **1 consulta** (era N+1) |

## HTML / CSS / JS

| Antigo | Novo |
|---|---|
| 248-334 tela de login (HTML + CSS + JS) | `Views/login.php`, `assets/css/login.css`, `assets/js/login.js` |
| 495-933 painel (HTML + CSS + JS) | `Views/files/index.php`, `Views/modals/{admin,common}.php`, `assets/css/app.css`, `assets/js/app.js` |
| `toggleTheme` duplicado (331, 898) | `assets/js/theme.js` (único) |
| `onclick="..."` inline | atributos `data-*` + delegação em `app.js` |
| `showToast("<?= addslashes($msg) ?>")` inline | `data-flash-*` no `<body>` + `textContent` |

## Removido

- Usuários/senhas padrão no código.
- Referências legadas a `users.json`, `sistema_logs.txt`.
- `ini_set` ineficazes.
- Links `GET` destrutivos (`?deletar=`, `?restore=`, `?perm_delete=`, `?deletar_user_id=`).

## Comportamentos que mudaram (intencionalmente)

1. Ações viram **POST com CSRF** e terminam em redirect (recarregar a página não repete a ação).
2. Arquivos só saem por `?action=download` (exige login); URL direta `uploads/...` não existe mais.
3. Senha nova: mínimo 8 caracteres. Login de usuário novo: 3-50 letras/números/`._-`.
4. 5 falhas de login/troca de senha em 15 min para o mesmo usuário+IP bloqueiam por 15 min.
5. Botão 🔑 na barra superior para **qualquer** usuário trocar a própria senha (antes só admin, via Painel Master).
6. SVG/XML/etc. não abrem no navegador: sempre baixam.
7. Data dos logs agora em horário local (antes UTC exibido como se fosse local).
