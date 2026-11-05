import { z } from "zod";

/**
 * Validação do formulário de login, só para dar resposta imediata ao usuário. Quem decide é a
 * API (ADR 0008): ela valida de novo, aplica o limitador e confere a senha. Por isso aqui não há
 * política de senha (10 caracteres, lista de proibidas: T024) nem checagem de formato do login.
 *
 * Os limites (255 e 1024) são os mesmos da API (AuthController::login).
 */
export const esquemaLogin = z.object({
  // O login é aparado: um espaço digitado sem querer não deve fazer a entrada falhar.
  usuario: z
    .string({ error: "Informe o usuário." })
    .trim()
    .min(1, "Informe o usuário.")
    .max(255, "O usuário é longo demais."),
  // A senha NUNCA é aparada: espaços podem fazer parte dela.
  senha: z.string({ error: "Informe a senha." }).min(1, "Informe a senha.").max(1024, "A senha é longa demais."),
});

export type DadosLogin = z.infer<typeof esquemaLogin>;
