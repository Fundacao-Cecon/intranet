# Matriz de paridade — testes do legado × nova stack

Esta tabela liga cada teste do sistema atual (PHP puro) ao teste que o substituirá em Laravel (Pest), Next.js (Vitest/Playwright) ou nos comandos `artisan`. É o **critério de aposentadoria** do legado: um teste legado só sai quando o substituto existe e passa. O plano completo está em [roadmap.md](roadmap.md) e a decisão na [ADR 0008](decisoes/0008-laravel-e-nextjs-em-fatias.md).

## Como ler

- *Fase*: em qual fase do roadmap o substituto deve existir (1 login, 2 leitura, 3 escrita e admin, 4 servidor real e troca).
- *Estado*: ⏳ ainda não portado; ✅ portado e passando; ➖ descartado (o framework já garante; escreva a justificativa na linha).
- O **cenário** manda mais do que a **forma**: os testes legados verificam HTML e redirecionamento; os novos verificam JSON e códigos HTTP. O que precisa sobreviver é a situação testada (por exemplo, `RH~1` não contorna a regra de `RH`), não o formato da resposta.
- Os dois defeitos corrigidos em 26/10/2026 (o `LIKE` do SQLite ignora a caixa; `strlen` conta bytes e `substr` do SQLite conta caracteres) têm teste nos grupos "Database e FileMeta", "FolderAccess e Groups" e "Permissões por pasta". **Portar esses antes** do código de renomear/mover.

> Gerada por script a partir dos títulos reais dos testes. Ao portar um teste, troque o ⏳ e confira o total.

## Resumo

- **189** testes PHP (incluindo 7 `todo`) e **44** declarações de teste JavaScript. Os testes de contraste e de acessibilidade geram casos em laço: a suíte JS executa 75 verificações no total.

| Fase | Testes PHP a portar |
|---|--:|
| Fase 1 | 62 |
| Fase 2 | 58 |
| Fase 3 | 61 |
| Fase 4 | 8 |

## Login com sessão real (`tests/run.php`) — 18 testes

| # | Grupo | Teste legado | Fase | Substituto na nova stack | Estado |
|--:|---|---|:-:|---|:-:|
| 1 | Login com sessão real | login correto grava sessão e troca o ID da sessão (anti-fixation) | 1 | Pest Feature — login/sessão | ⏳ |
| 2 | Login com sessão real | sessão ociosa expirada: login seguinte NÃO dá 419 (token CSRF persiste) | 1 | Pest Feature — login/sessão | ⏳ |
| 3 | Login com sessão real | sessão expirada deixa aviso "Sua sessão expirou" | 1 | Pest Feature — login/sessão | ⏳ |
| 4 | Login com sessão real | teto absoluto: sessão ativa mas com mais de 12h desde o login expira | 1 | Pest Feature — login/sessão | ⏳ |
| 5 | Login com sessão real | sessão dentro dos limites continua válida | 1 | Pest Feature — login/sessão | ⏳ |
| 6 | Login com sessão real | usuário apagado perde a sessão na hora, com aviso | 1 | Pest Feature — login/sessão | ⏳ |
| 7 | Login com sessão real | logout encerra a sessão | 1 | Pest Feature — login/sessão | ⏳ |
| 8 | Login com sessão real | login inexistente gasta o mesmo tempo de bcrypt que senha errada de login real | 1 | Pest Feature — login/sessão | ⏳ |
| 9 | Login com sessão real | hash fictício tem o mesmo custo dos hashes reais | 1 | Pest Feature — login/sessão | ⏳ |
| 10 | Login com sessão real | hash antigo (PASSWORD_DEFAULT) ainda loga e é migrado se o custo mudar | 1 | Pest Feature — login/sessão | ⏳ |
| 11 | Login com sessão real | log de falha nunca contém o texto digitado quando o login não existe | 1 | Pest Feature — login/sessão | ⏳ |
| 12 | Login com sessão real | log de falha de login real mantém o nome do usuário | 1 | Pest Feature — login/sessão | ⏳ |
| 13 | Login com sessão real | bloqueio: 5 falhas bloqueiam (mesmo com a senha certa) e gera UM log por janela | 1 | Pest Feature — login/sessão | ⏳ |
| 14 | Login com sessão real | bloqueio de login inexistente também é registrado uma vez, sem o texto digitado | 1 | Pest Feature — login/sessão | ⏳ |
| 15 | Login com sessão real | gc_maxlifetime acompanha session_lifetime (8h) e o cookie é HttpOnly/SameSite=Lax | 1 | Pest Feature — login/sessão | ⏳ |
| 16 | Login com sessão real | INTRANET_FORCE_HTTPS=1 liga cookie Secure (proxy que termina o TLS) | 1 | Pest Feature — login/sessão | ⏳ |
| 17 | Login com sessão real | tela de login: alternadores são <button> focáveis (não <a> sem href) | 1 | Pest Feature — login/sessão | ⏳ |
| 18 | Login com sessão real | ?form=change abre o formulário de nova senha e esconde o de login | 1 | Pest Feature — login/sessão | ⏳ |

## Unitários (`tests/unit_test.php`) — 62 testes

