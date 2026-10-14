<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NewPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || mb_strlen($value) < 12 || strlen($value) > 72 || str_contains($value, "\0")) {
            $fail('A senha deve ter pelo menos 12 caracteres, até 72 bytes e nenhum caractere nulo.');
        }
    }
}
