"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import { useForm } from "react-hook-form";

import type { Usuario } from "@/lib/api";
import { mensagemDeErro } from "@/lib/erros";
import { type DadosLogin, esquemaLogin } from "@/lib/validacao";
import { CampoTexto } from "./CampoTexto";
import styles from "./Intranet.module.css";

type Props = {
  entrar: (usuario: string, senha: string) => Promise<Usuario>;
  aoEntrar: (usuario: Usuario) => void;
  /** Aviso informativo acima do formulário (por exemplo, "Sua sessão expirou"). */
  aviso?: string;
};

export function LoginForm({ entrar, aoEntrar, aviso }: Props) {
  // Erro que veio da API (senha errada, bloqueio, rede). Os erros de preenchimento são do RHF.
  const [erroDaApi, setErroDaApi] = useState<string | null>(null);

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
    setErroDaApi(null);
    try {
      aoEntrar(await entrar(usuario, senha));
    } catch (e) {
      setErroDaApi(mensagemDeErro(e));
      resetField("senha"); // a senha digitada não fica na tela nem no estado
      setFocus("senha");
    }
  }

  // Um único alerta no topo: o resumo dos erros de preenchimento (anunciados todos de uma vez)
  // ou, se não houver nenhum, o erro da API. Nunca os dois: o erro antigo da API não descreve
  // mais a tentativa atual quando o reenvio nem chegou a sair.
  const mensagensDeCampo = [errors.usuario?.message, errors.senha?.message].filter(Boolean);
  const alerta = mensagensDeCampo.length > 0 ? `Corrija os campos: ${mensagensDeCampo.join(" ")}` : erroDaApi;

  return (
    <main className={styles.centro}>
      <form
        className={styles.cartao}
        onSubmit={handleSubmit(enviar, () => setErroDaApi(null))}
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
        {alerta && (
          <p role="alert" className={styles.erro}>
            {alerta}
          </p>
        )}

        <CampoTexto
          id="usuario"
          rotulo="Usuário"
          type="text"
          autoComplete="username"
          autoCapitalize="none"
          spellCheck={false}
          erro={errors.usuario?.message}
          {...register("usuario")}
        />

        <CampoTexto
          id="senha"
          rotulo="Senha"
          type="password"
          autoComplete="current-password"
          erro={errors.senha?.message}
          {...register("senha")}
        />

        <button className={styles.botao} type="submit" disabled={isSubmitting}>
          {isSubmitting ? "Entrando…" : "Entrar"}
        </button>
      </form>
    </main>
  );
}
