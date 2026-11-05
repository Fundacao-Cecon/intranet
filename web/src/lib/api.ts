/**
 * Cliente da API (Laravel + Sanctum por cookie de sessão, mesmo domínio atrás do proxy).
 *
 * Fluxo de escrita (docs/transicao.md): `GET /sanctum/csrf-cookie` entrega o cookie XSRF-TOKEN e
 * o token segue no cabeçalho `X-XSRF-TOKEN` do POST. Toda chamada leva `credentials: "include"`.
 * Nada de autenticação ou regra de acesso mora aqui: quem decide é só a API (ADR 0008).
 */

export type Usuario = { id: number; username: string; role: string };

/** Erro de uma resposta da API. `retryAfter` (segundos) vem do cabeçalho Retry-After do 429. */
export class ApiError extends Error {
  constructor(
    readonly status: number,
    message: string,
    readonly code?: string,
    readonly retryAfter?: number,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

// Vazio = mesmo domínio (produção, atrás do proxy). Em desenvolvimento aponta para o proxy local.
const base = () => process.env.NEXT_PUBLIC_API_BASE ?? "";

function tokenXsrf(): string | undefined {
  const achado = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
  return achado ? decodeURIComponent(achado[1]) : undefined;
}

async function pedirCookieCsrf(): Promise<void> {
  await fetch(`${base()}/sanctum/csrf-cookie`, {
    credentials: "include",
    headers: { Accept: "application/json" },
  });
}

async function lerCorpo(resposta: Response): Promise<{ message?: string; code?: string } | undefined> {
  try {
    return (await resposta.json()) as { message?: string; code?: string };
  } catch {
    return undefined; // 204, ou HTML de um proxy (502): o status basta
  }
}

async function chamar<T>(
  metodo: "GET" | "POST",
  caminho: string,
  opcoes: { corpo?: unknown; csrfNovo?: boolean } = {},
): Promise<T | undefined> {
  if (metodo === "POST" && (opcoes.csrfNovo || !tokenXsrf())) await pedirCookieCsrf();

  for (let tentativa = 0; ; tentativa++) {
    const cabecalhos: Record<string, string> = { Accept: "application/json" };
    if (opcoes.corpo !== undefined) cabecalhos["Content-Type"] = "application/json";
    if (metodo === "POST") {
      const token = tokenXsrf();
      if (token) cabecalhos["X-XSRF-TOKEN"] = token;
    }

    const resposta = await fetch(`${base()}${caminho}`, {
      method: metodo,
      credentials: "include",
      headers: cabecalhos,
      body: opcoes.corpo === undefined ? undefined : JSON.stringify(opcoes.corpo),
    });

    // 419: o token CSRF venceu (a sessão foi trocada). Renova e repete uma única vez.
    if (resposta.status === 419 && metodo === "POST" && tentativa === 0) {
      await pedirCookieCsrf();
      continue;
    }

    if (!resposta.ok) {
      const corpo = await lerCorpo(resposta);
      const espera = Number(resposta.headers.get("Retry-After"));
      throw new ApiError(
        resposta.status,
        corpo?.message ?? `Erro ${resposta.status}`,
        corpo?.code,
        Number.isFinite(espera) && espera > 0 ? espera : undefined,
      );
    }

    return resposta.status === 204 ? undefined : ((await lerCorpo(resposta)) as T);
  }
}

/** Entra. Pede sempre um cookie CSRF novo: o login troca a sessão. */
export async function login(username: string, password: string): Promise<Usuario> {
  const usuario = await chamar<Usuario>("POST", "/api/v1/auth/login", {
    corpo: { username, password },
    csrfNovo: true,
  });
  return usuario as Usuario;
}

export async function logout(): Promise<void> {
  await chamar("POST", "/api/v1/auth/logout");
}

/** Quem está logado. 401 não é erro: significa visitante e devolve null. */
export async function me(): Promise<Usuario | null> {
  try {
    return (await chamar<Usuario>("GET", "/api/v1/auth/me")) as Usuario;
  } catch (erro) {
    if (erro instanceof ApiError && erro.status === 401) return null;
    throw erro;
  }
}
