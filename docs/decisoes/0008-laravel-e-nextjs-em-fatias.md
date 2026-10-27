# ADR 0008 — Laravel (API) + Next.js (front), migrando em fatias

**Status:** aceita
**Data:** 2026-10-26
**Quem decidiu:** a pessoa que mantém o projeto, depois de comparar as opções abaixo
**Substitui:** [ADR 0003](0003-sem-framework.md) (sem framework), a partir da Fase 1 do [roadmap](../roadmap.md). **Adia:** [ADR 0007](0007-migracao-postgresql.md) (PostgreSQL).

## Contexto

O sistema (release 0.2.0) é um gerenciador de arquivos em PHP puro, SQLite e JavaScript puro: 19 rotas, cerca de 750 linhas de JS, 257 testes automatizados, 96,0 % de cobertura de linhas, PHPStan nível 5. As ADRs 0003 e 0004 deixaram gatilhos de revisão ("se o sistema crescer, reavaliar um framework").

O gatilho disparou por produto, não por problema técnico. Os motivos informados para mudar foram:

1. **Novos módulos e uma API** (busca global, versões de arquivo, mural de avisos, link temporário e o que vier depois).
2. **Visual e experiência de uso mais modernos** do que HTML gerado no servidor com JS puro permite com conforto.

Restrições: o ambiente de produção é **Linux com Apache ou Nginx**; o sistema precisa continuar no ar durante a mudança; as regras de segurança de hoje (travessia de caminho, permissão por pasta, CSRF, limite de tentativas) estão provadas por testes e não podem se perder.

## Decisão

1. **Backend:** Laravel 13 (PHP 8.3 ou superior) como **API JSON**, no diretório `api/` do mesmo repositório.
2. **Front:** Next.js 16 com **exportação estática** (`output: 'export'`), no diretório `web/`. Páginas só de cliente; **sem** Server Functions e **sem** autenticação em middleware do Next. Servido como arquivos estáticos pelo Nginx/Apache, **sem Node em produção**.
3. **Autenticação:** Laravel Sanctum no modo SPA (cookie de sessão + `XSRF-TOKEN`), com Next e API **no mesmo domínio** atrás do proxy.
4. **Banco:** continua SQLite durante a migração; PostgreSQL depois da troca (a ADR 0007 fica como plano, com o gatilho original). O Laravel torna a troca uma questão de configuração e migrations.
5. **Migração em fatias** (estrangulamento), não em reescrita única. O legado fica **congelado** (só correções de segurança) e continua atendendo `/` até a troca; a pilha nova nasce em `/api` e `/novo`. Detalhes de convivência em [transicao.md](../transicao.md).
6. **Contrato de testes:** cada teste do legado tem um substituto na nova stack, listado na [matriz de paridade](../matriz-de-paridade.md). Um teste legado só se aposenta quando o substituto passa.
7. **Os módulos novos** (mural, versões, link temporário) só são construídos **depois da troca**, direto na stack nova, para não implementar duas vezes.

## Opções consideradas

### A. Ficar na stack atual
| Dimensão | Avaliação |
|---|---|
| Esforço | Nenhum para manter; cada módulo novo é feito à mão (roteamento, validação, migrações, fila) |
| Risco | Baixo hoje, crescente: código caseiro de segurança cresce junto com o sistema |
| Atende os motivos? | Parcialmente: não entrega API documentada nem UI moderna |

Boa enquanto o sistema for só um gerenciador de arquivos. Deixa de ser quando o roadmap pede API e vários módulos.

### B. PostgreSQL + NestJS + Next.js
| Dimensão | Avaliação |
|---|---|
| Esforço | Alto: troca de linguagem, de banco e de front ao mesmo tempo |
| Risco | Alto: reescrever em TypeScript regras de segurança hoje provadas em PHP |
| Churn | NestJS 12 migra os pacotes oficiais para ESM e, segundo o InfoQ, troca Jest, ESLint e Webpack por Vitest, oxlint e Rspack |

Descartada: troca três coisas de uma vez e descarta o conhecimento de PHP sem ganho que o Laravel não entregue.

### C. Laravel + Next.js em fatias — **escolhida**
| Dimensão | Avaliação |
|---|---|
| Esforço | Alto, mas diluído em fases que entregam valor e podem parar |
| Risco | Médio: duas pilhas convivendo no mesmo banco e nos mesmos arquivos por um tempo |
| Ganhos | Rotas, CSRF, validação, migrações, limitador de tentativas, políticas, eventos, filas e testes vêm prontos; mesma linguagem do time; OpenAPI gera os tipos do front |

