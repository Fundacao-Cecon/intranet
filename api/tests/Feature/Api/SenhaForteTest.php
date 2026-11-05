<?php

use App\Rules\SenhaForte;
use Illuminate\Support\Facades\Validator;

/*
| T024 — política de senha (ADR 0005, decisão 3, opção B): comprimento + lista de proibidas, sem
| regra de composição (maiúscula, número, símbolo), que só induz "Senha@123".
*/

/** Mensagens de erro de $senha para o login $login (vazio = aceita). */
function erroDaSenha(string $senha, string $login = 'ana'): array
{
    return Validator::make(['password' => $senha], ['password' => [new SenhaForte($login)]])
        ->errors()->get('password');
}

test('aceita senhas boas, inclusive sem maiúscula, número ou símbolo', function (string $senha) {
    expect(erroDaSenha($senha))->toBe([]);
})->with([
    'frase longa em minúsculas' => 'cachorro azul voa longe',
    'mistura comum' => 'Tr0ca-Segura-2026',
    'exatamente 10' => 'Xk9#mQ2$vL',
    'só letras' => 'oceanocalmodemanha',
    'com acento e espaço' => 'café com açúcar às 8',
]);

test('recusa menos de 10 caracteres', function () {
    expect(erroDaSenha('Xk9#mQ2$v'))->toBe(['A senha precisa ter pelo menos 10 caracteres.']);
    expect(erroDaSenha('a'))->toContain('A senha precisa ter pelo menos 10 caracteres.');
    // Texto vazio nem chega à regra: quem cobre é o `required` do endpoint (ver PasswordChangeTest).
});

test('conta caracteres, não bytes (acentos não enganam o tamanho)', function () {
    // 9 caracteres (18 bytes), sem repetição nem sequência: só o tamanho pode reprovar
    expect(erroDaSenha('áéíóúãõçà'))->toBe(['A senha precisa ter pelo menos 10 caracteres.']);
    expect(erroDaSenha('áéíóú-ãõç-12'))->toBe([]);             // 12 caracteres
});

test('limite superior de 128 caracteres', function () {
    $variada = substr(str_repeat('o rato roeu a roupa do rei de roma e a rainha ', 6), 0, 128);

    expect(erroDaSenha($variada))->toBe([]);
    expect(erroDaSenha($variada.'x'))->toBe(['A senha pode ter no máximo 128 caracteres.']);
});

test('recusa senha igual ao login, sem diferenciar maiúsculas', function () {
    expect(erroDaSenha('administrador', 'administrador'))->toContain('A senha não pode ser igual ao login.');
    expect(erroDaSenha('Administrador', 'ADMINISTRADOR'))->toContain('A senha não pode ser igual ao login.');
});

test('recusa senhas comuns, sem diferenciar maiúsculas', function (string $senha) {
    expect(erroDaSenha($senha))->toContain('Essa senha é muito comum. Escolha outra.');
})->with(['password123', 'SENHA123456', 'Fcecon2015', 'minhasenha', 'Intranet2026', 'qwerty12345']);

test('recusa sequências e repetições', function (string $senha) {
    expect(erroDaSenha($senha))->toContain('Evite sequências e repetições (como 1234567890 ou aaaaaaaaaa).');
})->with([
    'números em ordem' => '1234567890',
    'números em ordem inversa' => '9876543210',
    'letras em ordem' => 'abcdefghijkl',
    'linha do teclado' => 'qwertyuiop',
    'um caractere repetido' => 'aaaaaaaaaaaa',
    'bloco repetido' => 'abcabcabcabc',
    'par repetido' => '1212121212',
]);

test('uma sequência dentro de uma senha longa e variada não reprova', function () {
    expect(erroDaSenha('meu cofre 1234 azul voador'))->toBe([]);
});

test('várias falhas voltam todas as mensagens de uma vez', function () {
    $erros = erroDaSenha('ana', 'ana');

    expect($erros)->toContain('A senha precisa ter pelo menos 10 caracteres.');
    expect($erros)->toContain('A senha não pode ser igual ao login.');
});

test('valor que não é texto é recusado sem lançar exceção', function () {
    $v = Validator::make(['password' => ['x']], ['password' => [new SenhaForte('ana')]]);

    expect($v->fails())->toBeTrue();
});
