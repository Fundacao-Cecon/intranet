import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";

import { ApiError, login, logout, me } from "./api";

type Resposta = { status: number; corpo?: unknown; cabecalhos?: Record<string, string>; aoChamar?: () => void };

/** Fila de respostas do `fetch`; cada chamada consome a próxima e fica registrada em `chamadas`. */
function simularApi(respostas: Resposta[]) {
  const chamadas: { url: string; init: RequestInit }[] = [];
  const fila = [...respostas];
  vi.stubGlobal(
    "fetch",
    vi.fn(async (url: string, init: RequestInit = {}) => {
      chamadas.push({ url, init });
      const r = fila.shift();
      if (!r) throw new Error(`fetch inesperado: ${url}`);
      r.aoChamar?.();
      return new Response(r.corpo === undefined ? null : JSON.stringify(r.corpo), {
        status: r.status,
        headers: { "Content-Type": "application/json", ...r.cabecalhos },
      });
    }),
  );
  return chamadas;
}

const definirXsrf = (valor: string) => () => {
  document.cookie = `XSRF-TOKEN=${encodeURIComponent(valor)}; path=/`;
};
const limparXsrf = () => {
  document.cookie = "XSRF-TOKEN=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT";
};
const ana = { id: 7, username: "ana", role: "user" };

beforeEach(limparXsrf);
afterEach(() => vi.unstubAllGlobals());

describe("login", () => {
  test("pede o cookie CSRF antes do POST e manda o token no cabeçalho X-XSRF-TOKEN", async () => {
    const chamadas = simularApi([
      { status: 204, aoChamar: definirXsrf("tok/en=1") },
      { status: 200, corpo: ana },
    ]);

    await expect(login("ana", "segredo")).resolves.toEqual(ana);

    expect(chamadas[0].url).toBe("/sanctum/csrf-cookie");
    expect(chamadas[1].url).toBe("/api/v1/auth/login");
    expect(chamadas[1].init.method).toBe("POST");
    const cab = chamadas[1].init.headers as Record<string, string>;
    expect(cab["X-XSRF-TOKEN"]).toBe("tok/en=1"); // o cookie vem codificado; o cabeçalho, não
    expect(cab["Accept"]).toBe("application/json");
    expect(JSON.parse(chamadas[1].init.body as string)).toEqual({ username: "ana", password: "segredo" });
  });

  test("manda e recebe cookies (credentials: include) em todas as chamadas", async () => {
    const chamadas = simularApi([{ status: 204, aoChamar: definirXsrf("t") }, { status: 200, corpo: ana }]);
    await login("ana", "x");
    for (const c of chamadas) expect(c.init.credentials).toBe("include");
  });

  test("senha errada vira ApiError 422 com a mensagem da API", async () => {
    simularApi([
      { status: 204, aoChamar: definirXsrf("t") },
      { status: 422, corpo: { message: "Usuário ou senha incorretos." } },
    ]);

    const erro = await login("ana", "errada").catch((e) => e);

    expect(erro).toBeInstanceOf(ApiError);
    expect(erro).toMatchObject({ status: 422, message: "Usuário ou senha incorretos." });
  });

  test("bloqueio (429) carrega o Retry-After em segundos", async () => {
    simularApi([
      { status: 204, aoChamar: definirXsrf("t") },
      { status: 429, corpo: { message: "Muitas tentativas." }, cabecalhos: { "Retry-After": "600" } },
    ]);

    const erro = await login("ana", "x").catch((e) => e);

    expect(erro).toMatchObject({ status: 429, retryAfter: 600 });
  });

  test("419: renova o token CSRF e repete o POST uma única vez", async () => {
    const chamadas = simularApi([
      { status: 204, aoChamar: definirXsrf("velho") },
      { status: 419, corpo: { message: "CSRF token mismatch." } },
      { status: 204, aoChamar: definirXsrf("novo") },
      { status: 200, corpo: ana },
    ]);

    await expect(login("ana", "x")).resolves.toEqual(ana);

    expect(chamadas.map((c) => c.url)).toEqual([
      "/sanctum/csrf-cookie",
      "/api/v1/auth/login",
      "/sanctum/csrf-cookie",
      "/api/v1/auth/login",
    ]);
    expect((chamadas[3].init.headers as Record<string, string>)["X-XSRF-TOKEN"]).toBe("novo");
  });

  test("419 de novo depois da repetição vira erro (sem laço infinito)", async () => {
    simularApi([
      { status: 204, aoChamar: definirXsrf("a") },
      { status: 419, corpo: {} },
      { status: 204, aoChamar: definirXsrf("b") },
      { status: 419, corpo: {} },
    ]);

    await expect(login("ana", "x")).rejects.toMatchObject({ status: 419 });
  });

  test("resposta que não é JSON não derruba: o erro leva o status", async () => {
    vi.stubGlobal(
      "fetch",
      vi
        .fn()
        .mockResolvedValueOnce(new Response(null, { status: 204 }))
        .mockResolvedValueOnce(new Response("<html>Bad Gateway</html>", { status: 502 })),
    );

    await expect(login("ana", "x")).rejects.toMatchObject({ status: 502 });
  });
});

describe("me", () => {
  test("devolve o usuário quando há sessão", async () => {
    const chamadas = simularApi([{ status: 200, corpo: ana }]);

    await expect(me()).resolves.toEqual(ana);
    expect(chamadas[0].url).toBe("/api/v1/auth/me");
    expect(chamadas[0].init.method ?? "GET").toBe("GET");
  });

  test("401 significa visitante: devolve null, não lança", async () => {
    simularApi([{ status: 401, corpo: { message: "Unauthenticated." } }]);

    await expect(me()).resolves.toBeNull();
  });

  test("outros erros (500) são lançados", async () => {
    simularApi([{ status: 500, corpo: { message: "Server Error" } }]);

    await expect(me()).rejects.toMatchObject({ status: 500 });
  });
});

describe("logout", () => {
  test("faz POST com o token CSRF e aceita 204", async () => {
    definirXsrf("tk")();
    const chamadas = simularApi([{ status: 204 }]);

    await expect(logout()).resolves.toBeUndefined();

    expect(chamadas[0].url).toBe("/api/v1/auth/logout");
    expect((chamadas[0].init.headers as Record<string, string>)["X-XSRF-TOKEN"]).toBe("tk");
  });
});
