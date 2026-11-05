# web — interface nova (Next.js 16, exportação estática)

Front da nova versão da Intranet FCECON. É uma exportação estática servida sob `/novo`, sem Node em produção, sem Server Functions e sem autenticação no Next: **quem decide acesso é só a API** (`api/`, ADR 0008).

## Comandos

```bash
npm ci               # instala as dependências
npm run lint         # ESLint
npm run typecheck    # tipos
npm test             # Vitest (unitário, componentes e contraste)
npm run build        # gera out/
```

## Experimentar com a API real

O login usa cookie de sessão e CSRF (Sanctum), então front e API precisam estar no **mesmo domínio**, como em produção. O `dev:proxy` faz isso localmente: serve `out/` sob `/novo` e repassa `/api` e `/sanctum` ao Laravel.

```bash
# 1) em api/: banco e usuário de teste, depois a API liberando a origem do proxy
SANCTUM_STATEFUL_DOMAINS=localhost:8080 php artisan serve        # http://127.0.0.1:8000

# 2) em web/
npm run build
npm run dev:proxy                                                # http://localhost:8080/novo/
```

Variáveis do proxy: `PORT` (padrão 8080) e `API_URL` (padrão `http://127.0.0.1:8000`). Só para desenvolvimento.

## Estrutura

| Caminho | O que é |
|---|---|
| `src/lib/api.ts` | Cliente da API: `GET /sanctum/csrf-cookie`, `X-XSRF-TOKEN`, `credentials: "include"`, nova tentativa única no 419 |
| `src/components/Intranet.tsx` | Pergunta à API quem está logado e mostra o login ou o painel |
| `src/components/LoginForm.tsx` | Formulário de login (acessível, com mensagens para 422, 429, 419, 5xx e falha de rede) |
| `src/app/globals.css` | Tokens de cor (tema claro e escuro) |
| `tests/` | Guardas da ADR 0008 e contraste WCAG AA dos tokens |

A fonte é a pilha do sistema (sem fonte da web). O imagotipo só entra depois da aprovação da comunicação (T029).
