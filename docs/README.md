# Documentação — Intranet FCECON

| Documento | Para que serve |
|---|---|
| [arquitetura.md](arquitetura.md) | Camadas, fluxo de uma requisição, rotas |
| [estrategia-de-testes.md](estrategia-de-testes.md) | Plano de testes: pirâmide, risco, 102 testes automatizados, mutação, defeitos achados e lacunas |
| [fluxos.md](fluxos.md) | Fluxos do login à ação final (diagramas Mermaid + passo → `arquivo:linha`) |
| [fluxos/viz/index.html](fluxos/viz/index.html) | Grafo interativo do código (Trail-Code), arquivo único |
| [refatoracao.md](refatoracao.md) | Mapa "código antigo → código novo" (do `index.php` monolítico) |
| [seguranca.md](seguranca.md) | Achados do code review e status de cada correção |
| [banco-de-dados.md](banco-de-dados.md) | Tabelas SQLite e regras de migração |
| [usuarios-e-permissoes.md](usuarios-e-permissoes.md) | O que cada perfil pode fazer |
| [operacao.md](operacao.md) | Instalar, configurar servidor, backup, criar usuários |
| [pendencias.md](pendencias.md) | O que ainda não foi feito / não foi verificado |
| [decisoes/](decisoes/) | ADRs — por que cada decisão estrutural foi tomada |

Origem: code review de `index.php` (monólito de ~930 linhas) seguido de reestruturação.
O arquivo original está preservado em `_backup_original/` (fora do git; apague depois de validar).