| # | Grupo | Teste legado | Fase | Substituto na nova stack | Estado |
|--:|---|---|:-:|---|:-:|
| 1 | FileStorage — caminhos | resolve: pasta vazia, ponto e barras caem na raiz | 2 | Pest Unit — `FileStorage` | ⏳ |
| 2 | FileStorage — caminhos | resolve: subpastas válidas, com barras sobrando e barra invertida | 2 | Pest Unit — `FileStorage` | ⏳ |
| 3 | FileStorage — caminhos | resolve: tentativas de sair da raiz caem na raiz | 2 | Pest Unit — `FileStorage` | ⏳ |
| 4 | FileStorage — caminhos | resolve: pasta inexistente ou arquivo não vira pasta | 2 | Pest Unit — `FileStorage` | ⏳ |
| 5 | FileStorage — caminhos | resolve: link simbólico que aponta para fora da raiz é recusado | 2 | Pest Unit — `FileStorage` | ⏳ |
| 6 | FileStorage — caminhos | resolve: a pasta volta com a caixa real do disco (sistemas que ignoram a caixa não contornam regras por caixa, ponto final ou nome curto) | 2 | Pest Unit — `FileStorage` | ⏳ |
| 7 | FileStorage — caminhos | resolve: lixeira e arquivos são raízes independentes | 2 | Pest Unit — `FileStorage` | ⏳ |
| 8 | FileStorage — nomes | safeItem: só o último segmento; vazio/ponto/nulo viram "" | 3 | Pest Unit — `FileStorage` | ⏳ |
| 9 | FileStorage — nomes | cleanName: tira símbolos e travessias, mantém letras acentuadas, números, espaço - _ . | 3 | Pest Unit — `FileStorage` | ⏳ |
| 10 | FileStorage — nomes | uniqueName: sufixo (1), (2)...; base vazia vira "arquivo"; extensão preservada | 3 | Pest Unit — `FileStorage` | ⏳ |
| 11 | FileStorage — nomes | isHidden: lista de nomes ocultos | 3 | Pest Unit — `FileStorage` | ⏳ |
| 12 | FileStorage — nomes | metaPath: lixeira ganha prefixo .lixeira/ | 3 | Pest Unit — `FileStorage` | ⏳ |
| 13 | FileStorage — nomes | reserveKeepingName: nome exato quando livre (sem limpar), reserva o arquivo e cai em (1) quando ocupado | 3 | Pest Unit — `FileStorage` | ⏳ |
| 14 | FileStorage — nomes | isSameOrInside: o próprio caminho e os de dentro; prefixo parecido não conta; raiz vazia | 3 | Pest Unit — `FileStorage` | ⏳ |
| 15 | FileStorage — nomes | hasSubdirs: só arquivos, ocultos, subpasta e pasta inexistente | 3 | Pest Unit — `FileStorage` | ⏳ |
| 16 | FileStorage — nomes | listDir: oculta itens ocultos, pastas e arquivos separados, ordem natural | 3 | Pest Unit — `FileStorage` | ⏳ |
| 17 | FileStorage — nomes | delete: remove pasta com conteúdo, não segue symlink para fora | 3 | Pest Unit — `FileStorage` | ⏳ |
| 18 | Database e FileMeta | Database::connect é idempotente e cria todas as tabelas | 3 | Pest Unit — migration baseline + `FileMeta` | ⏳ |
| 19 | Database e FileMeta | FileMeta: created + lookup em lote | 3 | Pest Unit — migration baseline + `FileMeta` | ⏳ |
| 20 | Database e FileMeta | FileMeta: lookup com mais de 500 caminhos (blocos) | 3 | Pest Unit — migration baseline + `FileMeta` | ⏳ |
| 21 | Database e FileMeta | FileMeta: renomear pasta move os filhos; irmãs com mesmo prefixo ficam intactas | 3 | Pest Unit — migration baseline + `FileMeta` | ⏳ |
| 22 | Database e FileMeta | FileMeta: curingas LIKE (% e _) no nome da pasta não atingem outras | 3 | Pest Unit — migration baseline + `FileMeta` | ⏳ |
| 23 | Database e FileMeta | FileMeta: removed apaga o item e o conteúdo, não o vizinho de prefixo | 3 | Pest Unit — migration baseline + `FileMeta` | ⏳ |
| 24 | Database e FileMeta | FileMeta: pastas que só diferem na caixa (Linux) são independentes — renomear/apagar "A" não toca "a" | 3 | Pest Unit — migration baseline + `FileMeta` | ⏳ |
| 25 | Database e FileMeta | FileMeta: pasta com acento (UTF-8) — renomear e apagar mantêm os filhos certos | 3 | Pest Unit — migration baseline + `FileMeta` | ⏳ |
| 26 | Database e FileMeta | FileMeta: origem da lixeira — lembrar, ler, esquecer, limpar tudo | 3 | Pest Unit — migration baseline + `FileMeta` | ⏳ |
| 27 | FolderAccess e Groups (permissões por pasta) | regras: pastas que só diferem na caixa (Linux) não se misturam — renomear/apagar "A" não mexe na regra de "a/sub" | 2 | Pest Unit — Policy/serviço de pastas | ⏳ |
| 28 | FolderAccess e Groups (permissões por pasta) | regras: pasta com acento (UTF-8) — renomear leva a regra das subpastas com o caminho certo | 2 | Pest Unit — Policy/serviço de pastas | ⏳ |
| 29 | FolderAccess e Groups (permissões por pasta) | chain: ancestrais incluindo o próprio; barras sobrando e vazio | 2 | Pest Unit — Policy/serviço de pastas | ⏳ |
| 30 | FolderAccess e Groups (permissões por pasta) | sem regra: todo usuário logado entra; sem usuário (null) nunca | 2 | Pest Unit — Policy/serviço de pastas | ⏳ |
| 31 | FolderAccess e Groups (permissões por pasta) | usuário direto, grupo e "só admins"; admin ignora regra; a raiz nunca fecha | 2 | Pest Unit — Policy/serviço de pastas | ⏳ |
| 32 | FolderAccess e Groups (permissões por pasta) | cadeia: precisa passar por TODAS as regras; filha só estreita | 2 | Pest Unit — Policy/serviço de pastas | ⏳ |
| 33 | FolderAccess e Groups (permissões por pasta) | lockedMap: só os filhos fechados; admin e lista vazia; muitos nomes (blocos) | 2 | Pest Unit — Policy/serviço de pastas | ⏳ |
| 34 | FolderAccess e Groups (permissões por pasta) | rule/rules: lê a regra própria (sem herança); null quando não há; em lote | 2 | Pest Unit — Policy/serviço de pastas | ⏳ |
| 35 | FolderAccess e Groups (permissões por pasta) | setRule ignora ids inexistentes e repetidos; substitui a regra anterior; clearRule só limpa a própria | 2 | Pest Unit — Policy/serviço de pastas | ⏳ |
| 36 | FolderAccess e Groups (permissões por pasta) | renamed: leva regra e subpastas; irmãs com prefixo parecido e curingas LIKE ficam intactas | 2 | Pest Unit — Policy/serviço de pastas | ⏳ |
| 37 | FolderAccess e Groups (permissões por pasta) | renamed: sobra no destino não se agarra ao item novo; mesmo caminho é no-op | 2 | Pest Unit — Policy/serviço de pastas | ⏳ |
| 38 | FolderAccess e Groups (permissões por pasta) | removed: apaga a regra e as das subpastas, não a do vizinho de prefixo | 2 | Pest Unit — Policy/serviço de pastas | ⏳ |
| 39 | FolderAccess e Groups (permissões por pasta) | Groups: nomes válidos e inválidos; duplicado sem diferenciar maiúsculas; espaços normalizados | 2 | Pest Unit — Policy/serviço de pastas | ⏳ |
| 40 | FolderAccess e Groups (permissões por pasta) | Groups: setMembers troca membros, ignora ids que não são usuários; grupo inexistente | 2 | Pest Unit — Policy/serviço de pastas | ⏳ |
| 41 | FolderAccess e Groups (permissões por pasta) | Groups: excluir grupo limpa membros e regras; all() vem ordenado por nome | 2 | Pest Unit — Policy/serviço de pastas | ⏳ |
| 42 | Logger | Logger: grava, limita o texto a 500 caracteres e lista do mais novo para o mais antigo | 1 | Pest Unit — `Logger` | ⏳ |
| 43 | Logger | Logger: usuário vem do provedor; sem ele, "Visitante" | 1 | Pest Unit — `Logger` | ⏳ |
| 44 | Logger | Logger::purge apaga só os registros mais velhos que o limite | 1 | Pest Unit — `Logger` | ⏳ |
| 45 | Auth — usuários e senha | createUser: aceita login válido (acentos, ponto, hífen, _) e cria com bcrypt custo fixo | 1 | Pest Unit/Feature — usuários e senha | ⏳ |
| 46 | Auth — usuários e senha | createUser: recusa login inválido, role inválida, senha curta e duplicado | 1 | Pest Unit/Feature — usuários e senha | ⏳ |
| 47 | Auth — usuários e senha | createUser: SQL injection no login não passa (regex) e no resto é parametrizado | 1 | Pest Unit/Feature — usuários e senha | ⏳ |
| 48 | Auth — usuários e senha | resetPassword: usuário inexistente, senha curta e sucesso | 1 | Pest Unit/Feature — usuários e senha | ⏳ |
| 49 | Auth — usuários e senha | changePassword: senha atual errada, nova curta, sucesso; a antiga deixa de valer | 1 | Pest Unit/Feature — usuários e senha | ⏳ |
| 50 | Auth — usuários e senha | deleteUser: não apaga a si mesmo, nem o último admin; apaga usuário comum; id inexistente | 1 | Pest Unit/Feature — usuários e senha | ⏳ |
| 51 | Auth — usuários e senha | listUsers: ordenado por login e sem expor o hash | 1 | Pest Unit/Feature — usuários e senha | ⏳ |
| 52 | Auth — limite de tentativas (fronteiras) | 4 falhas não bloqueiam; a 5ª bloqueia; senha certa não passa bloqueado | 1 | Pest Unit — `RateLimiter` de login | ⏳ |
| 53 | Auth — limite de tentativas (fronteiras) | bloqueio é por IP: outro IP não é afetado | 1 | Pest Unit — `RateLimiter` de login | ⏳ |
| 54 | Auth — limite de tentativas (fronteiras) | chave do limite ignora maiúsculas ("Maria" e "maria" contam juntos) | 1 | Pest Unit — `RateLimiter` de login | ⏳ |
| 55 | Auth — limite de tentativas (fronteiras) | bloqueio expira após lockout_minutes | 1 | Pest Unit — `RateLimiter` de login | ⏳ |
| 56 | Auth — limite de tentativas (fronteiras) | login com sucesso limpa as falhas daquele usuário+IP | 1 | Pest Unit — `RateLimiter` de login | ⏳ |
| 57 | Auth — limite de tentativas (fronteiras) | nomes gigantes e estranhos não quebram nem enchem a tabela | 1 | Pest Unit — `RateLimiter` de login | ⏳ |
| 58 | Auth — limite de tentativas (fronteiras) | changePassword também respeita o bloqueio por tentativas (não vira atalho para adivinhar a senha) | 1 | Pest Unit — `RateLimiter` de login | ⏳ |
| 59 | Auth — limite de tentativas (fronteiras) | sessão antiga sem login_at passa a contar a partir do primeiro acesso (não cai) | 1 | Pest Unit — `RateLimiter` de login | ⏳ |
| 60 | Csrf | token: 64 hex, estável na sessão, regenerate troca | 1 | Pest Feature — Sanctum/XSRF | ⏳ |
| 61 | Csrf | verifyRequest: aceita campo e header; recusa ausente, errado, vazio e não-string | 1 | Pest Feature — Sanctum/XSRF | ⏳ |
| 62 | Csrf | field: HTML com o token escapado | 1 | Pest Feature — Sanctum/XSRF | ⏳ |

