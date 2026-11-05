import { describe, expect, test } from "vitest";

import { esquemaLogin, esquemaTrocaSenha } from "./validacao";

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


function errosTroca(dados: unknown): Record<string, string[]> {
  const r = esquemaTrocaSenha.safeParse(dados);
  if (r.success) return {};
  const porCampo: Record<string, string[]> = {};
  for (const i of r.error.issues) (porCampo[String(i.path[0])] ??= []).push(i.message);
  return porCampo;
}

const valida = { senhaAtual: "senha-atual-1", novaSenha: "Tr0ca-Segura-2026", confirmacao: "Tr0ca-Segura-2026" };

describe("esquemaTrocaSenha", () => {
  test("aceita os três campos certos", () => {
    expect(esquemaTrocaSenha.safeParse(valida).success).toBe(true);
  });

  test("os três campos vazios dão um erro em cada campo", () => {
    expect(errosTroca({ senhaAtual: "", novaSenha: "", confirmacao: "" })).toEqual({
      senhaAtual: ["Informe a senha atual."],
      novaSenha: ["Informe a nova senha."],
      confirmacao: ["Confirme a nova senha."],
    });
  });

  test("a nova senha precisa de 10 caracteres, contando caracteres e não bytes", () => {
    expect(errosTroca({ ...valida, novaSenha: "curta", confirmacao: "curta" })).toEqual({
      novaSenha: ["A nova senha precisa ter pelo menos 10 caracteres."],
    });
    expect(esquemaTrocaSenha.safeParse({ ...valida, novaSenha: "áéíóú-ãõç-12", confirmacao: "áéíóú-ãõç-12" }).success).toBe(true);
    expect(errosTroca({ ...valida, novaSenha: "áéíóúãõçà", confirmacao: "áéíóúãõçà" })).toEqual({
      novaSenha: ["A nova senha precisa ter pelo menos 10 caracteres."],
    });
  });

  test("limite superior de 128 caracteres, igual ao da API", () => {
    const longa = "a b ".repeat(32); // 128
    expect(esquemaTrocaSenha.safeParse({ ...valida, novaSenha: longa, confirmacao: longa }).success).toBe(true);
    expect(errosTroca({ ...valida, novaSenha: longa + "x", confirmacao: longa + "x" })).toEqual({
      novaSenha: ["A nova senha pode ter no máximo 128 caracteres."],
    });
  });

  test("a confirmação precisa ser igual à nova senha", () => {
    expect(errosTroca({ ...valida, confirmacao: "Outra-Coisa-2026" })).toEqual({
      confirmacao: ["As senhas não conferem."],
    });
  });

  test("a nova senha precisa ser diferente da atual", () => {
    expect(errosTroca({ senhaAtual: "Tr0ca-Segura-2026", novaSenha: "Tr0ca-Segura-2026", confirmacao: "Tr0ca-Segura-2026" })).toEqual({
      novaSenha: ["A nova senha precisa ser diferente da atual."],
    });
  });

  test("nenhuma senha é aparada: espaços fazem parte dela", () => {
    const r = esquemaTrocaSenha.parse({ senhaAtual: "  a  ", novaSenha: "  nova senha ok  ", confirmacao: "  nova senha ok  " });
    expect(r.senhaAtual).toBe("  a  ");
    expect(r.novaSenha).toBe("  nova senha ok  ");
  });

  test("não repete a política da API: senha comum passa aqui e a API recusa", () => {
    expect(esquemaTrocaSenha.safeParse({ ...valida, novaSenha: "password123", confirmacao: "password123" }).success).toBe(true);
  });

  test("conta caracteres como a API (mb_strlen): emoji vale 1, e não 2 como no .length do JavaScript", () => {
    const cinco = "😀🐶🌵🎸🚀"; // 5 caracteres, mas length 10 em JavaScript
    const dez = "😀🐶🌵🎸🚀🍕🌈🎲🔥🌟";
    expect(errosTroca({ ...valida, novaSenha: cinco, confirmacao: cinco })).toEqual({
      novaSenha: ["A nova senha precisa ter pelo menos 10 caracteres."],
    });
    expect(esquemaTrocaSenha.safeParse({ ...valida, novaSenha: dez, confirmacao: dez }).success).toBe(true);
  });
});
