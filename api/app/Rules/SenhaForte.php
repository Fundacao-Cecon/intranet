<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Política de senha (ADR 0005, decisão 3, opção B): comprimento e lista de proibidas, sem regra
 * de composição (maiúscula, número, símbolo), que só induz "Senha@123". Mínimo de 10 caracteres,
 * máximo de 128; recusa igual ao login, senhas comuns, sequências e repetições.
 *
 * Conta caracteres, não bytes. Devolve todas as mensagens que se aplicam, de uma vez.
 */
final class SenhaForte implements ValidationRule
{
    public const MINIMO = 10;

    public const MAXIMO = 128;

    /** Senhas comuns com 10 caracteres ou mais (as menores já caem no mínimo). Comparação em minúsculas. */
    private const COMUNS = [
        '1q2w3e4r5t', '1q2w3e4r5t6y', '123456789012', '12345678910', '1234512345', '0123456789',
        'qwerty12345', 'qwerty123456', 'qwertyuiop1', 'q1w2e3r4t5', 'password12', 'password123',
        'password1234', 'passw0rd123', 'senha12345', 'senha123456', 'senha1234567', 'minhasenha',
        'minhasenha1', 'minhasenha123', 'mudar12345', 'trocar12345', 'abc1234567', 'abcd123456',
        'iloveyou12', 'letmein123', 'welcome123', 'admin12345', 'admin123456', 'administrador',
        'administrator', 'administrador1', 'fcecon2015', 'fcecon2026', 'fcecon12345', 'fcecon@2026',
        'fundacaocecon', 'intranet123', 'intranet2026', 'intranet@2026', 'cecon123456',
    ];

    /** Sequências conhecidas: a senha inteira dentro de uma delas (ou do avesso) é recusada. */
    private const SEQUENCIAS = [
        '01234567890123456789',
        'abcdefghijklmnopqrstuvwxyz',
        'qwertyuiopasdfghjklzxcvbnm',
        'qazwsxedcrfvtgbyhnujmikolp',
    ];

    public function __construct(private readonly string $login) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('A senha precisa ser um texto.');

            return;
        }

        $tamanho = mb_strlen($value);
        if ($tamanho < self::MINIMO) {
            $fail('A senha precisa ter pelo menos '.self::MINIMO.' caracteres.');
        }
        if ($tamanho > self::MAXIMO) {
            $fail('A senha pode ter no máximo '.self::MAXIMO.' caracteres.');
        }

        $minuscula = mb_strtolower($value);

        if ($minuscula === mb_strtolower($this->login)) {
            $fail('A senha não pode ser igual ao login.');
        }
        if (in_array($minuscula, self::COMUNS, true)) {
            $fail('Essa senha é muito comum. Escolha outra.');
        }
        if ($this->ehSequenciaOuRepeticao($minuscula)) {
            $fail('Evite sequências e repetições (como 1234567890 ou aaaaaaaaaa).');
        }
    }

    private function ehSequenciaOuRepeticao(string $minuscula): bool
    {
        if ($minuscula === '') {
            return false;
        }

        foreach (self::SEQUENCIAS as $sequencia) {
            if (str_contains($sequencia, $minuscula) || str_contains(strrev($sequencia), $minuscula)) {
                return true;
            }
        }

        // Um bloco curto repetido até o fim: "aaaaaaaaaa", "1212121212", "abcabcabcabc".
        return preg_match('/^(.{1,4})\1+$/su', $minuscula) === 1;
    }
}