## Integração HTTP (`tests/http_test.php`) — 82 testes

| # | Grupo | Teste legado | Fase | Substituto na nova stack | Estado |
|--:|---|---|:-:|---|:-:|
| 1 | Infra e acesso anônimo | GET / anônimo: tela de login, cookie HttpOnly+SameSite, cabeçalhos de segurança, sem HSTS | 1 | Pest Feature (cabeçalhos, anônimo) + smoke no servidor | ⏳ |
| 2 | Infra e acesso anônimo | arquivos internos nunca são servidos pela web (404) | 1 | Pest Feature (cabeçalhos, anônimo) + smoke no servidor | ⏳ |
| 3 | Infra e acesso anônimo | assets estáticos são servidos | 1 | Pest Feature (cabeçalhos, anônimo) + smoke no servidor | ⏳ |
| 4 | Infra e acesso anônimo | rota inexistente = 404; ações de escrita por GET não executam (nem as antigas) | 1 | Pest Feature (cabeçalhos, anônimo) + smoke no servidor | ⏳ |
| 5 | Infra e acesso anônimo | ação protegida sem login mostra o login e não entrega o arquivo | 1 | Pest Feature (cabeçalhos, anônimo) + smoke no servidor | ⏳ |
| 6 | Infra e acesso anônimo | POST sem CSRF e com token de outra sessão = 419; token válido anônimo em ação protegida cai no login | 1 | Pest Feature (cabeçalhos, anônimo) + smoke no servidor | ⏳ |
| 7 | Infra e acesso anônimo | POST text/plain pequeno NÃO é tratado como upload gigante (não dá 413) | 1 | Pest Feature (cabeçalhos, anônimo) + smoke no servidor | ⏳ |
| 8 | Infra e acesso anônimo | POST maior que post_max_size = 413 (JSON se XHR) e sem efeito | 1 | Pest Feature (cabeçalhos, anônimo) + smoke no servidor | ⏳ |
| 9 | Login e sessão (pela rede) | senha errada e usuário inexistente dão a MESMA mensagem; certa entra | 1 | Pest Feature — login | ⏳ |
| 10 | Login e sessão (pela rede) | ID da sessão muda no login (anti-fixation) e o token CSRF também | 1 | Pest Feature — login | ⏳ |
| 11 | Login e sessão (pela rede) | 5 senhas erradas bloqueiam; a certa também falha; mensagem de bloqueio | 1 | Pest Feature — login | ⏳ |
| 12 | Login e sessão (pela rede) | logout destrói a sessão no servidor (cookie copiado antes também deixa de valer) | 1 | Pest Feature — login | ⏳ |
| 13 | Login e sessão (pela rede) | troca de senha pública: erro reabre ?form=change; sucesso volta ao login; antiga deixa de valer | 1 | Pest Feature — login | ⏳ |
| 14 | Login e sessão (pela rede) | usuário logado troca a própria senha pelo painel (ignora o nome enviado) | 1 | Pest Feature — login | ⏳ |
| 15 | Login e sessão (pela rede) | sessão ociosa há muito tempo: aviso "sessão expirou" e o login seguinte funciona (sem 419) | 1 | Pest Feature — login | ⏳ |
| 16 | Login e sessão (pela rede) | usuário apagado perde a sessão na próxima requisição | 1 | Pest Feature — login | ⏳ |
| 17 | Listagem e navegação | lista: pastas e arquivos aparecem; ocultos não; nomes com aspas e & vêm escapados | 2 | Pest Feature — listar | ⏳ |
| 18 | Listagem e navegação | subpasta: lista o conteúdo e monta o breadcrumb; pasta vazia mostra aviso | 2 | Pest Feature — listar | ⏳ |
| 19 | Listagem e navegação | traversal em ?dir= cai na raiz e nunca lista nada de fora | 2 | Pest Feature — listar | ⏳ |
| 20 | Listagem e navegação | ?mode=trash: usuário comum vê a lista normal; admin vê a lixeira | 2 | Pest Feature — listar | ⏳ |
| 21 | Listagem e navegação | usuário comum não recebe elementos de admin; admin recebe | 2 | Pest Feature — listar | ⏳ |
| 22 | Download e ZIP | download: tipos permitidos abrem inline; svg e desconhecidos sempre baixam | 2 | Pest Feature — download/ZIP | ⏳ |
| 23 | Download e ZIP | download: nome de arquivo com acento no cabeçalho (filename*=UTF-8) | 2 | Pest Feature — download/ZIP | ⏳ |
| 24 | Download e ZIP | download com Range: 206, sufixo, fora do alcance = 416, lixo = 200 inteiro | 2 | Pest Feature — download/ZIP | ⏳ |
| 25 | Download e ZIP | download recusa ocultos, inexistentes, travessia, array e nome vazio (404) | 2 | Pest Feature — download/ZIP | ⏳ |
| 26 | Download e ZIP | download de lixeira: só admin; usuário comum não alcança | 2 | Pest Feature — download/ZIP | ⏳ |
| 27 | Download e ZIP | ZIP: só os selecionados válidos; ignora ocultos/inexistentes/travessia; vazio = aviso | 2 | Pest Feature — download/ZIP | ⏳ |
| 28 | Download e ZIP | download registra DOWNLOAD no log; visualização inline não | 2 | Pest Feature — download/ZIP | ⏳ |
| 29 | Permissões: usuário comum não consegue nada de admin | todas as ações de admin são negadas e não mudam disco nem banco | 3 | Pest Feature — Policy de admin | ⏳ |
| 30 | Admin: pastas, upload, renomear | nova pasta: cria; recusa vazio, "..", símbolos e duplicada; aceita acentos | 3 | Pest Feature — escrita | ⏳ |
| 31 | Admin: pastas, upload, renomear | upload: aceita permitidos, bloqueia perigosos, resolve colisão e saneia o nome | 3 | Pest Feature — escrita | ⏳ |
| 32 | Admin: pastas, upload, renomear | upload: arquivo com dupla extensão "shell.php.jpg" é só dado (fora do docroot, nunca executa) | 3 | Pest Feature — escrita | ⏳ |
| 33 | Admin: pastas, upload, renomear | upload sem arquivos e múltiplos arquivos | 3 | Pest Feature — escrita | ⏳ |
| 34 | Admin: pastas, upload, renomear | upload sem XHR: redireciona com mensagem (flash) | 3 | Pest Feature — escrita | ⏳ |
| 35 | Admin: pastas, upload, renomear | upload registra autor (files_meta) e UPLOAD no log | 3 | Pest Feature — escrita | ⏳ |
| 36 | Admin: pastas, upload, renomear | renomear: arquivo mantém a extensão; colisão e nomes inválidos recusados; pasta leva os metadados | 3 | Pest Feature — escrita | ⏳ |
| 37 | Admin: lixeira | ciclo: pasta com arquivo → lixeira → restaurar na pasta original | 3 | Pest Feature — lixeira | ⏳ |
| 38 | Admin: lixeira | restaurar arquivo volta para a subpasta; se a subpasta sumiu, vai para a raiz; nome ocupado ganha (1) | 3 | Pest Feature — lixeira | ⏳ |
| 39 | Admin: lixeira | lixeira: nomes iguais não se sobrescrevem; excluir definitivo apaga disco e metadados; esvaziar limpa tudo | 3 | Pest Feature — lixeira | ⏳ |
| 40 | Admin: lixeira | ações da lixeira com item inexistente, travessia ou fora da lixeira não fazem nada | 3 | Pest Feature — lixeira | ⏳ |
| 41 | Admin: usuários e painel | criar usuário pelo painel: valida, cria e o novo usuário consegue entrar | 3 | Pest Feature — usuários/painel | ⏳ |
| 42 | Admin: usuários e painel | excluir: a si mesmo é recusado; outro admin e usuário comum são excluídos; id inválido não quebra | 3 | Pest Feature — usuários/painel | ⏳ |
| 43 | Admin: usuários e painel | painel: logs aparecem para o admin com HTML escapado (log malicioso não executa) | 3 | Pest Feature — usuários/painel | ⏳ |
| 44 | Admin: usuários e painel | lista de usuários não vaza hash e não oferece excluir a si mesmo | 3 | Pest Feature — usuários/painel | ⏳ |
| 45 | Permissões por pasta (usuários e grupos, ADR 0006) | admin cria grupo, põe rh1 nele e restringe RH ao grupo + rh2 | 2 | Pest Feature — acesso por pasta | ⏳ |
| 46 | Permissões por pasta (usuários e grupos, ADR 0006) | quem não está na regra vê a pasta com cadeado, sem link, sem autoria e sem data | 2 | Pest Feature — acesso por pasta | ⏳ |
| 47 | Permissões por pasta (usuários e grupos, ADR 0006) | abrir a pasta fechada pela URL volta à raiz com "Acesso negado"; filhos também | 2 | Pest Feature — acesso por pasta | ⏳ |
| 48 | Permissões por pasta (usuários e grupos, ADR 0006) | download e ZIP de dentro da pasta fechada: 403 sem o conteúdo | 2 | Pest Feature — acesso por pasta | ⏳ |
| 49 | Permissões por pasta (usuários e grupos, ADR 0006) | digitar a pasta com outra caixa, ponto final ou nome curto não contorna a restrição | 2 | Pest Feature — acesso por pasta | ⏳ |
| 50 | Permissões por pasta (usuários e grupos, ADR 0006) | grupo e usuário listados entram: lista, baixa e baixa ZIP | 2 | Pest Feature — acesso por pasta | ⏳ |
| 51 | Permissões por pasta (usuários e grupos, ADR 0006) | regra de subpasta só estreita: RH/Sub só para rh2 (rh1 entra em RH, mas não em Sub) | 2 | Pest Feature — acesso por pasta | ⏳ |
| 52 | Permissões por pasta (usuários e grupos, ADR 0006) | regra na filha não reabre o que o pai fechou (outro está em Sub, mas RH está fechada) | 2 | Pest Feature — acesso por pasta | ⏳ |
| 53 | Permissões por pasta (usuários e grupos, ADR 0006) | admin vê tudo e as regras no botão de acesso | 2 | Pest Feature — acesso por pasta | ⏳ |
| 54 | Permissões por pasta (usuários e grupos, ADR 0006) | renomear e mandar para a lixeira levam a regra junto; restaurar a devolve | 2 | Pest Feature — acesso por pasta | ⏳ |
| 55 | Permissões por pasta (usuários e grupos, ADR 0006) | excluir definitivamente e esvaziar a lixeira apagam as regras | 2 | Pest Feature — acesso por pasta | ⏳ |
| 56 | Permissões por pasta (usuários e grupos, ADR 0006) | renomear pasta com acento leva a regra da subpasta junto (UTF-8: bytes ≠ caracteres no SQLite) | 2 | Pest Feature — acesso por pasta | ⏳ |
| 57 | Permissões por pasta (usuários e grupos, ADR 0006) | "só admins" (restringir sem marcar ninguém) e voltar a herdar | 2 | Pest Feature — acesso por pasta | ⏳ |
| 58 | Permissões por pasta (usuários e grupos, ADR 0006) | excluir o grupo ou o usuário tira o acesso de quem dependia deles | 2 | Pest Feature — acesso por pasta | ⏳ |
| 59 | Permissões por pasta (usuários e grupos, ADR 0006) | usuário comum não define regras nem gerencia grupos; sem CSRF = 419; pasta inválida é recusada | 2 | Pest Feature — acesso por pasta | ⏳ |
| 60 | Permissões por pasta (usuários e grupos, ADR 0006) | grupos: nomes inválidos ou repetidos (sem diferenciar maiúsculas) são recusados | 2 | Pest Feature — acesso por pasta | ⏳ |
| 61 | Árvore de pastas (GET ?action=tree) | anônimo não recebe a árvore (cai no login) | 2 | Pest Feature — árvore | ⏳ |
| 62 | Árvore de pastas (GET ?action=tree) | raiz: só subpastas (sem arquivos nem ocultos), em ordem natural, com hasChildren certo | 2 | Pest Feature — árvore | ⏳ |
| 63 | Árvore de pastas (GET ?action=tree) | subpasta: lista só as filhas diretas; arquivo solto não aparece; nome com & e acento vai intacto no JSON | 2 | Pest Feature — árvore | ⏳ |
| 64 | Árvore de pastas (GET ?action=tree) | pasta fechada: vem com cadeado e sem filhas; pedir o conteúdo dela é 403 (não vaza o que há dentro) | 2 | Pest Feature — árvore | ⏳ |
| 65 | Árvore de pastas (GET ?action=tree) | traversal em dir= cai na raiz; mode=trash só vale para admin (e vem vazio) | 2 | Pest Feature — árvore | ⏳ |
| 66 | Árvore de pastas (GET ?action=tree) | página: árvore com a cadeia da pasta atual aberta, pasta atual marcada, cadeado sem link; lixeira sem árvore | 2 | Pest Feature — árvore | ⏳ |
| 67 | Árvore de pastas (GET ?action=tree) | página: nomes de pasta saem escapados na árvore | 2 | Pest Feature — árvore | ⏳ |
| 68 | Mover itens entre pastas (action=move) | mover arquivo: sai da origem, chega ao destino; nome ocupado ganha (1) sem sobrescrever; autoria acompanha | 3 | Pest Feature — mover | ⏳ |
| 69 | Mover itens entre pastas (action=move) | sem colisão o nome é mantido exatamente (aspas, &) e o conteúdo vai inteiro | 3 | Pest Feature — mover | ⏳ |
| 70 | Mover itens entre pastas (action=move) | mover pasta leva o conteúdo, a autoria dos filhos e a regra de acesso; quem estava barrado continua barrado | 3 | Pest Feature — mover | ⏳ |
| 71 | Mover itens entre pastas (action=move) | mover para dentro de pasta restrita aplica a restrição ao que chegou | 3 | Pest Feature — mover | ⏳ |
| 72 | Mover itens entre pastas (action=move) | lote misto: o que não pode ir é ignorado e contado; o resto vai | 3 | Pest Feature — mover | ⏳ |
| 73 | Mover itens entre pastas (action=move) | destino inválido não move nada: inexistente, traversal, arquivo, pasta atual, lixeira | 3 | Pest Feature — mover | ⏳ |
| 74 | Mover itens entre pastas (action=move) | pasta não vai para dentro de si mesma nem de descendente; prefixo parecido (Pasta2 x Pasta2x) é outra pasta | 3 | Pest Feature — mover | ⏳ |
| 75 | Mover itens entre pastas (action=move) | mover para o início (raiz) e dentro da raiz para uma pasta | 3 | Pest Feature — mover | ⏳ |
| 76 | Mover itens entre pastas (action=move) | usuário comum não move; sem CSRF = 419; na lixeira não move; mover não escapa da raiz | 3 | Pest Feature — mover | ⏳ |
| 77 | Mover itens entre pastas (action=move) | página do admin tem botões de mover; a do usuário comum, não | 3 | Pest Feature — mover | ⏳ |
| 78 | Desempenho básico | pasta com 400 arquivos lista em menos de 3 s e traz a autoria de todos | 2 | Pest Feature — desempenho | ⏳ |
| 79 | Configurações do servidor (instâncias extras) | primeiro uso: storage inexistente é criado e a tela de login explica como criar o 1º usuário | 4 | Smoke/CI com `.env` de teste | ⏳ |
| 80 | Configurações do servidor (instâncias extras) | atrás de proxy HTTPS (INTRANET_FORCE_HTTPS=1): cookie Secure e HSTS | 4 | Smoke/CI com `.env` de teste | ⏳ |
| 81 | Configurações do servidor (instâncias extras) | erro interno devolve 500 genérico, sem vazar caminho/stack; só INTRANET_DEBUG=1 mostra detalhes | 4 | Smoke/CI com `.env` de teste | ⏳ |
| 82 | Configurações do servidor (instâncias extras) | upload acima de upload_max_filesize (mas abaixo de post_max_size): conta como erro, os outros arquivos passam | 4 | Smoke/CI com `.env` de teste | ⏳ |

