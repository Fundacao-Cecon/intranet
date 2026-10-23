# Estratégia de testes

Plano de testes da Intranet (PHP 7.4+/SQLite, sem framework): o que existe, o que foi medido em 23/10/2026 e o que ainda falta.

## 1. Resumo

| Camada | Suíte | Testes | O que prova |
|---|---|--:|---|
| Login com sessão real | `tests/run.php` (`login`) | 18 | Expiração, teto absoluto, tempo de resposta, logs, bloqueio, cookie, tela de login |
| Unitário | `tests/unit_test.php` (`unit`) | 41 | `FileStorage`, `FileMeta`, `Logger`, `Auth`, `Csrf`, fronteiras do limite de tentativas |
| Integração HTTP | `tests/http_test.php` (`http`) | 49 | Sistema **no ar**: login, listagem, download/Range, ZIP, permissões, upload, lixeira, usuários, desempenho, 4 configurações de servidor (primeiro uso, HTTPS forçado, erro 500, `upload_max_filesize`) |
| Concorrência | `tests/concurrency_test.php` (`conc`) | 4 | Processos paralelos: nomes no upload, SQLite com 8 escritores, dois admins se excluindo |
| Backup/restauração | `tests/backup_test.php` (`backup`) | 10 | Backup **com o sistema ligado**, manifest SHA-256, adulteração, restauração, sistema sobe na pasta restaurada |
| Servidor real (script) | `tests/smoke_test.php` (`smoke`) | 4 | Prova que `bin/smoke.php` aprova docroot certo e **reprova docroot errado** |
| JavaScript + acessibilidade | `tests/js_test.php` (`js`) | 53 | `app.js`/`theme.js`/`login.js` em jsdom, auditoria axe (WCAG 2.1 A/AA) de 6 telas, contraste 4,5:1 da paleta (claro/escuro) |
| **Total automatizado** | `php tests/all.php` | **179** | ~95 s; verde em **PHP 7.4 e 8.3** |
| Escritos antes do código | `tests/pending_phase2_test.php` (`p2`) | 7 `todo` | Fase 2 da ADR-0005; aparecem como "pend" e não reprovam |
| Opt-in | `tests/large_test.php` (`large`) | 4 | Arquivo grande com `memory_limit=64M` no servidor |
| Medição | `tests/coverage.php` | — | **96,0 %** das linhas de `src/` (1.004 de 1.046), com piso por arquivo crítico |
| Estático | `phpstan.neon` | — | PHPStan **nível 5 sem erros** |

Comandos: `php tests/all.php` · `php tests/all.php unit http` · `php tests/all.php large` · `php tests/all.php --list` · `php -d extension=pcov tests/coverage.php --min=90 --lines` · `php phpstan.phar analyse`. Detalhes de instalação em [operacao.md](operacao.md).

Princípio: a confiança vem sobretudo da **camada HTTP** (o sistema é fino e o risco está na fronteira: permissão, caminho, sessão). Unitários cobrem regras com muitas combinações; processos paralelos cobrem o que HTTP serial não alcança; jsdom/axe cobrem o JavaScript e a acessibilidade sem navegador; o navegador real fica para o que nenhum desses vê (layout, contraste calculado).

## 2. Risco × impacto (o que foi priorizado)

| Área | Impacto | Prob. | Prio. | Cobertura principal |
|---|---|---|---|---|
| Permissão (user × admin), CSRF, login obrigatório | Alto | Média | P0 | HTTP |
| Caminhos (`?dir=`, `?file=`, upload, rename) | Alto | Média | P0 | Unitário + HTTP |
| Sessão, bloqueio de login, senha | Alto | Média | P0 | Login + HTTP + unit |
| Perda de dados: lixeira, metadados, backup | Alto | Média | P0 | HTTP + backup |
| Concorrência (uploads, SQLite, admins) | Médio-alto | Baixa | P1 | conc |
| Download/ZIP/Range, upload (extensões, colisão, 413) | Médio | Média | P1 | HTTP + large |
| Escape de HTML (XSS) | Alto | Baixa | P1 | HTTP + JS |
| JavaScript, teclado, acessibilidade, contraste | Médio | Média | P2 | js |
| Configuração do servidor (docroot, `php.ini`) | Alto | Média | P1 | `bin/smoke.php` |

## 3. Como cada lacuna da seção 6 original foi tratada

