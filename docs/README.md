# Documentação — Intranet FCECON

| Documento | Para que serve |
|---|---|
| [roadmap.md](roadmap.md) | Para onde o projeto vai: fases da migração para Laravel + Next.js e módulos novos |
| [scrum.md](scrum.md) | Como as fatias viram sprints: papéis, cerimônias, DoR/DoD, estimativa e plano de sprints |
| [kanban.md](kanban.md) | Quadro de tarefas (retrato versionado; o quadro vivo é um artefato) |
| [transicao.md](transicao.md) | Como o PHP atual e a nova stack convivem; runbooks de piloto, troca e volta atrás |
| [matriz-de-paridade.md](matriz-de-paridade.md) | Cada teste do sistema atual ligado ao seu substituto na nova stack |
| [revisao-de-arquitetura.md](revisao-de-arquitetura.md) | Revisão de 26/10/2026: achados do desenho atual, arquitetura-alvo com diagramas, decisões D1 a D6 e riscos |
| [design-system/marca.md](design-system/marca.md) | Imagotipo da Intranet FCECON: conceito, cores, área de respeito, tamanhos mínimos, versões claro/escuro e uso no HTML |
| [arquitetura.md](arquitetura.md) | Camadas, fluxo de uma requisição, rotas (sistema atual) |
| [estrategia-de-testes.md](estrategia-de-testes.md) | Plano de testes: pirâmide, risco, 257 testes automatizados, mutação, defeitos achados, lacunas e estratégia da nova stack |
| [fluxos.md](fluxos.md) | Fluxos do login à ação final (diagramas Mermaid + passo → `arquivo:linha`) |
| [fluxos/viz/index.html](fluxos/viz/index.html) | Grafo interativo do código (Trail-Code), arquivo único |
| [refatoracao.md](refatoracao.md) | Mapa "código antigo → código novo" (do `index.php` monolítico) |
| [seguranca.md](seguranca.md) | Achados do code review e status de cada correção |
| [banco-de-dados.md](banco-de-dados.md) | Tabelas SQLite e regras de migração |
| [usuarios-e-permissoes.md](usuarios-e-permissoes.md) | O que cada perfil pode fazer |
| [operacao.md](operacao.md) | Instalar, configurar servidor, backup, criar usuários |
| [pendencias.md](pendencias.md) | O que ainda não foi feito / não foi verificado |
| [git-flow.md](git-flow.md) | Fluxo de branches (Git Flow): features, releases, hotfixes, tags e comandos |
| [decisoes/](decisoes/) | ADRs — por que cada decisão estrutural foi tomada |

Origem: code review de `index.php` (monólito de ~930 linhas) seguido de reestruturação.
O arquivo original está preservado em `_backup_original/` (fora do git; apague depois de validar).
