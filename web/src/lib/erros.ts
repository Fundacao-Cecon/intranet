import { ApiError } from "@/lib/api";

/** Texto para o usuário. Nunca repassa o texto cru de erro 5xx (pode trazer detalhes do servidor). */
export function mensagemDeErro(erro: unknown): string {
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
