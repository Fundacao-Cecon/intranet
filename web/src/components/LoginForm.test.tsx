import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, type Mock, test, vi } from "vitest";

import { ApiError, type Usuario } from "@/lib/api";
import { LoginForm } from "./LoginForm";

const ana = { id: 7, username: "ana", role: "user" };

async function preencher(usuario: string, senha: string) {
  const u = userEvent.setup();
  await u.type(screen.getByLabelText("Usuário"), usuario);
  await u.type(screen.getByLabelText("Senha"), senha);
  await u.click(screen.getByRole("button", { name: "Entrar" }));
}

describe("LoginForm", () => {
  let entrar: Mock<(usuario: string, senha: string) => Promise<Usuario>>;
  let aoEntrar: Mock<(usuario: Usuario) => void>;
  beforeEach(() => {
    entrar = vi.fn<(usuario: string, senha: string) => Promise<Usuario>>();
    aoEntrar = vi.fn<(usuario: Usuario) => void>();
  });

  test("tem campos rotulados, com autocomplete e senha mascarada", () => {
    render(<LoginForm entrar={entrar} aoEntrar={aoEntrar} />);

    const usuario = screen.getByLabelText("Usuário");
    const senha = screen.getByLabelText("Senha");
    expect(usuario).toHaveAttribute("autocomplete", "username");
    expect(usuario).toBeRequired();
    expect(senha).toHaveAttribute("type", "password");
    expect(senha).toHaveAttribute("autocomplete", "current-password");
    expect(senha).toBeRequired();
    expect(screen.getByRole("heading", { level: 1, name: /Intranet FCECON/ })).toBeInTheDocument();
  });

  test("envia usuário e senha e avisa quem chamou com o usuário devolvido", async () => {
    entrar.mockResolvedValue(ana);
    render(<LoginForm entrar={entrar} aoEntrar={aoEntrar} />);

    await preencher("ana", "segredo");

    expect(entrar).toHaveBeenCalledWith("ana", "segredo");
    await waitFor(() => expect(aoEntrar).toHaveBeenCalledWith(ana));
    expect(screen.queryByRole("alert")).not.toBeInTheDocument();
  });

  test("não envia com os campos vazios", async () => {
    render(<LoginForm entrar={entrar} aoEntrar={aoEntrar} />);

    await userEvent.setup().click(screen.getByRole("button", { name: "Entrar" }));

    expect(entrar).not.toHaveBeenCalled();
  });

  test("senha errada mostra a mensagem da API num alerta e mantém o usuário digitado", async () => {
    entrar.mockRejectedValue(new ApiError(422, "Usuário ou senha incorretos."));
    render(<LoginForm entrar={entrar} aoEntrar={aoEntrar} />);

    await preencher("ana", "errada");

    expect(await screen.findByRole("alert")).toHaveTextContent("Usuário ou senha incorretos.");
    expect(screen.getByLabelText("Usuário")).toHaveValue("ana");
    expect(screen.getByLabelText("Senha")).toHaveValue(""); // a senha digitada não fica na tela
    expect(aoEntrar).not.toHaveBeenCalled();
  });

  test("bloqueio (429) diz em quantos minutos tentar de novo", async () => {
    entrar.mockRejectedValue(
      new ApiError(429, "Muitas tentativas. Aguarde alguns minutos e tente de novo.", undefined, 600),
    );
    render(<LoginForm entrar={entrar} aoEntrar={aoEntrar} />);

    await preencher("ana", "x");

    expect(await screen.findByRole("alert")).toHaveTextContent(/Muitas tentativas.*10 minutos/);
  });

  test("Retry-After curto arredonda para 1 minuto", async () => {
    entrar.mockRejectedValue(new ApiError(429, "Muitas tentativas.", undefined, 20));
    render(<LoginForm entrar={entrar} aoEntrar={aoEntrar} />);

    await preencher("ana", "x");

    expect(await screen.findByRole("alert")).toHaveTextContent("1 minuto");
  });

  test("419 pede para recarregar a página", async () => {
    entrar.mockRejectedValue(new ApiError(419, "CSRF token mismatch."));
    render(<LoginForm entrar={entrar} aoEntrar={aoEntrar} />);

    await preencher("ana", "x");

    expect(await screen.findByRole("alert")).toHaveTextContent(/recarregue/i);
  });

  test("falha de rede tem mensagem própria", async () => {
    entrar.mockRejectedValue(new TypeError("Failed to fetch"));
    render(<LoginForm entrar={entrar} aoEntrar={aoEntrar} />);

    await preencher("ana", "x");

    expect(await screen.findByRole("alert")).toHaveTextContent(/conexão/i);
  });

  test("erro 5xx não vaza detalhes do servidor", async () => {
    entrar.mockRejectedValue(new ApiError(500, "SQLSTATE[HY000]: tabela users"));
    render(<LoginForm entrar={entrar} aoEntrar={aoEntrar} />);

    await preencher("ana", "x");

    const alerta = await screen.findByRole("alert");
    expect(alerta).toHaveTextContent(/servidor/i);
    expect(alerta).not.toHaveTextContent("SQLSTATE");
  });

  test("enquanto envia, o botão fica desativado e avisa que está entrando (sem envio duplo)", async () => {
    let concluir: (u: typeof ana) => void = () => {};
    entrar.mockReturnValue(new Promise((r) => (concluir = r)));
    render(<LoginForm entrar={entrar} aoEntrar={aoEntrar} />);

    await preencher("ana", "segredo");

    const botao = screen.getByRole("button", { name: /Entrando/ });
    expect(botao).toBeDisabled();
    await userEvent.setup().click(botao);
    expect(entrar).toHaveBeenCalledTimes(1);

    concluir(ana);
    await waitFor(() => expect(aoEntrar).toHaveBeenCalled());
  });

  test("o aviso informado pelo pai (sessão expirada) aparece como status", () => {
    render(<LoginForm entrar={entrar} aoEntrar={aoEntrar} aviso="Sua sessão expirou. Entre novamente." />);

    expect(screen.getByRole("status")).toHaveTextContent("Sua sessão expirou. Entre novamente.");
  });
});