## Concorrência (`tests/concurrency_test.php`) — 6 testes

| # | Grupo | Teste legado | Fase | Substituto na nova stack | Estado |
|--:|---|---|:-:|---|:-:|
| 1 | Concorrência — nomes no upload | 8 uploads simultâneos com o MESMO nome: nenhum sobrescreve o outro (reserva atômica) | 3 | Pest Feature + processos paralelos | ⏳ |
| 2 | Concorrência — nomes no upload | 8 "mover" simultâneos para o mesmo destino com o MESMO nome: nenhum sobrescreve o outro (reserveKeepingName) | 3 | Pest Feature + processos paralelos | ⏳ |
| 3 | Concorrência — nomes no upload | reserveName: não reaproveita nome existente, pula buracos e respeita extensão | 3 | Pest Feature + processos paralelos | ⏳ |
| 4 | Concorrência — SQLite com vários escritores | 8 processos gravando metadados, logs e tentativas ao mesmo tempo: nada se perde e o banco continua íntegro | 3 | Pest Feature + processos paralelos | ⏳ |
| 5 | Concorrência — regras de pasta e grupos | 8 processos criando grupos, regras e renomeando ao mesmo tempo: sem "database is locked" e sem perda | 3 | Pest Feature + processos paralelos | ⏳ |
| 6 | Concorrência — usuários | dois admins se excluindo ao mesmo tempo: sempre sobra pelo menos um admin | 3 | Pest Feature + processos paralelos | ⏳ |

