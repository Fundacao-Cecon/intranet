# ADR 0002 — Arquivos entregues por PHP autenticado

**Status:** aceita

## Contexto
Com os arquivos fora do docroot (ADR 0001), o servidor web não os serve. Antes, qualquer pessoa com a URL baixava, mesmo sem login, inclusive itens da lixeira.

## Decisão
`?action=download&file=...[&inline=1]` exige login, valida o caminho via `FileStorage`, define `Content-Type` por **lista branca** (jpg, png, gif, webp, pdf, mp4, txt), envia `nosniff`, e suporta `Range`. Tudo fora da lista (SVG, XML, HTML...) sai como `attachment`.

## Alternativas descartadas
- `X-Sendfile`/`X-Accel-Redirect`: mais rápido, mas depende do servidor (Apache/nginx); IIS não tem equivalente simples.
- Manter `uploads/` no docroot com `.htaccess`: inseguro fora do Apache e sem controle de login.

## Consequências
- (+) Controle de acesso e auditoria (`DOWNLOAD` no log) em um só lugar; fim do XSS por SVG.
- (−) PHP passa a transmitir arquivos grandes (leitura em blocos de 128 KB, sessão liberada antes). Para volume alto, avaliar `X-Sendfile`.
