# Segurança — achados do code review e status

Legenda: ✅ corrigido no código · ⚠️ ação manual necessária · ⏳ pendente

## Crítico

| # | Achado | Status |
|---|---|---|
| 1 | `database.db` dentro do docroot, baixável por URL (hashes + logs) | ✅ banco em `storage/` (fora de `public/`) + `storage/.htaccess` de rede de segurança |
| 2 | Senhas padrão no código-fonte (`adm`, `Vinicius`, `Fcecon`) | ✅ seed removido do código e do backup; senhas das 6 contas redefinidas (ver "Rotação de senhas" abaixo) |
| 3 | `uploads/` público, sem login (e `.htaccess` só vale em Apache) | ✅ arquivos fora do docroot; download só via PHP autenticado |
| 4 | Sem CSRF; ações destrutivas por GET | ✅ token em todo POST; ações destrutivas viraram POST |

## Alto

| # | Achado | Status |
|---|---|---|
| 5 | Troca de senha pública sem limite; login sem rate limit; sem `session_regenerate_id` | ✅ rate limit (tabela `login_attempts`), regenerate no login, token CSRF novo no login |
| 6 | SVG permitido e servido direto (XSS armazenado) | ✅ só jpg/png/gif/webp/pdf/mp4/txt abrem inline; resto sempre `attachment` + `nosniff` |
| 7 | XSS em `title=`, `src=`, `onclick=`; `new_type` livre | ✅ tudo escapado com `h()`; handlers inline removidos; role validada contra `['admin','user']` |
| 8 | Anti-traversal fraco (`str_replace('../')`) | ✅ `FileStorage::resolve` rejeita `..` por segmento e compara prefixo com `/` final |
| 9 | Role só na sessão | ✅ usuário/role recarregado do banco a cada request; sessão expira após 8h de inatividade e 12h absolutas |

## Médio

| # | Achado | Status |
|---|---|---|
| 10 | `ini_set` de upload sem efeito | ✅ movido para `public/.user.ini` (ou `php.ini`); `memory_limit` 1024M → 256M |
| 11 | `mkdir 0777` | ✅ `0775` |
| 12 | N+1 de metadados | ✅ 1 consulta por página |
| 13 | Lixeira restaurava só na raiz | ✅ restaura na pasta original (tabela `trash_origin`) |
| 14 | Tabela `logs` sem retenção | ✅ limpeza automática > 180 dias (`config['logs']['retention_days']`) |
| 15 | Font Awesome por CDN, sem SRI | ⏳ ver [pendencias.md](pendencias.md) |
| 16 | Uploads simultâneos com o mesmo nome sobrescreviam arquivos em silêncio | ✅ FileStorage::reserveName (reserva atômica) |
| 17 | Dois admins podiam se excluir ao mesmo tempo e deixar o sistema sem admin | ✅ deleteUser em transação BEGIN IMMEDIATE |
| 18 | Erro 500 em modo debug saía com status 200 | ✅ buffer de saída em Application::run |
| 19 | Corrida no limite de tentativas de login (30 simultâneas gravam 10 falhas) | ⏳ Fase 2 da ADR-0005 (teste 	odo já escrito) |

## Permissões por pasta

| Garantia | Onde |
|---|---|
| Pasta restrita não abre, não baixa e não entra em ZIP para quem não está na regra (nem pela URL direta) | `Controller::location()` → `FolderAccess::allows` |
| Regra de subpasta não reabre o que o pai fechou | `FolderAccess::allows` (interseção na cadeia) |
| Apagar grupo/usuário nunca libera pasta (falha fechada) | `Groups::delete`, `Auth::deleteUser` |
| Restrição acompanha rename, lixeira e restauração | `FolderAccess::renamed` |
| Só admin define regras e grupos; POST exige CSRF | `FilesController::setFolderAccess`, `GroupsController` |
| Cadeado expõe o **nome** da pasta (decisão de produto), não o conteúdo | [ADR 0006](decisoes/0006-permissoes-por-pasta.md) |

## Rotação de senhas (feita em 23/10/2026)

- As **6 contas** do banco real (`adm`, `Vinicius`, `Fcecon`, `Gilberson`, `Comunicação`, `Icaro`) tiveram a senha redefinida para valores aleatórios de 16 caracteres, por autorização do responsável. Registrado no log como `SENHA` / `CLI`.
- Conferido: as senhas que estavam no código **não funcionam mais**.
- As novas senhas estão em `senhas-novas.txt` na raiz do projeto (ignorado pelo git). **Guardar em gerenciador de senhas, repassar a cada pessoa por canal seguro e apagar o arquivo.**
- Senhas antigas removidas também do backup do código antigo (`_backup_original/index.php`). O backup do banco (`_backup_original/database.db`) ainda tem os hashes antigos: apagar junto com a pasta depois de validar.

## Docroot (`public/`)

O servidor web real **não está nesta máquina**, então o apontamento do site para `public/` precisa ser feito por quem administra o servidor (ver [operacao.md](operacao.md)). Como rede de segurança, a raiz do projeto traz `.htaccess` (Apache: bloqueia `src/ config/ storage/ bin/ docs/` e encaminha o resto para `public/`) e `web.config` (IIS: oculta essas pastas; o site em si só funciona com o docroot correto). Nenhum dos dois foi testado num servidor real.

## Ainda manual

1. Apontar o docroot para `public/`.
2. Revisar `storage/logs/php-error.log` periodicamente.

## Modelo de ameaças resumido

- Rede interna, usuários autenticados. Admin tem poder total (upload, apagar, gerir usuários).
- Não há CSP: o painel usa estilos inline e Font Awesome do CDN. Endurecer exige primeiro mover o CSS inline para arquivos e hospedar o CDN localmente.
- Sem 2FA, sem política de expiração de senha.
