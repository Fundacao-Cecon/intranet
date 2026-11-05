"use client";

import { type FormEvent, useState } from "react";

import { ApiError, type Usuario } from "@/lib/api";
import styles from "./Intranet.module.css";

type Props = {
  entrar: (usuario: string, senha: string) => Promise<Usuario>;
  aoEntrar: (usuario: Usuario) => void;
  /** Aviso informativo acima do formulário (por exemplo, "Sua sessão expirou"). */
  aviso?: string;
};

/** Texto para o usuário. Nunca repassa o texto cru de erro 5xx (pode trazer detalhes do servidor). */
function mensagemDeErro(erro: unknown): string {
  if (erro instanceof ApiError) {
    if (erro.status === 429) {
      const minutos = Math.max(1, Math.ceil((erro.retryAfter ?? 60) / 60));
      return `${erro.message} Tente de novo em cerca de ${minutos} ${minutos === 1 ? "minuto" : "minutos"}.`;
    }
    if (erro.status === 419) return "A página ficou desatualizada. Recarregue e tente de novo.";
    if (erro.status >= 500) return "Erro no servidor. Tente de novo em instantes.";
    return erro.message;
  }
  return "Não foi possível falar com o servidor. Verifique a conexão e tente de novo.";
}

export function LoginForm({ entrar, aoEntrar, aviso }: Props) {
  const [usuario, setUsuario] = useState("");
  const [senha, setSenha] = useState("");
  const [enviando, setEnviando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);

  async function enviar(evento: FormEvent<HTMLFormElement>) {
    evento.preventDefault();
    if (enviando) return;
    setEnviando(true);
    setErro(null);
    try {
      aoEntrar(await entrar(usuario, senha));
    } catch (e) {
      setErro(mensagemDeErro(e));
      setSenha(""); // a senha digitada não fica na tela nem no estado
      setEnviando(false);
    }
  }

  return (
    <main className={styles.centro}>
      <form className={styles.cartao} onSubmit={enviar} aria-labelledby="titulo-login">
        <h1 id="titulo-login" className={styles.titulo}>
          Intranet FCECON
        </h1>
        <p className={styles.subtitulo}>Entre com o seu usuário e senha.</p>

        {aviso && (
          <p role="status" className={styles.aviso}>
            {aviso}
          </p>
        )}
        {erro && (
          <p role="alert" className={styles.erro}>
            {erro}
          </p>
        )}

        <label className={styles.rotulo} htmlFor="usuario">
          Usuário
        </label>
        <input
          id="usuario"
          className={styles.campo}
          name="username"
          type="text"
          autoComplete="username"
          autoCapitalize="none"
          spellCheck={false}
          required
          value={usuario}
          onChange={(e) => setUsuario(e.target.value)}
        />

        <label className={styles.rotulo} htmlFor="senha">
          Senha
        </label>
        <input
          id="senha"
          className={styles.campo}
          name="password"
          type="password"
          autoComplete="current-password"
          required
          value={senha}
          onChange={(e) => setSenha(e.target.value)}
        />

        <button className={styles.botao} type="submit" disabled={enviando}>
          {enviando ? "Entrando…" : "Entrar"}
        </button>
      </form>
    </main>
  );
}
