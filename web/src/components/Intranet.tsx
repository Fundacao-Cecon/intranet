"use client";

import { useCallback, useEffect, useState } from "react";

import { ApiError, login, logout, me, type Usuario } from "@/lib/api";
import { LoginForm } from "./LoginForm";
import styles from "./Intranet.module.css";

type Estado =
  | { tipo: "carregando" }
  | { tipo: "visitante" }
  | { tipo: "falha" }
  | { tipo: "logado"; usuario: Usuario };

const PERFIS: Record<string, string> = { admin: "Administrador", user: "Usuário comum" };

/**
 * Raiz da interface. Pergunta à API quem está logado e mostra o login ou o painel. A decisão
 * de acesso é só da API: aqui a tela apenas reflete a resposta (ADR 0008).
 */
export function Intranet({ avisoInicial }: { avisoInicial?: string }) {
  const [estado, setEstado] = useState<Estado>({ tipo: "carregando" });
  const [erroSair, setErroSair] = useState(false);

  const consultar = useCallback(async () => {
    setEstado({ tipo: "carregando" });
    try {
      const usuario = await me();
      setEstado(usuario ? { tipo: "logado", usuario } : { tipo: "visitante" });
    } catch {
      setEstado({ tipo: "falha" });
    }
  }, []);

  useEffect(() => {
    // Consulta única ao abrir a página; o estado só muda depois da resposta da API.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void consultar();
  }, [consultar]);

  async function sair() {
    setErroSair(false);
    try {
      await logout();
      setEstado({ tipo: "visitante" });
    } catch (e) {
      // Sessão já encerrada pelo servidor (401) conta como saída; qualquer outra falha não.
      if (e instanceof ApiError && e.status === 401) setEstado({ tipo: "visitante" });
      else setErroSair(true);
    }
  }

  if (estado.tipo === "carregando") {
    return (
      <main className={styles.centro}>
        <p role="status">Carregando…</p>
      </main>
    );
  }

  if (estado.tipo === "falha") {
    return (
      <main className={styles.centro}>
        <div className={styles.cartao}>
          <p role="alert" className={styles.erro}>
            Não foi possível consultar o servidor. Verifique a conexão.
          </p>
          <button className={styles.botao} type="button" onClick={() => void consultar()}>
            Tentar de novo
          </button>
        </div>
      </main>
    );
  }

  if (estado.tipo === "visitante") {
    return (
      <LoginForm
        entrar={login}
        aoEntrar={(usuario) => setEstado({ tipo: "logado", usuario })}
        aviso={avisoInicial}
      />
    );
  }

  const { usuario } = estado;
  return (
    <main className={styles.centro}>
      <div className={styles.cartao}>
        <h1 className={styles.titulo}>Olá, {usuario.username}</h1>
        <p className={styles.subtitulo}>{PERFIS[usuario.role] ?? usuario.role}</p>
        <p>Os arquivos e pastas chegam nas próximas etapas da nova versão.</p>
        {erroSair && (
          <p role="alert" className={styles.erro}>
            Não foi possível sair. Tente de novo.
          </p>
        )}
        <button className={styles.botao} type="button" onClick={() => void sair()}>
          Sair
        </button>
      </div>
    </main>
  );
}
