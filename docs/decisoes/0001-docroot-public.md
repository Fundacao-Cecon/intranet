# ADR 0001 — Docroot em `public/` e dados em `storage/`

**Status:** aceita

## Contexto
O sistema antigo servia a raiz do projeto: `database.db` (hashes de senha), o código e todos os uploads ficavam acessíveis por URL. A proteção dependia de `.htaccess`, que só existe no Apache.

## Decisão
Somente `public/` é exposto. Banco, uploads, lixeira e logs ficam em `storage/` (configurável por `INTRANET_STORAGE`). Código em `src/`, configuração em `config/`.

## Consequências
- (+) Vazamento de banco/código por URL deixa de ser possível, em qualquer servidor web.
- (+) `storage/` pode ir para outro disco/volume e ter backup separado.
- (−) O servidor precisa ser reconfigurado para apontar para `public/` (passo obrigatório de deploy).
- (−) URLs diretas antigas `uploads/...` deixam de funcionar.
