import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, test, vi } from "vitest";

import * as api from "@/lib/api";
import { Intranet } from "./Intranet";

vi.mock("@/lib/api", async (importOriginal) => {
  const real = await importOriginal<typeof import("@/lib/api")>();
  return { ...real, me: vi.fn(), login: vi.fn(), logout: vi.fn() };
});

const ana = { id: 7, username: "ana", role: "user" };
const adm = { id: 1, username: "adm", role: "admin" };

beforeEach(() => vi.resetAllMocks());

describe("Intranet", () => {
  test("enquanto consulta a sessão mostra Carregando, sem piscar o login", () => {
    vi.mocked(api.me).mockReturnValue(new Promise(() => {}));
    render(<Intranet />);

    expect(screen.getByRole("status")).toHaveTextContent(/Carregando/);
    expect(screen.queryByLabelText("Usuário")).not.toBeInTheDocument();
  });

  test("sem sessão (me devolve null) mostra o formulário de login", async () => {
    vi.mocked(api.me).mockResolvedValue(null);
    render(<Intranet />);

    expect(await screen.findByLabelText("Usuário")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Sair" })).not.toBeInTheDocument();
  });

  test("com sessão ativa vai direto ao painel, com o nome e o perfil", async () => {
    vi.mocked(api.me).mockResolvedValue(adm);
    render(<Intranet />);

    expect(await screen.findByRole("heading", { name: /Olá, adm/ })).toBeInTheDocument();
    expect(screen.getByText(/Administrador/)).toBeInTheDocument();
    expect(screen.queryByLabelText("Usuário")).not.toBeInTheDocument();
  });

  test("login certo troca o formulário pelo painel", async () => {
    vi.mocked(api.me).mockResolvedValue(null);
    vi.mocked(api.login).mockResolvedValue(ana);
    render(<Intranet />);

    const u = userEvent.setup();
    await u.type(await screen.findByLabelText("Usuário"), "ana");
    await u.type(screen.getByLabelText("Senha"), "segredo");
    await u.click(screen.getByRole("button", { name: "Entrar" }));

    expect(await screen.findByRole("heading", { name: /Olá, ana/ })).toBeInTheDocument();
    expect(screen.getByText(/Usuário comum/)).toBeInTheDocument();
    expect(api.login).toHaveBeenCalledWith("ana", "segredo");
  });

  test("Sair encerra a sessão na API e volta ao login", async () => {
    vi.mocked(api.me).mockResolvedValue(ana);
    vi.mocked(api.logout).mockResolvedValue(undefined);
    render(<Intranet />);

    await userEvent.setup().click(await screen.findByRole("button", { name: "Sair" }));

    expect(api.logout).toHaveBeenCalledTimes(1);
    expect(await screen.findByLabelText("Usuário")).toBeInTheDocument();
  });

  test("se o logout falhar, mostra o erro e continua no painel (não finge que saiu)", async () => {
    vi.mocked(api.me).mockResolvedValue(ana);
    vi.mocked(api.logout).mockRejectedValue(new api.ApiError(500, "erro"));
    render(<Intranet />);

    await userEvent.setup().click(await screen.findByRole("button", { name: "Sair" }));

    expect(await screen.findByRole("alert")).toHaveTextContent(/não foi possível sair/i);
    expect(screen.getByRole("heading", { name: /Olá, ana/ })).toBeInTheDocument();
  });

  test("falha ao consultar a sessão (servidor fora) mostra erro com botão de tentar de novo", async () => {
    vi.mocked(api.me).mockRejectedValueOnce(new api.ApiError(503, "indisponível"));
    render(<Intranet />);

    expect(await screen.findByRole("alert")).toHaveTextContent(/não foi possível/i);

    vi.mocked(api.me).mockResolvedValueOnce(null);
    await userEvent.setup().click(screen.getByRole("button", { name: "Tentar de novo" }));
    expect(await screen.findByLabelText("Usuário")).toBeInTheDocument();
  });

  test("o aviso inicial (sessão expirada) aparece no login", async () => {
    vi.mocked(api.me).mockResolvedValue(null);
    render(<Intranet avisoInicial="Sua sessão expirou. Entre novamente." />);

    expect(await screen.findByText("Sua sessão expirou. Entre novamente.")).toBeInTheDocument();
  });
});
