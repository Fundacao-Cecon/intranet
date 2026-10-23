# Banco de dados

SQLite em `storage/database.db`. O esquema é criado/atualizado em todo boot por `Database::migrate()` (`CREATE TABLE/INDEX IF NOT EXISTS`), então bancos antigos ganham as tabelas novas sozinhos.

## Tabelas

### `users`
| Coluna | Tipo | Notas |
|---|---|---|
| id | INTEGER PK | |
| username | TEXT UNIQUE | 3-50 chars: letras, números, `. _ -` (para novos) |
| password | TEXT | `password_hash(PASSWORD_DEFAULT)`; rehash automático no login |
| role | TEXT | `admin` \| `user` |
| created_at, last_login | DATETIME | `last_login` em horário local |

### `logs`
Auditoria: `data_hora` (local), `ip`, `usuario`, `acao`, `detalhe`. Ações: `LOGIN`, `LOGIN_FALHA`, `LOGIN_BLOQUEADO`, `SENHA`, `USER`, `PASTA`, `RENAME`, `UPLOAD`, `BLOQUEIO`, `DOWNLOAD`, `ZIP`, `LIXEIRA`, `RESTAURAR`. Retenção: 180 dias.

### `files_meta`
Quem criou/editou. `filepath` é **relativo à raiz de uploads**; itens na lixeira levam o prefixo `.lixeira/` (compatível com o banco antigo). Ao renomear/mover pasta, os filhos são atualizados por prefixo.

### `login_attempts` *(nova)*
`ip`, `username` (minúsculo), `attempted_at` (epoch). Alimenta o bloqueio de 5 falhas / 15 min. Registros > 24h são apagados.

### `trash_origin` *(nova)*
`trash_path` → `origin_path`. Permite restaurar para a pasta original. Só itens do topo da lixeira têm origem; os demais vão para a raiz.

## Convenções

- Sempre `prepare()` com parâmetros; nunca concatenar entrada do usuário em SQL.
- `LIKE` de prefixo usa `ESCAPE '\'` (nomes com `%` e `_`).
- Mudança de esquema: adicionar `CREATE ... IF NOT EXISTS` ou `ALTER TABLE` condicional em `Database::migrate()`. Se o esquema crescer, trocar por tabela `schema_version` (ver ADR 0004).
