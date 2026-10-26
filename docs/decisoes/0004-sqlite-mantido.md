# ADR 0004 — Manter SQLite

**Status:** aceita

## Contexto
O banco guarda poucos usuários, logs e metadados. Acesso concorrente é baixo (poucos admins escrevem).

## Decisão
Manter SQLite (`storage/database.db`), com `busy_timeout` de 15 s (5 s estourava com 8 processos gravando em disco lento, no runner do Windows) e migração idempotente no boot (`CREATE ... IF NOT EXISTS`).

## Consequências
- (+) Sem servidor de banco; backup = copiar arquivo.
- (−) Escritas concorrentes são serializadas; o banco não deve ficar em compartilhamento de rede (SMB/NFS) por causa de locking.
- Gatilho para revisar: múltiplos servidores web, ou volume de logs/uploads que cause `database is locked`. Nesse caso, migrar para MySQL/PostgreSQL e introduzir tabela `schema_version` com migrações numeradas.
