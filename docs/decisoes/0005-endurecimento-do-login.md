# ADR-0005: Endurecimento e completude do fluxo de login

**Status:** Parcialmente implementada — **Fase 1 concluída em 23/10/2026** (achados 1, 3, 4, 5 e 10; testes em `tests/run.php`). Fases 2 e 3 pendentes.
**Data:** 23/10/2026
**Decisores:** Responsável pela TI da FCECON; administradores do sistema

## Contexto

O code review do fluxo de login ([docs/fluxos.md](../fluxos.md), seções 2, 3 e 9) encontrou 10 pontos em `src/Auth.php`, `src/Application.php` e `src/Views/login.php`. A base já tem o essencial (bcrypt com rehash, CSRF, `session_regenerate_id`, bloqueio por tentativas, perfil relido do banco a cada requisição). O que falta são **defesas contra ataque distribuído**, **ciclo de vida da sessão** e **completude** (troca forçada, aviso de expiração).

Forças em jogo:

- Sistema interno de um hospital/fundação: poucos usuários (6 contas), um servidor, equipe de TI pequena.
- Contas reais com senha temporária entregue por fora do sistema (`senhas-novas.txt`).
- Sem Composer, sem Redis, SQLite como banco (ADR 0003 e 0004): a solução precisa funcionar com PHP puro + SQLite.
- Pode haver proxy reverso/IIS na frente (não confirmado).
- Possível diretório corporativo (Active Directory) na fundação: não foi verificado.

Achados que motivam esta decisão (numeração do review):

| # | Achado | Gravidade |
|---|---|---|
| 1 | 1º login após expirar a sessão dá 419 (`session_destroy` dentro de `Auth::user()`) | alta |
| 2 | Rate limit só por usuário+IP: spraying sem limite, DoS atrás de proxy, corrida check-then-act | alta |
| 3 | `gc_maxlifetime` padrão (24 min) derruba a sessão antes das 8h; sem teto absoluto; `Secure` depende só de `$_SERVER['HTTPS']` | média |
| 4 | Enumeração de logins por tempo de resposta | média |
| 5 | Log guarda o texto digitado como usuário (senhas digitadas no campo errado) e enche com tentativas bloqueadas | média |
| 6 | Troca de senha não derruba outras sessões; sem troca forçada no primeiro acesso | média |
| 7 | `Adm` e `adm` coexistem e dividem o contador de bloqueio | baixa-média |
| 8 | Política de senha: só 8 caracteres | baixa-média |
| 9 | `DELETE` de limpeza varre a tabela em toda falha | baixa |
| 10 | Link "Alterar Senha" sem `href`; erro de troca reabre o formulário errado | baixa |

## Decisão

Adotar, **sem trocar a arquitetura** (sessões nativas do PHP + SQLite), um pacote de seis mudanças em `Auth`, `Application` e na tela de login, entregues em três fases (ver Ações). Em resumo:

1. **Limites em camadas**, atômicos, com IP real vindo de proxy confiável configurável.
2. **Sessão com ciclo de vida completo:** ociosidade 8 h + teto absoluto 12 h, `gc_maxlifetime` coerente, invalidação por `auth_version`, expiração tratada sem destruir a sessão no meio da requisição.
3. **Senha:** política de comprimento + lista de proibidas, e `must_change_password` para senhas temporárias.
4. **Respostas uniformes:** hash fictício contra enumeração por tempo; log sem texto digitado.
5. **Identidade:** logins sem diferença de caixa (`COLLATE NOCASE`).
6. **Migrações versionadas** (`schema_version`), necessárias para as novas colunas.

## Opções consideradas

### Decisão 1 — Limitação de tentativas

#### Opção A: manter usuário+IP (hoje)
| Dimensão | Avaliação |
|---|---|
| Complexidade | Baixa (já existe) |
| Custo | Zero |
| Resistência | Fraca: spraying livre; DoS direcionado atrás de proxy; corrida |

**Prós:** simples. **Contras:** os três problemas do achado 2.

#### Opção B (recomendada): três contadores + transação + IP confiável
| Dimensão | Avaliação |
|---|---|
| Complexidade | Média (≈80 linhas em `Auth`, 1 chave de config) |
| Custo | Zero (mesma tabela `login_attempts`) |
| Resistência | Boa para o porte do sistema |

Regras:

