"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import { useForm } from "react-hook-form";

import { ApiError, type Usuario } from "@/lib/api";
import { type DadosLogin, esquemaLogin } from "@/lib/validacao";
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
  // Erro que veio da API (senha errada, bloqueio, rede). Os erros de preenchimento são do RHF.
  const [erro, setErro] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    resetField,
    setFocus,
    formState: { errors, isSubmitting },
  } = useForm<DadosLogin>({
    resolver: zodResolver(esquemaLogin),
    defaultValues: { usuario: "", senha: "" },
  });

  async function enviar({ usuario, senha }: DadosLogin) {
    setErro(null);
    try {
      aoEntrar(await entrar(usuario, senha));
    } catch (e) {
      setErro(mensagemDeErro(e));
      resetField("senha"); // a senha digitada não fica na tela nem no estado
      setFocus("senha");
    }
  }

  return (
    <main className={styles.centro}>
      <form
        className={styles.cartao}
        onSubmit={handleSubmit(enviar)}
        aria-labelledby="titulo-login"
        noValidate
      >
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
          type="text"
          autoComplete="username"
          autoCapitalize="none"
          spellCheck={false}
          aria-required="true"
          aria-invalid={errors.usuario ? true : undefined}
          aria-describedby={errors.usuario ? "usuario-erro" : undefined}
          {...register("usuario")}
        />
        {errors.usuario && (
          <p id="usuario-erro" className={styles.erroCampo}>
            {errors.usuario.message}
          </p>
        )}

        <label className={styles.rotulo} htmlFor="senha">
          Senha
        </label>
        <input
          id="senha"
          className={styles.campo}
          type="password"
          autoComplete="current-password"
          aria-required="true"
          aria-invalid={errors.senha ? true : undefined}
          aria-describedby={errors.senha ? "senha-erro" : undefined}
          {...register("senha")}
        />
        {errors.senha && (
          <p id="senha-erro" className={styles.erroCampo}>
            {errors.senha.message}
          </p>
        )}

        <button className={styles.botao} type="submit" disabled={isSubmitting}>
          {isSubmitting ? "Entrando…" : "Entrar"}
        </button>
      </form>
    </main>
  );
}
