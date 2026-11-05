import * as z from "zod/mini";

/**
 * Validação do formulário de login, só para dar resposta imediata ao usuário. Quem decide é a
 * API (ADR 0008): ela valida de novo, aplica o limitador e confere a senha. Por isso aqui não há
 * política de senha (10 caracteres, lista de proibidas: T024) nem checagem de formato do login.
 *
 * Os limites (255 e 1024) são os mesmos da API (AuthController::login).
 *
 * Usa `zod/mini` (API em funções, bem menor no pacote do navegador que o `zod` completo).
 */
export const esquemaLogin = z.object({
  // O login é aparado: um espaço digitado sem querer não deve fazer a entrada falhar.
  // A ordem dos checks importa: aparar antes de exigir o tamanho mínimo.
  usuario: z
    .string({ error: "Informe o usuário." })
    .check(z.trim(), z.minLength(1, "Informe o usuário."), z.maxLength(255, "O usuário é longo demais.")),
  // A senha NUNCA é aparada: espaços podem fazer parte dela.
  senha: z
    .string({ error: "Informe a senha." })
    .check(z.minLength(1, "Informe a senha."), z.maxLength(1024, "A senha é longa demais.")),
});

export type DadosLogin = z.infer<typeof esquemaLogin>;
