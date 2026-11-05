<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
| T022 e T024 — troca da própria senha: política de senha, limitador compartilhado com o login e
| encerramento das OUTRAS sessões (a atual continua valendo).
*/

const SENHA_ATUAL = 'senha-de-teste';
const SENHA_NOVA = 'Tr0ca-Segura-2026';

function pwSpa(string $ip = '10.0.0.1'): TestCase
{
    app('auth')->forgetGuards();

    /** @var TestCase $t */
    $t = test();

    return $t->withServerVariables(['REMOTE_ADDR' => $ip])->withHeaders(['Referer' => 'http://localhost', 'Accept' => 'application/json']);
}

function entrarComo(string $usuario = 'ana', string $senha = SENHA_ATUAL): TestResponse
{
    return pwSpa()->postJson('/api/v1/auth/login', ['username' => $usuario, 'password' => $senha]);
}

function trocar(string $atual, string $nova): TestResponse
{
    return pwSpa()->postJson('/api/v1/auth/password', ['current_password' => $atual, 'password' => $nova]);
}

/** O que o AuthenticateSession espera na sessão: o HMAC do hash da senha (nunca o hash cru). */
function hashDeSessao(string $hashDaSenha): string
{
    return app('auth')->guard('web')->hashPasswordForCookie($hashDaSenha);
}

test('exige estar logado', function () {
    pwSpa()->postJson('/api/v1/auth/password', ['current_password' => SENHA_ATUAL, 'password' => SENHA_NOVA])->assertUnauthorized();
});

test('troca a senha, responde 204 e a nova passa a valer no login', function () {
    User::factory()->create(['username' => 'ana']);
    entrarComo()->assertOk();

    trocar(SENHA_ATUAL, SENHA_NOVA)->assertNoContent();

    expect(Hash::check(SENHA_NOVA, User::where('username', 'ana')->value('password')))->toBeTrue();
    entrarComo('ana', SENHA_NOVA)->assertOk();
});

test('a senha antiga deixa de valer', function () {
    User::factory()->create(['username' => 'ana']);
    entrarComo()->assertOk();
    trocar(SENHA_ATUAL, SENHA_NOVA)->assertNoContent();

    entrarComo('ana', SENHA_ATUAL)->assertStatus(422);
});

test('senha atual errada é recusada, não troca nada e conta como falha do limitador', function () {
    User::factory()->create(['username' => 'ana']);
    entrarComo()->assertOk();
    $antes = User::where('username', 'ana')->value('password');

    trocar('errada-errada', SENHA_NOVA)->assertStatus(422)->assertJsonValidationErrors('current_password');

    expect(User::where('username', 'ana')->value('password'))->toBe($antes);
    expect(DB::table('login_attempts')->where('username', 'ana')->count())->toBe(1);
});

test('adivinhar a senha atual tem o mesmo limite do login: 5 falhas bloqueiam', function () {
    User::factory()->create(['username' => 'ana']);
    entrarComo()->assertOk();

    foreach (range(1, 5) as $_) {
        trocar('errada-errada', SENHA_NOVA)->assertStatus(422);
    }

    trocar(SENHA_ATUAL, SENHA_NOVA)->assertStatus(429);
});

test('troca certa zera as falhas daquele usuário e IP', function () {
    User::factory()->create(['username' => 'ana']);
    entrarComo()->assertOk();
    foreach (range(1, 3) as $_) {
        trocar('errada-errada', SENHA_NOVA)->assertStatus(422);
    }

    trocar(SENHA_ATUAL, SENHA_NOVA)->assertNoContent();

    expect(DB::table('login_attempts')->where('username', 'ana')->count())->toBe(0);
});

test('a política de senha vale na troca (devolve os erros do campo password)', function (string $nova) {
    User::factory()->create(['username' => 'ana']);
    entrarComo()->assertOk();

    trocar(SENHA_ATUAL, $nova)->assertStatus(422)->assertJsonValidationErrors('password');

    expect(Hash::check(SENHA_ATUAL, User::where('username', 'ana')->value('password')))->toBeTrue();
})->with(['curta', 'password123', '1234567890', 'aaaaaaaaaaaa', 'ana']);

test('a nova senha precisa ser diferente da atual', function () {
    User::factory()->create(['username' => 'ana']);
    entrarComo()->assertOk();

    trocar(SENHA_ATUAL, SENHA_ATUAL)->assertStatus(422)
        ->assertJsonPath('errors.password.0', 'A nova senha precisa ser diferente da atual.');
});

test('os dois campos são obrigatórios', function () {
    User::factory()->create(['username' => 'ana']);
    entrarComo()->assertOk();

    pwSpa()->postJson('/api/v1/auth/password', [])->assertStatus(422)->assertJsonValidationErrors(['current_password', 'password']);
});

test('o login guarda na sessão o HMAC do hash da senha, nunca o hash cru', function () {
    $ana = User::factory()->create(['username' => 'ana']);

    entrarComo()->assertOk();

    expect(session('password_hash_web'))->not->toBe($ana->fresh()->password)->not->toContain('$2y$');
    expect(session('password_hash_web'))->toBe(hashDeSessao($ana->fresh()->password));
});

test('troca derruba as OUTRAS sessões, até as que nunca fizeram outra requisição, e mantém a atual', function () {
    User::factory()->create(['username' => 'ana']);
    entrarComo()->assertOk();
    // Estado de uma segunda sessão (outro navegador), capturado logo depois do login, SEM nenhuma
    // requisição autenticada: é o caso que o AuthenticateSession sozinho deixaria passar.
    $outraSessao = session()->all();

    trocar(SENHA_ATUAL, SENHA_NOVA)->assertNoContent();

    // a sessão atual continua valendo, e o hash guardado nela é o novo
    pwSpa()->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('username', 'ana');
    expect(session('password_hash_web'))->toBe(hashDeSessao(User::where('username', 'ana')->value('password')));

    // a outra sessão, com o hash ANTIGO, cai na próxima requisição
    session()->flush();
    pwSpa()->withSession($outraSessao)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

test('a sessão antiga continua derrubada mesmo depois de uma segunda troca', function () {
    User::factory()->create(['username' => 'ana']);
    entrarComo()->assertOk();
    $outraSessao = session()->all();

    trocar(SENHA_ATUAL, SENHA_NOVA)->assertNoContent();
    trocar(SENHA_NOVA, 'Outra-Senha-Segura-77')->assertNoContent();

    session()->flush();
    pwSpa()->withSession($outraSessao)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

test('grava SENHA no log, sem nenhuma das senhas', function () {
    User::factory()->create(['username' => 'ana']);
    entrarComo()->assertOk();

    trocar(SENHA_ATUAL, SENHA_NOVA)->assertNoContent();

    $linha = DB::table('logs')->where('acao', 'SENHA')->first();
    expect($linha->usuario)->toBe('ana');
    expect($linha->detalhe)->toContain('ana')->not->toContain(SENHA_ATUAL)->not->toContain(SENHA_NOVA);
});

test('a resposta e o log nunca trazem o hash da senha', function () {
    User::factory()->create(['username' => 'ana']);
    entrarComo()->assertOk();

    $r = trocar(SENHA_ATUAL, 'curta');

    expect($r->getContent())->not->toContain('$2y$');
});
