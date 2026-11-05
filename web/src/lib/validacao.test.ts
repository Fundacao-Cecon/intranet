import { describe, expect, test } from "vitest";

import { esquemaLogin } from "./validacao";

/** Mensagens de erro de um objeto inválido, por campo. */
function erros(dados: unknown): Record<string, string[]> {
  const r = esquemaLogin.safeParse(dados);
  if (r.success) return {};
  const porCampo: Record<string, string[]> = {};
  for (const i of r.error.issues) (porCampo[String(i.path[0])] ??= []).push(i.message);
  return porCampo;
}

describe("esquemaLogin", () => {
  test("aceita usuário e senha preenchidos", () => {
    expect(esquemaLogin.safeParse({ usuario: "ana", senha: "segredo" }).success).toBe(true);
  });

  test("usuário vazio ou só com espaços é recusado com a mensagem certa", () => {
    expect(erros({ usuario: "", senha: "x" })).toEqual({ usuario: ["Informe o usuário."] });
    expect(erros({ usuario: "   ", senha: "x" })).toEqual({ usuario: ["Informe o usuário."] });
  });

  test("senha vazia é recusada", () => {
    expect(erros({ usuario: "ana", senha: "" })).toEqual({ senha: ["Informe a senha."] });
  });

  test("os dois campos vazios dão um erro em cada campo (nenhum se perde)", () => {
    expect(erros({ usuario: "", senha: "" })).toEqual({
      usuario: ["Informe o usuário."],
      senha: ["Informe a senha."],
    });
  });

  test("o usuário é aparado (espaço digitado sem querer não faz o login falhar)", () => {
    expect(esquemaLogin.parse({ usuario: "  ana  ", senha: "x" }).usuario).toBe("ana");
  });

  test("a senha NUNCA é aparada: espaços fazem parte dela", () => {
    expect(esquemaLogin.parse({ usuario: "ana", senha: "  segredo  " }).senha).toBe("  segredo  ");
    expect(esquemaLogin.safeParse({ usuario: "ana", senha: "   " }).success).toBe(true);
  });

  test("respeita os mesmos limites da API (usuário 255, senha 1024), com mensagem própria", () => {
    expect(esquemaLogin.safeParse({ usuario: "a".repeat(255), senha: "x" }).success).toBe(true);
    expect(erros({ usuario: "a".repeat(256), senha: "x" })).toEqual({ usuario: ["O usuário é longo demais."] });
    expect(esquemaLogin.safeParse({ usuario: "ana", senha: "x".repeat(1024) }).success).toBe(true);
    expect(erros({ usuario: "ana", senha: "x".repeat(1025) })).toEqual({ senha: ["A senha é longa demais."] });
  });

  test("não é um esquema de política de senha: a regra de 10 caracteres é da API (T024)", () => {
    expect(esquemaLogin.safeParse({ usuario: "ana", senha: "curta" }).success).toBe(true);
  });

  test("valor que não é texto é recusado sem lançar exceção", () => {
    expect(esquemaLogin.safeParse({ usuario: 123, senha: null }).success).toBe(false);
    expect(esquemaLogin.safeParse(undefined).success).toBe(false);
  });
});