| Lacuna | Resultado |
|---|---|
| Cobertura de linhas não medida | ✅ PCOV instalado; `tests/coverage.php` mescla processos (inclusive o servidor HTTP e os CLI). **96,0 %** total; pisos: `FileStorage`/`FileMeta`/`Csrf`/`Auth`/`Application` ≥ 95 %, `FilesController`/`TrashController` ≥ 90 %, `Backup` ≥ 85 %. O que sobra descoberto são ramos de falha do sistema operacional (`rename()`/`mkdir()` falhando) e linhas `}` |
| Sem CI | ✅ `.github/workflows/tests.yml`: PHP 7.4/8.1/8.3/8.4 (Ubuntu) + 8.3 (Windows), cobertura (meta 90 %), PHPStan, job manual de arquivo grande. **Não foi executado no GitHub** (só validado como YAML e reproduzido localmente) |
| PHP 7.4 nunca executado | ✅ PHP 7.4.33 instalado em `D:\php74`; as 179 verificações passam. Achado: `proc_open` com string quebra no `cmd.exe` do 7.4 (corrigido, usa array) |
| Servidor real (Apache/IIS) | 🟡 `bin/smoke.php <url>`: 40+ verificações só por HTTP (docroot, 20 caminhos internos, cabeçalhos, cookie, HSTS, `display_errors`, login opcional). Testado contra instalação certa e **errada**. **Falta rodar no servidor de verdade** |
| JavaScript só à mão | ✅ jsdom sobre o HTML real de cada tela. Playwright (navegador real automatizado) **não foi instalado** (~200 MB); o roteiro manual de 23/10 continua válido |
| Upload grande / Range | 🟡 `large_test.php`: 40 MB com servidor a 64 MB de memória: tamanho e SHA-256 idênticos, Range correto, download a ~300 MB/s. O servidor embutido do PHP no Windows recebe uploads a ~1 MB/s (300 MB passa de 15 min), então **medir em Apache/IIS** |
| Concorrência | ✅ `conc` + 2 defeitos corrigidos (ver §5) |
| Backup/restauração | ✅ `bin/backup.php`, `bin/restore.php`, `App\Backup` com manifest e verificação; 10 testes |
| Acessibilidade | ✅ axe: 6 telas sem violações (eram 8 tipos); contraste: 8 combinações corrigidas; Esc, foco e `role=dialog` nos modais |
| Fase 2 da ADR-0005 | ✅ 7 testes escritos antes do código (`todo`), já mostrando a falha real (ex.: 30 tentativas simultâneas gravam 10 falhas, não 5) |
| Análise estática | ✅ PHPStan nível 5 limpo. Nível 6 aponta 41 itens (tipos de array); nível 8, 77 |

## 4. Qualidade dos próprios testes (mutação)

Regras críticas foram quebradas de propósito numa cópia do código, uma por vez:

- **Rodada 1 (20 mutantes de segurança):** 19 mortos; 1 **equivalente** (prefixo do `realpath` sem `/` final: só se manifesta com symlink, que o Windows sem privilégio não cria; coberto no CI Linux). Dois sobreviventes iniciais viraram teste.
- **Login:** hash fictício, log com texto digitado, bloqueio sem dedupe, teto absoluto, `session_destroy` na expiração: todos mortos. O teste de tempo foi trocado por **medianas relativas** depois de ficar instável sob carga.
- **Contraste:** voltar o azul antigo no botão Entrar derruba `contrast.test.js`.
- **Limite conhecido:** a escolha de `reserveName` no controller de upload não é detectada por mutação (processos paralelos não passam pelo servidor embutido no Windows); é coberta por revisão e pelo teste da função.

Meta: **mutation score ≥ 90 %** nas regras de segurança.

## 5. Defeitos encontrados (e estado)