O Laravel 13 foi lançado em março de 2026, exige PHP 8.3+, recebe correções até o 3º trimestre de 2027 e correções de segurança até o 1º trimestre de 2028 ([notas de versão](https://laravel.com/docs/13.x/releases)).

### D. Laravel + Next.js com troca única (projeto novo)
Mais rápido de desenhar, mas só se valida no fim e a volta é difícil. Descartada em favor das fatias.

### Sub-decisão: como publicar o Next.js

| Opção | Avaliação |
|---|---|
| **Exportação estática (escolhida)** | Sem processo Node para operar ou atualizar; sem Server Functions; casa com Sanctum por cookie no mesmo domínio |
| Servidor Node (SSR) | Mais um serviço; superfície de ataque do servidor do Next |

Contexto de segurança: em 2025 houve a CVE-2025-29927 (contorno de autenticação em *middleware* do Next, CVSS 9,1) e a CVE-2025-55182 (execução remota sem login nas Server Functions do React 19, CVSS 10; corrigida em React 19.0.1/19.1.2/19.2.1 e nas versões correspondentes do Next). Ambas atingem o padrão "autenticação feita dentro do Next". Esta decisão evita esse padrão por construção: a autenticação e toda regra de acesso ficam só na API.

## Análise de trade-offs

- **Continuidade × limpeza:** conviver com o legado custa regras de coexistência (banco compartilhado, mudanças só aditivas, congelamento), mas evita uma data de "virada" arriscada.
- **Uma reescrita de testes:** os testes de HTML/redirect não rodam contra JSON; o que se reaproveita são os **cenários**. Por isso a matriz de paridade existe e é o critério de pronto.
- **Next estático × recursos do Next:** abrimos mão de SSR, middleware e Server Functions. Não fazem falta para um app interno autenticado e é exatamente aí que estiveram as falhas críticas recentes.
- **Segurança de CSP:** a exportação estática injeta scripts de hidratação; uma CSP estrita exige hashes gerados no build. Tratar na Fase 4.

## Consequências

**Fica mais fácil**
- Adicionar módulos (migration + modelo + policy + controller + testes) e documentar a API (OpenAPI gerado, tipos TypeScript derivados).
- Endurecer o login: limitador, `logoutOtherDevices`, política de senha e troca forçada viram configuração e testes, não código caseiro (a Fase 2 da [ADR 0005](0005-endurecimento-do-login.md) passa a ser feita no Laravel).
- Trocar para PostgreSQL mais tarde.

**Fica mais difícil**
- Operar duas pilhas durante a transição e vigiar o que as duas escrevem no mesmo SQLite.
- Dependências para atualizar (Laravel e Next têm um ciclo maior por ano): planejar uma atualização anual e usar Dependabot ou Renovate.
- Ambiente de desenvolvimento: Composer, Node 24 LTS e, para os testes de navegador, Playwright.

**A revisitar**
- Se o SQLite começar a travar sob a carga das duas pilhas, antecipar o PostgreSQL ([ADR 0007](0007-migracao-postgresql.md)).
- Se a exportação estática limitar uma necessidade real (páginas públicas com SEO, por exemplo), reavaliar o Next com servidor, mantendo a autenticação só na API.
- Se a equipe perder capacidade de manter duas pilhas, parar na fase corrente (cada fase deixa o sistema funcionando).

## Ações

Ver [roadmap.md](../roadmap.md): Fase 0 (documentação e correção 0.2.1), Fase 1 (fundação e login), Fase 2 (leitura e busca), Fase 3 (escrita e admin), Fase 4 (servidor real e troca, tag 1.0.0), Fase 5 (módulos novos e PostgreSQL).

## Referências

- [Laravel 13 — notas de versão e política de suporte](https://laravel.com/docs/13.x/releases)
- [Laravel Sanctum — autenticação de SPA por cookie](https://laravel.com/docs/13.x/sanctum)
- [React — vulnerabilidade crítica em Server Components (CVE-2025-55182)](https://react.dev/blog/2025/12/03/critical-security-vulnerability-in-react-server-components)
- [Fastly — CVE-2025-29927, contorno de autorização no Next.js](https://fastly.com/blog/cve-2025-29927-authorization-bypass-in-next-js)
- [PHP — versões suportadas](https://php.net/supported-versions)
- [InfoQ — roteiro do NestJS 12 (ESM)](https://infoq.com/news/2026/04/nestjs-12-roadmap-esm)

Datas e versões foram conferidas em 26/10/2026, em parte por fontes secundárias; reconfirme nas páginas oficiais antes de decisões de prazo.