- `(usuário, IP)`: 5 falhas / 15 min → bloqueia (como hoje).
- `IP`: 30 falhas / 15 min → bloqueia o IP (barra spraying de um só endereço).
- `usuário` global: **não bloqueia** (evita DoS contra o admin); ao passar de 20 falhas / h grava `LOGIN_ALERTA` no log para o admin ver.
- Verificação + registro dentro de `BEGIN IMMEDIATE` (fecha a corrida).
- IP real: `REMOTE_ADDR`, e só usa `X-Forwarded-For` se `REMOTE_ADDR` estiver em `config['trusted_proxies']` (vazio por padrão).
- Limpeza de `login_attempts` esporádica (1/50, como o log) + índice em `attempted_at`.

**Prós:** cobre spraying e corrida sem expor o admin a bloqueio remoto. **Contras:** NAT corporativo (todos atrás de um IP) pode bater no teto de 30; ajustável.

#### Opção C: delegar a WAF / fail2ban / IIS Dynamic IP Restrictions
| Dimensão | Avaliação |
|---|---|
| Complexidade | Média-alta (depende do servidor, que não está nesta máquina) |
| Custo | Tempo da infra |
| Resistência | Ótima, mas fora do nosso controle |

**Prós:** protege também fora do login. **Contras:** não verificável aqui; não substitui a defesa na aplicação. Fica como **complemento** recomendado.

### Decisão 2 — Sessão

#### Opção A: sessões nativas + `auth_version` no usuário (recomendada)
Coluna `users.auth_version` (inteiro). O login grava a versão na sessão; `Auth::user()` compara. Trocar senha, mudar perfil ou "encerrar sessões" incrementa a versão → todas as outras sessões caem na próxima requisição.

| Dimensão | Avaliação |
|---|---|
| Complexidade | Baixa-média |
| Custo | Uma coluna e uma comparação por requisição (a consulta ao usuário já existe) |
| Escalabilidade | Adequada |

Também: `session.gc_maxlifetime = session_lifetime`; teto absoluto (`login_at` + 12 h); expiração **não** chama `session_destroy` no meio da requisição: marca `$expired`, chama `session_regenerate_id(true)`, limpa `user_id` e grava flash "Sua sessão expirou. Entre novamente." (resolve o achado 1 pela raiz: o token CSRF gerado depois continua persistido).

#### Opção B: sessões em tabela SQLite
Permite listar/encerrar sessões individuais e ver "onde estou logado". **Contras:** reimplementa o que o PHP já faz, escrita no banco a cada requisição (SQLite serializa escritas), mais código para manter. **Adiar** até haver requisito de "sessões ativas".

#### Opção C: token assinado (JWT)
**Contras:** sem revogação barata, sem ganho para app monolítico de um servidor. **Descartada.**

### Decisão 3 — Política e ciclo de vida da senha

#### Opção A: regras de composição (maiúscula, número, símbolo)
Fácil de explicar, mas induz `Senha@123`. Contraria a orientação atual (NIST SP 800-63B). **Descartada.**

#### Opção B (recomendada): comprimento + lista de proibidas + troca forçada
- Mínimo **10** caracteres (hoje 8), máximo 128; recusa senha igual ao login, sequências (`12345678`, `qwertyui`) e uma lista curta embutida de senhas comuns (≈200 itens, arquivo em `config/`).
- Coluna `must_change_password`: `bin/create-user.php` (criação e `--reset`) liga a flag; no login, redireciona para a troca antes de qualquer outra tela.
- Trocar senha incrementa `auth_version` e regenera a sessão.

**Prós:** resolve senha temporária compartilhada fora do sistema e senha fraca. **Contras:** a troca forçada precisa de uma tela dedicada (pequena).

### Decisão 4 — Autenticação centralizada / 2FA (para o futuro)
| Opção | Avaliação |
|---|---|
| TOTP (app autenticador) para admins | Média complexidade; alto ganho para as 4 contas admin. **Próxima ADR**, não nesta. |
| LDAP/Active Directory | Só vale se a fundação já tem AD; elimina senhas locais. **Verificar com a TI** antes de decidir. |
| Manter só senha local | Aceitável **depois** das fases 1-2 abaixo. |

## Análise de trade-offs

- **Segurança × disponibilidade:** o bloqueio por usuário global foi trocado por alerta justamente para um estranho não conseguir travar o admin do hospital; o bloqueio por IP protege sem esse risco.
- **Simplicidade × recursos:** `auth_version` dá "encerrar todas as sessões" com uma coluna; sessões em tabela só se houver necessidade real.
- **Usabilidade × rigidez:** 10 caracteres + lista de proibidas bloqueia o óbvio sem impor símbolos; a troca forçada é um passo a mais, só uma vez.
- **Compatibilidade:** `COLLATE NOCASE` exige checar duplicatas antes (hoje não há: 6 usuários distintos).