## Backup e restauração (`tests/backup_test.php`) — 10 testes

| # | Grupo | Teste legado | Fase | Substituto na nova stack | Estado |
|--:|---|---|:-:|---|:-:|
| 1 | Backup com o sistema ligado | backup concorrente com escritas no banco: termina, passa na conferência e o banco é consistente | 3 | Pest Feature — comando `intranet:backup` | ⏳ |
| 2 | Backup com o sistema ligado | o backup contém banco, uploads (com acentos) e lixeira; os logs ficam de fora | 3 | Pest Feature — comando `intranet:backup` | ⏳ |
| 3 | Backup com o sistema ligado | o backup não altera a produção (arquivos intactos) | 3 | Pest Feature — comando `intranet:backup` | ⏳ |
| 4 | Backup com o sistema ligado | backup recusa destino existente | 3 | Pest Feature — comando `intranet:backup` | ⏳ |
| 5 | Backup com o sistema ligado | verify detecta arquivo alterado, removido e acrescentado | 3 | Pest Feature — comando `intranet:backup` | ⏳ |
| 6 | Backup com o sistema ligado | verify recusa backup sem manifest e com banco corrompido | 3 | Pest Feature — comando `intranet:backup` | ⏳ |
| 7 | Restauração | restaura em pasta nova: árvore idêntica ao backup (menos o manifest) e banco íntegro | 3 | Pest Feature — comando `intranet:restore` | ⏳ |
| 8 | Restauração | restore recusa destino com conteúdo, backup adulterado e não sobrescreve nada | 3 | Pest Feature — comando `intranet:restore` | ⏳ |
| 9 | Sistema ligado na pasta restaurada | login, listagem, autoria e download funcionam idênticos ao original | 3 | Pest Feature — comando `intranet:restore` | ⏳ |
| 10 | Sistema ligado na pasta restaurada | restaurar a lixeira preserva a origem: restaurar velho.txt volta para "Norma Zero" | 3 | Pest Feature — comando `intranet:restore` | ⏳ |

