# ADR 0003 — Sem framework; camadas finas com autoload próprio

**Status:** substituída pela [ADR 0008](0008-laravel-e-nextjs-em-fatias.md) (26/10/2026). Continua valendo para o **sistema atual** (legado), que fica congelado até a troca para Laravel + Next.js (Fase 4 do [roadmap](../roadmap.md)).

> O gatilho de reavaliação previsto abaixo ("se o sistema crescer, reavaliar um micro-framework ou Laravel") foi acionado pelo roadmap de módulos novos e pela API.

## Contexto
A aplicação é pequena (um gerenciador de arquivos). Não há Composer nem processo de build no ambiente atual, e a equipe mantém o sistema em um servidor simples.

## Decisão
Separar em Controllers / Services / Views com autoload PSR-4 de ~10 linhas (`src/bootstrap.php`), sem dependências externas. Código compatível com PHP 7.4+.

## Consequências
- (+) Zero dependências, nada para instalar ou atualizar; deploy = copiar arquivos.
- (+) Estrutura compatível com PSR-4: migrar para Composer/PHPUnit depois é trivial (basta um `composer.json`).
- (−) Roteamento, CSRF e validação são caseiros; se o sistema crescer (muitas rotas/permissões), reavaliar um micro-framework (Slim) ou Laravel.
