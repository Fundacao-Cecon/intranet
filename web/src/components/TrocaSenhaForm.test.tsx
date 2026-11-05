import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, type Mock, test, vi } from "vitest";

import { ApiError } from "@/lib/api";
import { TrocaSenhaForm } from "./TrocaSenhaForm";

const NOVA = "Tr0ca-Segura-2026";

async function preencher(atual: string, nova: string, confirmacao: string = nova) {
  const u = userEvent.setup();
  await u.type(screen.getByLabelText("Senha atual"), atual);
  await u.type(screen.getByLabelText("Nova senha"), nova);
  await u.type(screen.getByLabelText("Confirmar nova senha"), confirmacao);
  await u.click(screen.getByRole("button", { name: "Trocar senha" }));
}

describe("TrocaSenhaForm", () => {
  let trocar: Mock<(atual: string, nova: string) => Promise<void>>;
  let aoVoltar: Mock<() => void>;
  let aoExpirar: Mock<() => void>;
  beforeEach(() => {
    trocar = vi.fn<(atual: string, nova: string) => Promise<void>>();
    aoVoltar = vi.fn<() => void>();
    aoExpirar = vi.fn<() => void>();
  });
  const montar = () => render(<TrocaSenhaForm trocar={trocar} aoVoltar={aoVoltar} aoExpirar={aoExpirar} />);

  test("tem três campos de senha rotulados, com os autocompletes certos", () => {
    montar();

    for (const nome of ["Senha atual", "Nova senha", "Confirmar nova senha"]) {
      expect(screen.getByLabelText(nome)).toHaveAttribute("type", "password");
    }
    expect(screen.getByLabelText("Senha atual")).toHaveAttribute("autocomplete", "current-password");
    expect(screen.getByLabelText("Nova senha")).toHaveAttribute("autocomplete", "new-password");
    expect(screen.getByLabelText("Confirmar nova senha")).toHaveAttribute("autocomplete", "new-password");
    expect(screen.getByRole("heading", { level: 1, name: /Trocar senha/ })).toBeInTheDocument();
  });

  test("dá a dica da política sem repetir regras que são da API", () => {
    montar();

    expect(screen.getByLabelText("Nova senha")).toHaveAccessibleDescription(/10 caracteres/);
  });

  test("enviar vazio mostra um resumo com os três erros e não chama a API", async () => {
    montar();

    await userEvent.setup().click(screen.getByRole("button", { name: "Trocar senha" }));

    const alerta = await screen.findByRole("alert");
    expect(alerta).toHaveTextContent("Informe a senha atual.");
    expect(alerta).toHaveTextContent("Informe a nova senha.");
    expect(alerta).toHaveTextContent("Confirme a nova senha.");
    expect(screen.getAllByRole("alert")).toHaveLength(1);
    expect(trocar).not.toHaveBeenCalled();
  });

  test("confirmação diferente da nova senha é recusada antes de chamar a API", async () => {
    montar();

    await preencher("atual-1", NOVA, "outra-coisa-2026");

    expect(await screen.findByText("As senhas não conferem.")).toBeInTheDocument();
    expect(trocar).not.toHaveBeenCalled();
  });

  test("envia a senha atual e a nova, sem aparar espaços", async () => {
    trocar.mockResolvedValue(undefined);
    montar();

    await preencher(" atual 1 ", " nova senha ok ");

    await waitFor(() => expect(trocar).toHaveBeenCalledWith(" atual 1 ", " nova senha ok "));
  });

  test("sucesso: avisa, limpa os três campos e não mostra erro", async () => {
    trocar.mockResolvedValue(undefined);
    montar();

    await preencher("atual-1", NOVA);

    expect(await screen.findByRole("status")).toHaveTextContent(/Senha alterada/);
    expect(screen.getByRole("status")).toHaveTextContent(/outros dispositivos|outras sessões/i);
    for (const nome of ["Senha atual", "Nova senha", "Confirmar nova senha"]) {
      expect(screen.getByLabelText(nome)).toHaveValue("");
    }
    expect(screen.queryByRole("alert")).not.toBeInTheDocument();
  });

  test("enquanto envia, o botão fica desativado (sem envio duplo)", async () => {
    let concluir: () => void = () => {};
    trocar.mockReturnValue(new Promise<void>((r) => (concluir = r)));
    montar();

    await preencher("atual-1", NOVA);

    const botao = screen.getByRole("button", { name: /Trocando/ });
    expect(botao).toBeDisabled();
    await userEvent.setup().click(botao);
    expect(trocar).toHaveBeenCalledTimes(1);
    concluir();
    await screen.findByRole("status");
  });

  test("senha atual errada: erro no campo, e só a senha atual é limpa", async () => {
    trocar.mockRejectedValue(
      new ApiError(422, "A senha atual está incorreta.", undefined, undefined, {
        current_password: ["A senha atual está incorreta."],
      }),
    );
    montar();

    await preencher("errada-1", NOVA);

    expect(await screen.findByText("A senha atual está incorreta.", { selector: "p[id]" })).toBeInTheDocument();
    expect(screen.getByLabelText("Senha atual")).toHaveAttribute("aria-invalid", "true");
    expect(screen.getByLabelText("Senha atual")).toHaveValue("");
    expect(screen.getByLabelText("Nova senha")).toHaveValue(NOVA);
  });

  test("política recusada pela API: todas as mensagens no campo da nova senha, que é limpa junto da confirmação", async () => {
    trocar.mockRejectedValue(
      new ApiError(422, "x", undefined, undefined, {
        password: ["Essa senha é muito comum. Escolha outra.", "Evite sequências e repetições (como 1234567890 ou aaaaaaaaaa)."],
      }),
    );
    montar();

    await preencher("atual-1", "password123");

    const campo = screen.getByLabelText("Nova senha");
    await waitFor(() => expect(campo).toHaveAttribute("aria-invalid", "true"));
    expect(campo).toHaveAccessibleDescription(/muito comum/);
    expect(campo).toHaveAccessibleDescription(/sequências/);
    expect(campo).toHaveValue("");
    expect(screen.getByLabelText("Confirmar nova senha")).toHaveValue("");
    expect(screen.getByLabelText("Senha atual")).toHaveValue("atual-1"); // a atual estava certa
  });

  test("bloqueio (429) vira alerta com os minutos, e nenhum campo é marcado", async () => {
    trocar.mockRejectedValue(new ApiError(429, "Muitas tentativas. Aguarde alguns minutos e tente de novo.", undefined, 600));
    montar();

    await preencher("atual-1", NOVA);

    expect(await screen.findByRole("alert")).toHaveTextContent(/Muitas tentativas.*10 minutos/);
    expect(screen.getByLabelText("Senha atual")).not.toHaveAttribute("aria-invalid", "true");
  });

  test("erro 5xx não vaza detalhes do servidor", async () => {
    trocar.mockRejectedValue(new ApiError(500, "SQLSTATE[HY000]: tabela users"));
    montar();

    await preencher("atual-1", NOVA);

    const alerta = await screen.findByRole("alert");
    expect(alerta).toHaveTextContent(/servidor/i);
    expect(alerta).not.toHaveTextContent("SQLSTATE");
  });

  test("falha de rede tem mensagem própria e mantém o que foi digitado", async () => {
    trocar.mockRejectedValue(new TypeError("Failed to fetch"));
    montar();

    await preencher("atual-1", NOVA);

    expect(await screen.findByRole("alert")).toHaveTextContent(/conexão/i);
    expect(screen.getByLabelText("Nova senha")).toHaveValue(NOVA);
  });

  test("sessão expirada (401) avisa quem chamou e não mostra erro na tela", async () => {
    trocar.mockRejectedValue(new ApiError(401, "Sua sessão expirou.", "session_expired"));
    montar();

    await preencher("atual-1", NOVA);

    await waitFor(() => expect(aoExpirar).toHaveBeenCalledTimes(1));
  });

  test("o erro antigo da API some quando o reenvio tem campo inválido e não volta ao corrigir", async () => {
    trocar.mockRejectedValue(new ApiError(500, "erro"));
    montar();
    await preencher("atual-1", NOVA);
    expect(await screen.findByRole("alert")).toHaveTextContent(/servidor/i);

    const u = userEvent.setup();
    await u.clear(screen.getByLabelText("Confirmar nova senha"));
    await u.click(screen.getByRole("button", { name: "Trocar senha" }));
    await waitFor(() => expect(screen.getByRole("alert")).toHaveTextContent(/Corrija os campos/));
    expect(screen.getByRole("alert")).not.toHaveTextContent(/servidor/i);

    await u.type(screen.getByLabelText("Confirmar nova senha"), NOVA);
    await waitFor(() => expect(screen.queryByRole("alert")).not.toBeInTheDocument());
  });

  test("Voltar chama quem abriu a tela", async () => {
    montar();

    await userEvent.setup().click(screen.getByRole("button", { name: "Voltar" }));

    expect(aoVoltar).toHaveBeenCalledTimes(1);
  });

  test("usa validação própria (noValidate) e o formulário tem nome", () => {
    montar();

    expect(screen.getByRole("form", { name: /Trocar senha/ })).toHaveAttribute("novalidate");
  });
});
