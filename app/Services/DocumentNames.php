<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class DocumentNames
{
    public function validate(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 255 || str_starts_with($name, '.') || str_contains($name, '..')
            || ! preg_match('/^[\p{L}\p{N}\p{Pd} ._()]+$/uD', $name)) {
            throw ValidationException::withMessages(['nome' => 'Use um nome de até 255 caracteres com letras, números, espaços, ponto, traço, parênteses ou sublinhado, sem caminhos ou pontos consecutivos.']);
        }

        return $name;
    }
}
