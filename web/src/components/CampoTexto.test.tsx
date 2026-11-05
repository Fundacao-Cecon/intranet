import { render, screen } from "@testing-library/react";
import { createRef } from "react";
import { describe, expect, test } from "vitest";

import { CampoTexto } from "./CampoTexto";

describe("CampoTexto", () => {
  test("liga o rótulo ao campo e marca como obrigatório", () => {
    render(<CampoTexto id="usuario" rotulo="Usuário" />);

    const campo = screen.getByLabelText("Usuário");
    expect(campo).toHaveAttribute("id", "usuario");
    expect(campo).toHaveAttribute("aria-required", "true");
  });

  test("sem erro: não marca inválido, não liga descrição e não mostra mensagem", () => {
    render(<CampoTexto id="usuario" rotulo="Usuário" />);

    const campo = screen.getByLabelText("Usuário");
    expect(campo).not.toHaveAttribute("aria-invalid");
    expect(campo).not.toHaveAttribute("aria-describedby");
    expect(campo).toHaveAccessibleDescription("");
  });

  test("com erro: marca inválido e liga a mensagem ao campo pela descrição", () => {
    render(<CampoTexto id="senha" rotulo="Senha" erro="Informe a senha." />);

    const campo = screen.getByLabelText("Senha");
    expect(campo).toHaveAttribute("aria-invalid", "true");
    expect(campo).toHaveAttribute("aria-describedby", "senha-erro");
    expect(campo).toHaveAccessibleDescription("Informe a senha.");
    expect(screen.getByText("Informe a senha.")).toHaveAttribute("id", "senha-erro");
  });

  test("a mensagem de campo não é um alerta próprio (o resumo do formulário é quem anuncia)", () => {
    render(<CampoTexto id="senha" rotulo="Senha" erro="Informe a senha." />);

    expect(screen.queryByRole("alert")).not.toBeInTheDocument();
  });

  test("repassa as propriedades do input (tipo, autocomplete, etc.)", () => {
    render(<CampoTexto id="senha" rotulo="Senha" type="password" autoComplete="current-password" name="senha" />);

    const campo = screen.getByLabelText("Senha");
    expect(campo).toHaveAttribute("type", "password");
    expect(campo).toHaveAttribute("autocomplete", "current-password");
    expect(campo).toHaveAttribute("name", "senha");
  });

  test("repassa o ref ao input (é por onde o React Hook Form controla o campo)", () => {
    const ref = createRef<HTMLInputElement>();
    render(<CampoTexto id="usuario" rotulo="Usuário" ref={ref} />);

    expect(ref.current).toBe(screen.getByLabelText("Usuário"));
  });

  test("as propriedades do chamador não desligam o vínculo de acessibilidade do erro", () => {
    render(<CampoTexto id="usuario" rotulo="Usuário" erro="Informe o usuário." aria-describedby="outro" />);

    expect(screen.getByLabelText("Usuário")).toHaveAttribute("aria-describedby", "usuario-erro");
  });
});
