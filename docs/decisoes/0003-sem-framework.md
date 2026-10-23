# ADR 0003 — Sem framework; camadas finas com autoload próprio

**Status:** aceita

## Contexto
A aplicação é pequena (um gerenciador de arquivos). Não há Composer nem processo de build no ambiente atual, e a equipe mantém o sistema em um servidor simples.

## Decisão
Separar em Controllers / Services / Views com autoload PSR-4 de ~10 linhas (`src/bootstrap.php`), sem dependências externas. Código compatível com PHP 7.4+.

## Consequências
- (+) Zero dependências, nada para instalar ou atualizar; deploy = copiar arquivos.
- (+) Estrutura compatível com PSR-4: migrar para Composer/PHPUnit depois é trivial (basta um `composer.json`).
- (−) Roteamento, CSRF e validação são caseiros; se o sistema crescer (muitas rotas/permissões), reavaliar um micro-framework (Slim) ou Laravel.