| # | Defeito | Achado por | Estado |
|---|---|---|---|
| 1 | `?file[]=x` virava a string "Array" | unitário | corrigido |
| 2 | POST `text/plain` pequeno respondia "arquivo excede o limite" | HTTP | corrigido |
| 3 | `display_errors=On` fazia o upload gigante responder 200 com JSON quebrado | HTTP | mitigado (`.user.ini`) |
| 4 | Celular: colunas cortadas, **Sair** inalcançável | navegador | corrigido |
| 5 | **Uploads simultâneos com o mesmo nome se sobrescreviam em silêncio** (8 uploads viravam 2 arquivos) | concorrência | **corrigido**: `FileStorage::reserveName` reserva o nome com `fopen('x')` |
| 6 | Dois admins podiam se excluir ao mesmo tempo e deixar o sistema sem admin | análise + conc | **corrigido**: `deleteUser` em `BEGIN IMMEDIATE` |
| 7 | Em modo debug o erro 500 saía com status 200 (aviso do PHP fechava os cabeçalhos) | HTTP | **corrigido**: buffer de saída em `Application::run` |
| 8 | Campos de login sem rótulo; botões de ícone sem nome; select sem nome; modais sem `role` | axe | **corrigido** |
| 9 | 8 combinações abaixo de 4,5:1 (links e botões azuis, verde e azul do Painel Master, tema escuro) | axe no navegador | **corrigido** (paleta separada em texto × fundo) |
| 10 | `app.js` usava `innerText` (frágil); sem Esc e sem retorno de foco nos modais | jsdom | **corrigido** |
| 11 | Scripts de `bin/` saíam com código 0 em erro de uso | smoke | **corrigido** (código 2) |
| 12 | `proc_open` com string falha no PHP 7.4 do Windows | PHP 7.4 | corrigido (nos testes) |
| 13 | **Corrida no limite de tentativas**: 30 tentativas simultâneas gravam 10 falhas em vez de 5 | `p2` (todo) | **aberto**: Fase 2 da ADR-0005 (`BEGIN IMMEDIATE`) |
| 14 | Toast de aviso sem estilo próprio | navegador | aberto (cosmético) |
| 15 | `rename()` e restaurar da lixeira ainda têm janela de corrida minúscula (checar e mover) | análise | aberto, risco baixo; só o upload tinha janela longa |

## 6. Lacunas e próximos passos (o que sobrou)

| Lacuna | Risco | Ação | Prio. |
|---|---|---|---|
| **CI nunca rodou no GitHub** | Falha só visível no 1º push | Subir a branch e conferir os 4 jobs; ajustar versões das actions se preciso | Alta |
| **Servidor real** (Apache/IIS, docroot, `php.ini`, HSTS) | Exposição de `src/`, `storage/` | Rodar `php bin/smoke.php https://intranet…` e `--login`; tratar todo FAIL | Alta |
| Fase 2 da ADR-0005 | Limite por IP, corrida, `auth_version`, senha, maiúsculas | Implementar até os 7 `todo` virarem `test()` | Alta |
| Upload grande em servidor de verdade | Timeout/memória não medidos fora do `php -S` | `php tests/large_test.php --mb=500` contra Apache/IIS (apontando para a URL de teste) | Média |
| Navegador real automatizado (Playwright) | Layout/contraste calculados só à mão | Instalar `@playwright/test` no CI; reaproveitar o roteiro da §7 | Média |
| `rename()`/restaurar atômicos | Sobrescrita em corrida rara | Reservar o destino como no upload | Baixa |
| PHPStan nível 6+ | Tipos de array sem anotação | Anotar `@param array<…>`; subir nível aos poucos | Baixa |
| Toast de aviso | Cosmético | Estilo `.toast.warning` | Baixa |

## 7. Roteiro manual no navegador (referência)

Executado em 23/10/2026 no Chromium do app, sem erros de console: login (alternar formulários, tema, erro), Painel Master → Usuários → voltar, fundo fecha, busca, renomear, ZIP, miniatura/lightbox, upload com barra e toast, lixeira (mover/restaurar na origem/excluir), perfil comum, **celular 375 px** e contraste (axe, claro e escuro, com modais abertos).

## 8. Critérios de saída (pronto para publicar)

- `php tests/all.php` verde em **PHP 7.4 e 8.3**, Linux e Windows (CI).
- Cobertura ≥ 90 % com os pisos de `tests/coverage.php`; PHPStan sem erros.
- `bin/smoke.php` **sem FAIL** no servidor real (WARN revisados).
- Nenhum defeito P0/P1 aberto em §5 e §6.

## 9. Convenções

- Todo bug corrigido ganha teste que **falha sem a correção** (confira por mutação).
- Teste de segurança verifica o **efeito** (disco, banco, corpo), não só o status.
- Dados só em pasta temporária (`mktmp()`); nunca o `storage/` real; usuários e senhas de teste só no código do teste.
- Funcionalidade nova começa por `todo()` e vira `test()` quando passar.
- Teste de tempo usa **medianas e razões**, nunca limites absolutos.