## Smoke (`tests/smoke_test.php`) — 4 testes

| # | Grupo | Teste legado | Fase | Substituto na nova stack | Estado |
|--:|---|---|:-:|---|:-:|
| 1 | bin/smoke.php | instalação correta (docroot = public/): nenhum FAIL e exit 0 | 4 | Comando `intranet:smoke` | ⏳ |
| 2 | bin/smoke.php | login de verificação: entra, carrega o painel e o logout encerra a sessão | 4 | Comando `intranet:smoke` | ⏳ |
| 3 | bin/smoke.php | DOCROOT ERRADO (raiz do projeto): reprova e acusa o que ficou exposto | 4 | Comando `intranet:smoke` | ⏳ |
| 4 | bin/smoke.php | URL inválida e servidor fora do ar: mensagem clara e exit 1 | 4 | Comando `intranet:smoke` | ⏳ |

## Pendentes da ADR-0005 Fase 2 (`tests/pending_phase2_test.php`) — 7 testes

| # | Grupo | Teste legado | Fase | Substituto na nova stack | Estado |
|--:|---|---|:-:|---|:-:|
| 1 | ADR-0005 Fase 2 — login (escritos antes do código) | limite por IP: 31 falhas de logins diferentes no mesmo IP bloqueiam o IP (a 32ª, certa, também) | 1 | Pest Feature — login (nasce no Laravel) | ⏳ todo |
| 2 | ADR-0005 Fase 2 — login (escritos antes do código) | limite por usuário NÃO vira DoS: 25 falhas de IPs diferentes contra "joao" geram LOGIN_ALERTA, e o dono ainda entra | 1 | Pest Feature — login (nasce no Laravel) | ⏳ todo |
| 3 | ADR-0005 Fase 2 — login (escritos antes do código) | corrida: 30 tentativas erradas simultâneas gravam no máximo 6 falhas (o resto já nasce bloqueado) | 1 | Pest Feature — login (nasce no Laravel) | ⏳ todo |
| 4 | ADR-0005 Fase 2 — login (escritos antes do código) | auth_version: trocar a senha derruba as OUTRAS sessões do usuário e mantém a atual | 1 | Pest Feature — login (nasce no Laravel) | ⏳ todo |
| 5 | ADR-0005 Fase 2 — login (escritos antes do código) | senha temporária: bin/create-user.php --reset liga must_change_password e o login manda trocar antes de tudo | 1 | Pest Feature — login (nasce no Laravel) | ⏳ todo |
| 6 | ADR-0005 Fase 2 — login (escritos antes do código) | política de senha: 12345678, password, igual ao login e qwertyui são recusadas; "Tr0ca-Segura-2026" é aceita | 1 | Pest Feature — login (nasce no Laravel) | ⏳ todo |
| 7 | ADR-0005 Fase 2 — login (escritos antes do código) | logins sem diferença de maiúsculas: criar "Adm" quando existe "adm" é recusado; entrar como "ADM" entra em "adm" | 1 | Pest Feature — login (nasce no Laravel) | ⏳ todo |

