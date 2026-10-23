# Estratégia de testes

Plano de testes da Intranet (PHP 7.4+/SQLite, sem framework), o que já existe, o que foi executado em 23/10/2026 e o que ainda falta.

## 1. Resumo

| Camada | Suíte | Testes | Tempo | O que prova |
|---|---|--:|--:|---|
| Unitário | `tests/unit_test.php` | 39 | 4 s | Regras de `FileStorage`, `FileMeta`, `Logger`, `Auth` (usuários, senha, limites) e `Csrf`, sem servidor |
| Login (com sessão real) | `tests/run.php` | 18 | 6 s | Expiração, teto absoluto, tempo de resposta, logs, bloqueio, cookie, tela de login |
| Integração HTTP | `tests/http_test.php` | 45 | 20 s | Sistema **no ar** (`php -S`): login, listagem, download, ZIP, permissões, upload, lixeira, usuários, desempenho |
| E2E no navegador | manual (roteiro abaixo) | — | — | JavaScript, modais, lightbox, upload com progresso, toasts, tema, celular |
| **Total automatizado** | `php tests/all.php` | **102** | **30 s** | |

Rodar: `php tests/all.php` (tudo) ou `php tests/all.php unit http` (só algumas). Requisitos: PHP com `pdo_sqlite`, `zip`, `curl`; nenhum pacote do Composer. Sai com código 1 se algo falhar (pronto para CI).

Princípio: **a maior parte da confiança vem da camada HTTP**, porque o sistema é fino (controllers chamam serviços) e quase todo risco está na fronteira (permissão, caminho, sessão). Os unitários cobrem as regras que têm muitas combinações (nomes, caminhos, limites); o E2E cobre só o que o servidor não enxerga (JavaScript).

## 2. Como foi decidido o que testar (risco × impacto)

| Área | Impacto se falhar | Probabilidade | Prioridade | Tipo principal |
|---|---|---|---|---|
| Permissão (user × admin), CSRF, login obrigatório | Alto: apagar/enviar arquivos sem direito | Média | **P0** | HTTP |
| Caminhos (`?dir=`, `?file=`, upload, rename) | Alto: ler/gravar fora de `storage/` | Média | **P0** | Unitário + HTTP |
| Sessão, bloqueio de login, senha | Alto: invasão | Média | **P0** | Login + HTTP |
| Download/ZIP/Range | Médio: vazamento ou arquivo corrompido | Média | **P1** | HTTP |
| Lixeira e metadados (restaurar, renomear pasta) | Médio: perda de dados | Média | **P1** | HTTP + unitário |
| Upload (extensões, colisão, 413) | Médio | Média | **P1** | HTTP |
| Listagem/escape HTML (XSS) | Alto | Baixa | **P1** | HTTP |
| JavaScript (modais, upload, ZIP, busca) | Médio: UI quebrada | Média | **P2** | E2E navegador |
| Layout/responsivo/acessibilidade | Baixo-médio | Alta | **P2** | E2E manual |
| Configuração de servidor (Apache/IIS, PHP.ini) | Alto | Média | **P1** | Smoke no servidor real (ver §6) |

Foco: caminhos críticos de negócio, tratamento de erro, bordas, fronteiras de segurança e integridade de dados. Fora de escopo: getters triviais, o próprio PHP/SQLite.

## 3. O que cada suíte cobre

