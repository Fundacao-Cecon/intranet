# Kanban — Intranet FCECON

Retrato do quadro em **26/10/2026** (Sprint 0 em andamento). O quadro vivo, com arrastar e soltar, está no artefato "Kanban da Intranet" publicado na sessão; este arquivo é o registro versionado. Processo e cerimônias: [scrum.md](scrum.md). Plano: [roadmap.md](roadmap.md).

## Como ler

- **Colunas:** Backlog do produto → Backlog da sprint → Em andamento → Em revisão → Concluído.
- **Pontos:** história em pontos de Fibonacci (1, 2, 3, 5, 8, 13). Referências em [scrum.md](scrum.md#estimativa).
- **Épicos:** E0 a E5 são as fatias do [roadmap](roadmap.md). Cada tarefa leva o id `Txxx`, que também vai no nome da branch (`feature/T014-sanctum-login`).
- **Limite de trabalho em andamento (WIP):** no máximo 2 tarefas em *Em andamento* e 2 em *Em revisão* por pessoa.

## Resumo

| Coluna | Tarefas | Pontos |
|---|--:|--:|
| Backlog do produto | 62 | 235 |
| Backlog da sprint | 1 | 1 |
| Em andamento | 0 | 0 |
| Em revisão | 6 | 21 |
| Concluído | 4 | 9 |
| **Total** | **73** | **266** |

## Sprints

| Sprint | Meta | Release | Tarefas | Pontos |
|---|---|:-:|--:|--:|
| Sprint 0 — Documentação e 0.2.1 | Decisão registrada, plano e documentação publicados, correção de segurança commitada. | 0.2.1 | 6 | 14 |
| Sprint 1 — Fundação | Alguém entra pela API a partir do Next, com CI verde e o esquema adotado sem quebrar o legado. | — | 9 | 25 |
| Sprint 2 — Login endurecido | Os 7 cenários da ADR 0005 passam no Laravel e o login do Next está acessível. | 0.3.0 | 10 | 28 |
| Sprint 3 — Leitura (API) | A API lista, baixa e compacta respeitando a regra de acesso por pasta, com os cenários RH, travessia e UTF-8. | — | 7 | 27 |
| Sprint 4 — Busca e leitura (Next) | Dá para navegar, buscar e visualizar arquivos pelo Next com a11y verde. | 0.4.0 | 9 | 27 |
| Sprint 5 — Escrita (API) | Upload, pastas, mover e lixeira funcionam na API, com concorrência testada. | — | 7 | 28 |
| Sprint 6 — Admin e telas de escrita | Painel Master e telas de escrita no Next; backup e restauração por artisan. | — | 6 | 28 |
| Sprint 7 — Servidor real e piloto | Smoke verde no servidor real e piloto iniciado em /novo. | 0.5.0 | 8 | 24 |
| Sprint 8 — Troca para a nova stack | Troca do / feita, legado aposentado, tag 1.0.0. | 1.0.0 | 5 | 17 |
| Sprint 9 — Mural de avisos | Avisos com validade, grupo e leitura. | 1.1.0 | 1 | 8 |
| Sprint 10 — Versões de arquivo | Histórico de versões com restauração. | 1.2.0 | 1 | 13 |
| Sprint 11 — Link temporário | Compartilhar arquivo por link que expira, com auditoria. | 1.3.0 | 1 | 8 |
| Sprint 12 — TOTP e PostgreSQL | 2FA para admins e banco PostgreSQL por configuração. | 1.4.0 | 3 | 19 |

Os pontos são hipótese de planejamento: recalibre com a velocidade real depois das Sprints 1 e 2 ([scrum.md](scrum.md#capacidade-e-velocidade)).

## Backlog do produto

| Id | Sprint | Épico | Tarefa | Pts | Tipo e nota |
|---|:-:|---|---|--:|---|
| T015 | S1 | E1 Fundação e login | Auditoria: tabela logs e retenção de 180 dias | 2 | feature |
| T016 | S1 | E1 Fundação e login | Sessão: ociosa de 8 h e teto absoluto de 12 h (middleware) | 3 | segurança |
| T017 | S1 | E1 Fundação e login | Dependabot ou Renovate para api/ e web/ | 1 | infra |
| T018 | S1 | E1 Fundação e login | GET /api/v1/health e logs de aplicação em JSON | 2 | infra — Revisão de arquitetura, D5 e D6 |
| T020 | S2 | E1 Fundação e login | Limitador de login em camadas (usuário+IP e IP) com hash fictício | 5 | segurança |
| T021 | S2 | E1 Fundação e login | Alerta por usuário (LOGIN_ALERTA) sem bloquear o dono da conta | 2 | segurança |
| T022 | S2 | E1 Fundação e login | Trocar a senha derruba as outras sessões | 2 | segurança |
| T023 | S2 | E1 Fundação e login | Senha temporária: must_change_password e tela de troca | 3 | segurança |
| T024 | S2 | E1 Fundação e login | Política de senha: 10+ caracteres e lista de proibidas | 2 | segurança |
| T025 | S2 | E1 Fundação e login | Login sem diferença de caixa (índice único em lower(username)) | 2 | segurança |
| T026 | S2 | E1 Fundação e login | Portar os testes da Fase 1 da matriz (62), incluindo os 7 todo | 5 | teste |
| T027 | S2 | E1 Fundação e login | Next: login e troca de senha, com acessibilidade e contraste | 5 | feature |
| T028 | S2 | E1 Fundação e login | Release 0.3.0 (changelog e tag) | 1 | release |
| T029 | S2 | E1 Fundação e login | Aplicar o imagotipo e o favicon no login e no layout do Next | 1 | feature — docs/design-system/marca.md; aprovação da comunicação pendente |
| T030 | S3 | E2 Leitura e busca | Portar FileStorage (resolve, safeItem, cleanName) com testes de travessia, UTF-8 e caixa | 5 | segurança |
| T031 | S3 | E2 Leitura e busca | Policy de acesso por pasta (interseção na cadeia) e cenários RH | 5 | segurança |
| T032 | S3 | E2 Leitura e busca | API: listar pasta com autoria em uma consulta | 3 | feature |
| T033 | S3 | E2 Leitura e busca | API: árvore de pastas | 3 | feature |
| T034 | S3 | E2 Leitura e busca | API: download com Range e lista de MIME (SVG sempre como anexo) | 5 | feature |
| T035 | S3 | E2 Leitura e busca | API: ZIP de seleção | 3 | feature |
| T036 | S4 | E2 Leitura e busca | OpenAPI (Scramble) e tipos TypeScript gerados | 3 | infra |
| T037 | S3 | E2 Leitura e busca | PathIndex e eventos de caminho com testes de caixa e UTF-8 | 3 | segurança — Revisão de arquitetura, D1 e D2; antes de renomear e mover |
| T040 | S4 | E2 Leitura e busca | API: busca global por nome, podando pastas fechadas (limite de 200) | 5 | feature |
| T041 | S4 | E2 Leitura e busca | Next: lista, migalhas e tema claro/escuro | 5 | feature |
| T042 | S4 | E2 Leitura e busca | Next: árvore lateral com teclado e ARIA | 5 | feature |
| T043 | S4 | E2 Leitura e busca | Next: visualizador de imagem, PDF e vídeo | 3 | feature |
| T044 | S4 | E2 Leitura e busca | Next: caixa de busca e resultados | 2 | feature |
| T045 | S4 | E2 Leitura e busca | axe e contraste nas telas de leitura | 2 | teste |
| T046 | S5 | E2 Leitura e busca | Desempenho: 400 itens em menos de 3 s na API | 2 | teste |
| T047 | S4 | E2 Leitura e busca | Release 0.4.0 | 1 | release |
| T048 | S4 | E2 Leitura e busca | ADR da busca: medição e limiar para índice FTS5 | 1 | docs — Revisão de arquitetura, D4 |
| T050 | S5 | E3 Escrita e admin | Eventos de caminho (PathRenamed, PathRemoved) e listeners | 5 | feature |
| T051 | S5 | E3 Escrita e admin | Upload com reserva atômica de nome e lista de extensões | 5 | feature |
| T052 | S5 | E3 Escrita e admin | Nova pasta e renomear | 3 | feature |
| T053 | S5 | E3 Escrita e admin | Mover itens (sem entrar em si mesma, regra e autoria acompanham) | 5 | feature |
| T054 | S5 | E3 Escrita e admin | Lixeira: enviar, restaurar na origem, excluir e esvaziar | 5 | feature |
| T055 | S5 | E3 Escrita e admin | Concorrência em Pest: 8 uploads e 2 admins se excluindo | 3 | teste |
| T060 | S6 | E3 Escrita e admin | Usuários: criar e excluir, com o último admin protegido por transação | 3 | feature |
| T061 | S6 | E3 Escrita e admin | Grupos e acesso por pasta | 5 | feature |
| T062 | S6 | E3 Escrita e admin | Painel de logs | 2 | feature |
| T063 | S6 | E3 Escrita e admin | Comandos artisan: backup, restore e create-user | 5 | feature |
| T064 | S6 | E3 Escrita e admin | Next: upload com progresso, pasta, renomear, mover e lixeira | 8 | feature |
| T065 | S6 | E3 Escrita e admin | Next: Painel Master (usuários, grupos, acesso e logs) | 5 | feature |
| T070 | S7 | E4 Servidor real e troca | Nginx ou Apache com php-fpm e HTTPS (proxy de /api e /novo) | 5 | infra — Depende do servidor real |
| T071 | S7 | E4 Servidor real e troca | artisan intranet:smoke (docroot, cabeçalhos, cookies, login) | 3 | teste |
| T072 | S7 | E4 Servidor real e troca | Upload de 500 MB medido no servidor real | 2 | teste |
| T073 | S7 | E4 Servidor real e troca | Font Awesome local e imagens em WebP | 2 | dívida |
| T074 | S7 | E4 Servidor real e troca | CSP com hashes gerados no build do Next | 5 | segurança |
| T075 | S7 | E4 Servidor real e troca | Playwright: arrastar e soltar e celular | 3 | teste |
| T076 | S7 | E4 Servidor real e troca | Fechar a matriz de paridade (100 %) e publicar a 0.5.0 | 2 | release |
| T077 | S7 | E4 Servidor real e troca | Iniciar o piloto em /novo com 3 a 5 pessoas | 2 | release |
| T080 | S8 | E4 Servidor real e troca | Piloto: acompanhar logs e corrigir os achados | 5 | feature |
| T081 | S8 | E4 Servidor real e troca | Ensaiar backup, restauração e a volta atrás | 3 | teste |
| T082 | S8 | E4 Servidor real e troca | Trocar o / e aposentar o legado (tag 1.0.0) | 5 | release |
| T083 | S8 | E4 Servidor real e troca | Entregar e apagar senhas-novas.txt e _backup_original/ | 1 | segurança |
| T084 | S8 | E4 Servidor real e troca | Docs finais: README, operação, segurança e fluxos | 3 | docs |
| T090 | S9 | E5 Módulos novos | Mural de avisos: ADR, testes, API e tela | 8 | feature |
| T091 | S10 | E5 Módulos novos | Versões de arquivo: ADR, API, tela e backup | 13 | feature |
| T092 | S11 | E5 Módulos novos | Link temporário: ADR com modelo de ameaças, API e tela | 8 | feature |
| T093 | S12 | E5 Módulos novos | TOTP para administradores | 8 | segurança |
| T094 | S12 | E5 Módulos novos | Migrar para PostgreSQL (revisitar a ADR 0007) | 8 | infra |
| T095 | S12 | E5 Módulos novos | Atualização anual de Laravel, Next, Node e PHP | 3 | dívida |

## Backlog da sprint

| Id | Sprint | Épico | Tarefa | Pts | Tipo e nota |
|---|:-:|---|---|--:|---|
| T006 | S0 | E0 Documentação e 0.2.1 | Abrir o PR da 0.2.1 e publicar a tag v0.2.1 | 1 | release — Envolve push: precisa do OK |

## Em andamento

_Vazia._

## Em revisão

| Id | Sprint | Épico | Tarefa | Pts | Tipo e nota |
|---|:-:|---|---|--:|---|
| T004 | S0 | E0 Documentação e 0.2.1 | Escrever roadmap, transição, Scrum e kanban | 3 | docs |
| T005 | S0 | E0 Documentação e 0.2.1 | Ajustar ADRs 0003, 0004 e 0007, README, pendências e estratégia de testes | 2 | docs |
| T011 | S1 | E1 Fundação e login | Criar api/ (Laravel 13) e web/ (Next 16, exportação estática) | 3 | infra — api/ Laravel 13.39 e web/ Next 16.4.3 (export estático, basePath /novo); build e testes padrão verdes |
| T012 | S1 | E1 Fundação e login | CI por pasta: Pest, Larastan e Pint na API; Vitest e build no front | 3 | infra — api.yml (Pest 8.3/8.4, Pint, Larastan 6, audit), web.yml (ESLint, tsc, Vitest, build, audit), legado com filtro de pasta; tudo verde local; workflows ainda não rodaram no GitHub |
| T013 | S1 | E1 Fundação e login | Migration baseline que adota o esquema atual, com SQLite em WAL | 5 | feature — Baseline idempotente + WAL, busy_timeout 15 s, IMMEDIATE; 5 testes (esquema igual ao do legado, dados preservados, coexistência) |
| T014 | S1 | E1 Fundação e login | Sanctum SPA: login, logout e me, com testes Pest | 5 | feature — Sanctum 4.3 por cookie + CSRF; /api/v1/auth/login, logout, me; 16 testes Pest; conferido por HTTP real |

## Concluído

| Id | Sprint | Épico | Tarefa | Pts | Tipo e nota |
|---|:-:|---|---|--:|---|
| T001 | S0 | E0 Documentação e 0.2.1 | Corrigir caixa e UTF-8 nas regras de pasta (0.2.1) | 3 | segurança — Commit 3e9d9a4; 5 testes novos |
| T002 | S0 | E0 Documentação e 0.2.1 | Avaliar a stack e registrar a ADR 0008 | 3 | docs — Laravel + Next.js em fatias |
| T003 | S0 | E0 Documentação e 0.2.1 | Gerar a matriz de paridade dos testes | 2 | docs — 189 testes PHP e 44 declarações JS |
| T010 | S1 | E1 Fundação e login | Instalar Composer e Node 24 LTS | 1 | infra — Node 24.20 já estava instalado; Composer 2.10.3 (SHA-256 conferido) em D:/php/tools |