## JavaScript e acessibilidade (`tests/js/`) — 44 declarações

`${...}` no título marca um teste gerado em laço (uma verificação por tela, tema ou combinação de cores). No Next.js, `jsdom` e `axe` continuam valendo para os componentes; o que depende de layout real (arrastar e soltar, celular, contraste calculado) vai para o Playwright.

### `tests/js/ui.test.js` — 39 declaração(ões)

| # | Teste legado | Substituto na nova stack | Estado |
|--:|---|---|:-:|
| 1 | modais: abrir, fechar pelo X, encadear (Painel -> Usuários -> voltar) e fechar clicando no fundo | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 2 | teclado: Esc fecha o modal; o foco entra no modal ao abrir e volta ao botão que o abriu | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 3 | todo modal é um diálogo com nome acessível (role=dialog, aria-modal, aria-label) | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 4 | modal de senha existe para o usuário comum, mas sem nenhum modal de admin | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 5 | busca filtra por nome (sem diferenciar maiúsculas) e limpar mostra tudo | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 6 | renomear: abre o modal com o nome atual nos dois campos | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 7 | ZIP: botão aparece com a contagem de arquivos, "selecionar todos" marca tudo e some ao desmarcar | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 8 | pasta tem caixa de seleção só para admin (mover); para usuário comum só arquivos entram | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 9 | ação destrutiva vira POST com _csrf, action e item; o formulário vai para a URL atual | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 10 | confirmação recusada cancela a ação (nada é enviado); aceita envia | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 11 | excluir usuário envia o id numérico; não existe botão para o próprio usuário | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 12 | lightbox: abre com a imagem certa, fecha pelo X e pelo fundo | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 13 | toast do servidor aparece como TEXTO (HTML malicioso na mensagem não é interpretado) | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 14 | tema: alterna, persiste no localStorage e sobrevive a localStorage bloqueado | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 15 | upload: mostra "N selecionados" e o botão; envia por XHR para a URL atual com X-Requested-With e FormData com CSRF | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 16 | upload sem arquivos escolhidos não dispara XHR | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 17 | login: alterna entre "Entrar" e "Nova senha" (botões) | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 18 | login ?form=change: já abre no formulário de nova senha e o botão "Voltar" funciona | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 19 | acesso à pasta: o botão preenche o modal com a regra atual e o modo "herdar" trava as listas | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 20 | acesso à pasta: pasta sem regra abre em "herdar", sem ninguém marcado, e o título nunca vira HTML | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 21 | usuário comum: pasta fechada aparece com cadeado, sem link nem botões de admin | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 22 | grupos: o modal lista os grupos com seus membros e cada formulário leva CSRF | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 23 | árvore: estrutura ARIA, raiz aberta, pastas do primeiro nível e pasta atual marcada | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 24 | árvore: na pasta aberta a cadeia vem aberta e a pasta atual fica marcada | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 25 | árvore: abrir busca as filhas uma vez; nomes viram texto; pasta fechada é cadeado sem link; fechar e reabrir não busca de novo | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 26 | árvore: falha ao carregar mostra aviso, não abre e tenta de novo no próximo clique | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 27 | árvore: teclado (setas, Home/End, Enter) segue o padrão ARIA tree | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 28 | árvore: lembra o que o usuário abriu e reabre ao voltar à página | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 29 | árvore: botão "Pastas" fecha e abre o painel e lembra a escolha; localStorage bloqueado não quebra | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 30 | árvore: usuário comum vê a pasta fechada como cadeado; a lixeira não tem árvore | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 31 | mover: o botão só aparece com seleção, conta pastas e arquivos e abre o modal com os itens | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 32 | mover: a busca esconde linhas — "todos" e "Mover selecionados" só valem para o que se vê | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 33 | mover: o botão da linha leva só aquele item; folha de destino escolhida habilita o envio | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 34 | mover: o modal recusa a pasta atual e uma pasta dentro dela mesma, e aceita o início | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 35 | arrastar: item solto sobre uma pasta da lista vira POST move com a pasta como destino | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 36 | arrastar: item marcado leva a seleção inteira; arquivo não é alvo; pasta não cai em si mesma | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 37 | arrastar: migalha e árvore lateral são alvos; a pasta atual não é | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 38 | arrastar: soltar arquivos do computador sobre uma pasta envia para ela (XHR com CSRF), e some o destaque ao sair | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |
| 39 | mover: usuário comum e lixeira não têm botão, modal, linha arrastável nem checkbox de pasta | Vitest + Testing Library (por tela; Fases 1 a 3) | ⏳ |

### `tests/js/a11y.test.js` — 3 declaração(ões)

| # | Teste legado | Substituto na nova stack | Estado |
|--:|---|---|:-:|
| 1 | acessibilidade (WCAG 2.1 A/AA): ${title} | Vitest + axe (Fases 1 a 3) | ⏳ |
| 2 | todas as telas declaram idioma e título | Vitest + axe (Fases 1 a 3) | ⏳ |
| 3 | campos de senha têm autocomplete correto (gerenciadores de senha funcionam) | Vitest + axe (Fases 1 a 3) | ⏳ |

### `tests/js/contrast.test.js` — 2 declaração(ões)

| # | Teste legado | Substituto na nova stack | Estado |
|--:|---|---|:-:|
| 1 | contraste ≥ 4,5:1 — ${theme}: ${label} | Vitest — contraste das variáveis de cor (Fase 1) | ⏳ |
| 2 | os dois temas definem as mesmas variáveis de cor críticas | Vitest — contraste das variáveis de cor (Fase 1) | ⏳ |