## Consequências

**Fica mais fácil:** responder a conta comprometida (trocar a senha derruba todas as sessões); auditar ataques (`LOGIN_ALERTA`, bloqueios deduplicados); entregar senha temporária com segurança.

**Fica mais difícil:** o schema passa a evoluir por migrações numeradas (`schema_version`); `Auth` ganha responsabilidade e deve ser dividido (ver abaixo).

**A revisitar:** TOTP para admins; AD/LDAP; sessões em tabela; CAPTCHA (hoje desnecessário nesse porte).

## Mapa achado → solução

| Achado | Solução | Onde |
|---|---|---|
| 1 | expiração sem `session_destroy`; flash de expirada | `Auth::user`, `Application::dispatch` |
| 2 | 3 contadores, transação, `trusted_proxies` | `Auth` (novo `LoginThrottle`), `config.php` |
| 3 | `gc_maxlifetime`, teto absoluto, `Secure` via proxy confiável, HSTS | `Application::startSession` |
| 4 | `password_verify` contra hash fictício | `Auth::attempt`, `changePassword` |
| 5 | log só de login existente (senão prefixo/hash); 1 `LOGIN_BLOQUEADO` por janela | `Auth::attempt`, `Logger` |
| 6 | `auth_version`, `must_change_password`, regenerar sessão | `Auth`, `bin/create-user.php`, nova view |
| 7 | `COLLATE NOCASE` + checagem de duplicatas na migração | `Database::migrate` |
| 8 | política 10+ e lista de proibidas | `Auth::validatePassword` |
| 9 | limpeza 1/50 + índice | `Auth`, `Database::migrate` |
| 10 | `<button>`, `?form=change` | `login.php`, `login.js`, `AuthController` |

**Refatoração junto:** extrair de `Auth` a classe `App\LoginThrottle` (limites, IP, limpeza) e `App\PasswordPolicy` (regras). `attempt()` e `changePassword()` hoje repetem a lógica de bloqueio/falha; ambos passam a usar `LoginThrottle`.

## Ações

**Fase 1 — correções diretas, sem mudança de schema (≈ meio dia)**
1. [ ] Expiração sem destruir a sessão + flash "Sua sessão expirou" (achado 1).
2. [ ] `session.gc_maxlifetime`, `Secure` e HSTS (achado 3, parte sem proxy).
3. [ ] Hash fictício contra enumeração (4).
4. [ ] Log sem texto digitado; dedupe de bloqueios (5).
5. [ ] Botões acessíveis e `?form=change` (10).
6. [ ] Testes: expirar sessão e logar na 1ª tentativa; tempo de resposta de login inexistente vs. existente (diferença < 20 ms).

**Fase 2 — schema e limites (≈ 1 dia)**
7. [ ] `schema_version` + migrações numeradas em `Database` (pré-requisito).
8. [ ] Colunas `auth_version`, `must_change_password`, `password_changed_at`; índice `login_attempts(attempted_at)`.
9. [ ] `LoginThrottle` com 3 contadores, `BEGIN IMMEDIATE`, `trusted_proxies`, limpeza 1/50 (2, 9).
10. [ ] `auth_version` na sessão e invalidação ao trocar senha/perfil (6).
11. [ ] `must_change_password` + tela de troca + `bin/create-user.php` marcando a flag; **aplicar às 6 contas atuais** (6).
12. [ ] `PasswordPolicy` (8) e `COLLATE NOCASE` após checar duplicatas (7).
13. [ ] Teste de carga simples: 50 tentativas paralelas não passam de 5 por usuário+IP.

**Fase 3 — decisões em aberto**
14. [ ] Perguntar à TI: existe AD/LDAP? Há proxy reverso? Qual?
15. [ ] Nova ADR: TOTP para administradores.
16. [ ] Botão no Painel Master "Encerrar sessões deste usuário" (usa `auth_version`) e "Desbloquear login".
17. [ ] Atualizar [docs/seguranca.md](../seguranca.md), [docs/usuarios-e-permissoes.md](../usuarios-e-permissoes.md) e [docs/fluxos.md](../fluxos.md).

## Critérios de pronto

- Os 10 achados do review marcados como corrigidos (novo `ReportFindings` com resultado).
- Roteiro de fumaça de `docs/pendencias.md` + os testes das fases 1 e 2 passando.
- Nenhuma das 6 contas com senha temporária sem `must_change_password`.
