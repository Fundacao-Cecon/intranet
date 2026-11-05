"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import { useForm } from "react-hook-form";

import { ApiError } from "@/lib/api";
import { mensagemDeErro } from "@/lib/erros";
import { type DadosTrocaSenha, esquemaTrocaSenha } from "@/lib/validacao";
import { CampoTexto } from "./CampoTexto";
import styles from "./Intranet.module.css";

type Props = {
  trocar: (senhaAtual: string, novaSenha: string) => Promise<void>;
  aoVoltar: () => void;
  /** A sessão acabou no meio da troca (401): quem abriu a tela leva de volta ao login. */
  aoExpirar: () => void;
};

const VAZIO: DadosTrocaSenha = { senhaAtual: "", novaSenha: "", confirmacao: "" };

export function TrocaSenhaForm({ trocar, aoVoltar, aoExpirar }: Props) {
  // Erro que não é de um campo (bloqueio, servidor, rede) e o aviso de sucesso.
  const [erroDaApi, setErroDaApi] = useState<string | null>(null);
  const [trocada, setTrocada] = useState(false);

  const {
    register,
    handleSubmit,
    reset,
    resetField,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<DadosTrocaSenha>({
    resolver: zodResolver(esquemaTrocaSenha),
    defaultValues: VAZIO,
  });

  async function enviar({ senhaAtual, novaSenha }: DadosTrocaSenha) {
    setErroDaApi(null);
    setTrocada(false);
    try {
      await trocar(senhaAtual, novaSenha);
      reset(VAZIO); // nenhuma das três senhas fica na tela nem no estado
      setTrocada(true);
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) {
        aoExpirar();
        return;
      }
      if (e instanceof ApiError && e.status === 422 && e.erros) {
        // Limpa só o que a API rejeitou (resetField também apaga o erro do campo, então vem antes).
        if (e.erros.current_password) {
          resetField("senhaAtual");
          setError("senhaAtual", { message: e.erros.current_password.join(" ") });
        }
        if (e.erros.password) {
          resetField("novaSenha");
          resetField("confirmacao");
          setError("novaSenha", { message: e.erros.password.join(" ") });
        }
        return;
      }
      setErroDaApi(mensagemDeErro(e));
    }
  }

  // Um único alerta: o resumo dos erros de campo (anunciados de uma vez) OU o erro da API.
  const mensagensDeCampo = [errors.senhaAtual?.message, errors.novaSenha?.message, errors.confirmacao?.message].filter(Boolean);
  const alerta = mensagensDeCampo.length > 0 ? `Corrija os campos: ${mensagensDeCampo.join(" ")}` : erroDaApi;

  return (
    <main className={styles.centro}>
      <form
        className={styles.cartao}
        onSubmit={handleSubmit(enviar, () => setErroDaApi(null))}
        aria-labelledby="titulo-troca"
        noValidate
      >
        <h1 id="titulo-troca" className={styles.titulo}>
          Trocar senha
        </h1>
        <p className={styles.subtitulo}>Informe a senha atual e escolha uma nova.</p>

        {trocada && (
          <p role="status" className={styles.sucesso}>
            Senha alterada. As outras sessões desta conta foram encerradas.
          </p>
        )}
        {alerta && (
          <p role="alert" className={styles.erro}>
            {alerta}
          </p>
        )}

        <CampoTexto
          id="senha-atual"
          rotulo="Senha atual"
          type="password"
          autoComplete="current-password"
          erro={errors.senhaAtual?.message}
          {...register("senhaAtual")}
        />
        <CampoTexto
          id="nova-senha"
          rotulo="Nova senha"
          type="password"
          autoComplete="new-password"
          dica="Use pelo menos 10 caracteres. Uma frase longa é uma boa senha."
          erro={errors.novaSenha?.message}
          {...register("novaSenha")}
        />
        <CampoTexto
          id="confirmacao"
          rotulo="Confirmar nova senha"
          type="password"
          autoComplete="new-password"
          erro={errors.confirmacao?.message}
          {...register("confirmacao")}
        />

        <button className={styles.botao} type="submit" disabled={isSubmitting}>
          {isSubmitting ? "Trocando…" : "Trocar senha"}
        </button>
        <button className={styles.secundario} type="button" onClick={aoVoltar}>
          Voltar
        </button>
      </form>
    </main>
  );
}
