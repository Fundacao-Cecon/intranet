import type { ComponentProps } from "react";

import styles from "./Intranet.module.css";

type Props = ComponentProps<"input"> & {
  id: string;
  rotulo: string;
  /** Texto de ajuda sempre visível (por exemplo, a regra da senha). */
  dica?: string;
  /** Mensagem de erro de preenchimento deste campo (vem do esquema Zod). */
  erro?: string;
};

/**
 * Campo de formulário acessível: rótulo ligado ao input e, quando há erro, o campo fica marcado
 * como inválido e aponta para a mensagem por `aria-describedby`. A mensagem de campo NÃO é um
 * alerta: quem anuncia todos os erros de uma vez é o resumo do formulário (role="alert").
 *
 * No React 19 o `ref` chega como propriedade comum, então o `register()` do React Hook Form
 * passa direto pelo espalhamento.
 */
export function CampoTexto({ id, rotulo, dica, erro, ...resto }: Props) {
  const idErro = `${id}-erro`;
  const idDica = `${id}-dica`;
  // O erro e a dica descrevem o campo juntos; sem nenhum dos dois, vale o que o chamador passou.
  const descricao = [dica ? idDica : null, erro ? idErro : null].filter(Boolean).join(" ") || resto["aria-describedby"];

  return (
    <>
      <label className={styles.rotulo} htmlFor={id}>
        {rotulo}
      </label>
      <input
        className={styles.campo}
        aria-required="true"
        {...resto}
        id={id}
        aria-invalid={erro ? true : undefined}
        aria-describedby={descricao}
      />
      {dica && (
        <p id={idDica} className={styles.dica}>
          {dica}
        </p>
      )}
      {erro && (
        <p id={idErro} className={styles.erroCampo}>
          {erro}
        </p>
      )}
    </>
  );
}