### 3.1 Unitários (`unit_test.php`)
- **FileStorage:** `resolve` com 20 tentativas de fuga (`..`, `....//`, `%2f`, barra invertida, byte nulo, caminho absoluto, `C:\`, UNC, irmã com mesmo prefixo, symlink*); `safeItem`, `cleanName`, `uniqueName`, `isHidden`, `metaPath`, `listDir` (ordem natural, ocultos), `delete` recursivo.
- **FileMeta:** autoria em lote (>500 itens), renomear pasta leva os filhos sem tocar nas irmãs de prefixo (`Doc` × `Docs`), curingas `%` e `_` no nome, `removed`, origem da lixeira.
- **Logger:** truncamento em 500, usuário via provedor, `purge` por idade.
- **Auth:** `createUser` (login válido/inválido, role, senha curta, duplicado, tentativa de SQL injection), `resetPassword`, `changePassword`, `deleteUser` (si mesmo, inexistente, não expõe hash), limite de tentativas nas fronteiras (4 ok, 5ª bloqueia, por IP, expira, caixa).
- **Csrf:** formato, estabilidade, `regenerate`, aceitar campo e header, recusar ausente/errado/vazio/array.
- \*O teste de symlink é pulado no Windows sem privilégio; roda em Linux/CI.

### 3.2 Login com sessão real (`run.php`)
Troca de ID no login, expiração ociosa **sem perder o token CSRF** (era o 419 do review), aviso "Sua sessão expirou", teto absoluto de 12 h, usuário apagado, logout, tempo de resposta igual para login inexistente, hash fictício com mesmo custo, log sem texto digitado, 1 log de bloqueio por janela, `gc_maxlifetime`/cookie em processo isolado, tela de login (botões, `?form=change`).

### 3.3 Integração HTTP (`http_test.php`)
Sobe `php -S` numa porta livre com `post_max_size=1M` e `display_errors=0` (como produção), dados temporários e 7 usuários.
- **Infra:** cabeçalhos de segurança, cookie HttpOnly/SameSite, 16 caminhos internos → 404 (`/database.db`, `/src/…`, `/.git/config`, `%2e%2e`…), assets, GET de ação de escrita não executa, 419, 413 (JSON se XHR), POST `text/plain` não vira 413.
- **Login/sessão:** mesma mensagem para senha errada e usuário inexistente, anti-fixation, bloqueio, logout invalida o cookie, troca de senha (pública e logada), expiração (envelhecendo o arquivo de sessão), usuário apagado perde a sessão.
- **Listagem:** ocultos, escape de `'` e `&`, breadcrumb, traversal em 14 variações, `mode=trash`, elementos de admin ausentes para `user`.
- **Download/ZIP:** inline só para tipos permitidos, SVG sempre baixa, `filename*=UTF-8`, `Range` (206, sufixo, aberto, 416, inválido), 11 variações de arquivo proibido, lixeira só admin, ZIP só com itens válidos (inclusive ocultos da raiz), log de download.
- **Permissões:** usuário comum tenta as 9 ações de admin: todas negadas, **disco e banco idênticos antes/depois**.
- **Admin:** pasta (nomes inválidos), upload (`.exe`, `.php`, `.phtml`, `.htaccess`, `web.config` bloqueados; colisão `(1)`; nome com `../`; `shell.php.jpg` nunca executa), renomear, ciclo completo da lixeira (restaurar na origem, na raiz, nome ocupado, purge, esvaziar), usuários, logs com HTML malicioso escapado, hash nunca no HTML, 400 itens em ~0,2 s.

### 3.4 E2E no navegador (executado 23/10/2026, Chromium do app)
| Verificação | Resultado |
|---|---|
| Login: alternar para "Nova Senha" e voltar, tema escuro persiste após reload, erro de senha | ok |
| Painel Master → Usuários → voltar; clique no fundo fecha; modal de senha | ok |
| Busca filtra; "Renomear" pré-preenche; ZIP mostra "Baixar N (ZIP)"; selecionar todos | ok |
| Miniatura e lightbox (imagem carrega, fecha pelo X e pelo fundo) | ok |
| Upload de 2 arquivos (1 permitido + `.exe`): barra 100 %, toast "1 enviados, 1 bloqueados.", recarrega e mostra autor | ok |
| Mover para lixeira → toast; lixeira → restaurar volta à pasta original; excluir definitivo com confirmação | ok |
| Perfil comum: nenhum controle de admin, `?mode=trash` ignorado | ok |
| Console do navegador sem erros | ok |
| **Celular (375 px)** | **falhou** → corrigido (ver §5) |

## 4. Qualidade dos próprios testes (teste de mutação)

Para provar que os testes pegam defeitos, 20 regras críticas foram quebradas de propósito, uma por vez, numa cópia do código:

- **19 de 20 mutantes mortos** (a suíte falhou): traversal em `resolve`, `safeItem` com array, `removed()` sem barra, `LIKE` sem escape, CSRF desligado, `requireAdmin` desligado, download sem login, SVG liberado, ocultos no download e no ZIP, XSS na listagem, restaurar ignorando a origem, lixeira sobrescrevendo, upload sem lista de extensões, `Range` ignorado, cookie sem HttpOnly, role livre, autoexclusão, token CSRF vazio.
- **2 sobreviveram na 1ª rodada** e viraram teste (ZIP com ocultos de verdade; `LIKE` — erro do script).
- **1 equivalente:** comparar o prefixo do `realpath` sem `/` final. Só se manifesta com um symlink dentro de `uploads/` apontando para uma pasta irmã; no Windows sem privilégio não dá para provar. Fica coberto no CI Linux.

Meta: **mutation score ≥ 90 %** nas regras de segurança (atual: 95 %).

## 5. Defeitos encontrados nesta rodada

| # | Defeito | Como apareceu | Estado |
|---|---|---|---|
| 1 | `?file[]=x` virava a string "Array" (aviso do PHP) | unitário `safeItem` | **corrigido** (`FileStorage::safeItem` recusa não-string) |
| 2 | Qualquer POST com corpo não-formulário (ex.: `text/plain`) respondia "arquivo excede `post_max_size`" | HTTP | **corrigido** (`Application::dispatch` compara `CONTENT_LENGTH` com o limite real) |
| 3 | Com `display_errors=On` o aviso de startup do PHP vai para o corpo: status 200 em vez de 413 e JSON quebrado no upload gigante | HTTP | **mitigado**: `display_errors = Off` em `public/.user.ini`; documentar no `php.ini` de produção |
| 4 | Celular: colunas cortadas por `overflow:hidden`, botão **Sair** e ações inacessíveis | E2E 375 px | **corrigido** (`@media (max-width:768px)` em `app.css`) |
| 5 | Toast de aviso (`.toast.warning`) sem estilo (só `success`/`error` têm borda colorida) | E2E | pendente (cosmético) |
| 6 | Guarda "não excluir o último admin" nunca dispara pela interface (quem exclui já é admin); e dois admins podem se excluir ao mesmo tempo e deixar zero | análise | pendente (risco baixo; resolver com transação) |

## 6. Lacunas e próximos passos

| Lacuna | Risco | Ação sugerida | Prioridade |
|---|---|---|---|
| **Cobertura de linhas** não medida (sem Xdebug/PCOV) | Não se sabe o que não é executado | Instalar PCOV no CI; meta **≥ 85 % linhas** em `src/` e 100 % em `FileStorage`, `Auth`, `Csrf`, `Application::dispatch` | Alta |
| **Sem CI** | Regressão entre commits | Workflow rodando `php tests/all.php` em PHP **7.4, 8.1, 8.3** (Ubuntu, com symlink) a cada push/PR | Alta |
| PHP 7.4 nunca executado | Quebra no servidor real | Matriz do CI | Alta |
| Servidor real (Apache/IIS): `.htaccess`, `web.config`, docroot, `php.ini` | Exposição de `src/`, `storage/` | **Smoke no servidor**: roteiro de [pendencias.md](pendencias.md) + `curl -I` nos 16 caminhos da §3.3 | Alta |
| JavaScript só testado à mão | Regressão silenciosa de UI | Playwright (login → painel → upload → lixeira) no CI; 1 teste por fluxo do [fluxos.md](fluxos.md) | Média |
| Upload grande (centenas de MB), `Range` de vídeo em player real | Timeout/memória | Teste manual com 500 MB e MP4; medir memória | Média |
| Concorrência (dois admins, upload simultâneo, SQLite `locked`) | Perda de metadado | Teste com N processos paralelos escrevendo em `files_meta`/`logs` | Média |
| Backup/restauração de `storage/` | Perda de dados | Exercício trimestral: restaurar numa pasta nova e subir o sistema | Média |
| Acessibilidade (WCAG 2.1 AA) | Usuários com limitação | Auditoria com axe; foco visível, contraste do tema escuro, rótulos de modal | Média |
| Fases 2/3 da ADR-0005 (limite por IP, `auth_version`, política de senha) | — | **Escrever os testes antes** (TDD): ver exemplos abaixo | Alta (quando implementar) |
| Análise estática | Bugs de tipo | PHPStan nível 5 no CI | Baixa |

## 7. Casos de teste de exemplo para o que vem a seguir

Escritos no mesmo formato das suítes (`test('nome', fn)`), prontos para virar código quando a funcionalidade existir.

**ADR-0005 Fase 2 (login)**
1. *Spraying:* 31 tentativas erradas, de logins diferentes, do mesmo IP ⇒ a 31ª (certa) é bloqueada; outro IP não é afetado.
2. *Limite por usuário não vira DoS:* 25 falhas de IPs diferentes contra `admin1` ⇒ `LOGIN_ALERTA` no log, mas o `admin1` legítimo ainda entra.
3. *Corrida:* 50 requisições paralelas de senha errada ⇒ no máximo 5 contam antes do bloqueio (transação).
4. *`auth_version`:* sessão A e B do mesmo usuário; A troca a senha ⇒ B cai na próxima requisição; A continua logada com ID novo.
5. *Senha temporária:* `bin/create-user.php --reset` liga `must_change_password` ⇒ login redireciona para a tela de troca e bloqueia o resto até trocar.
6. *Política:* `12345678`, `password`, igual ao login e `qwertyui` ⇒ recusadas; `Tr0ca-Segura-2026` ⇒ aceita.
7. *Maiúsculas:* criar `Adm` quando existe `adm` ⇒ recusado; login `ADM` entra na conta `adm`.

**Arquivos**
8. *Symlink:* `uploads/saida -> ..` ⇒ `?dir=saida` cai na raiz (roda só em CI Linux).
9. *Arquivo de 600 MB:* upload via XHR conclui sem estourar `memory_limit` (256 M).
10. *Dois admins, mesmo nome:* upload simultâneo de `a.txt` ⇒ um fica `a.txt`, o outro `a (1).txt`, sem sobrescrever.
11. *Restore em cascata:* excluir `Pasta/Sub/arq.txt`, depois `Pasta/Sub`, depois `Pasta`; restaurar na ordem inversa ⇒ árvore idêntica à original.

**Operação**
12. *Backup:* copiar `storage/` com o sistema ligado, restaurar em outra pasta, subir ⇒ login, listagem e autoria idênticos.

## 8. Critérios de saída (pronto para publicar)

- `php tests/all.php` verde em **PHP 7.4 e 8.3**, em Linux e Windows.
- Cobertura de linhas ≥ 85 % (≥ 95 % nos arquivos P0); mutation score ≥ 90 % nas regras de segurança.
- Roteiro de fumaça rodado **no servidor real** com `curl -I` dos caminhos internos devolvendo 404/403.
- Playwright (ou roteiro manual da §3.4) sem falhas, inclusive em 375 px.
- Nenhum item P0/P1 aberto na §5/§6.

## 9. Convenções para novos testes

- Todo bug corrigido ganha um teste que **falha sem a correção** (confira por mutação, como na §4).
- Teste de segurança verifica o **efeito** (disco, banco, corpo da resposta), não só o status.
- Dados só em pasta temporária (`mktmp()`); nunca `storage/` real. Usuários e senhas de teste ficam no código do teste.
- Um teste = uma regra de negócio, com nome em português que descreve o comportamento esperado.
- Testes HTTP usam um cliente novo por cenário que mexe em sessão/bloqueio, para não vazar estado entre testes.
